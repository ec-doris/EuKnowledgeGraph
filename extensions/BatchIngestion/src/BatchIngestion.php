<?php

namespace MediaWiki\Extension\BatchIngestion;

use CommentStoreComment;
use Deserializers\DispatchableDeserializer;
use Exception;
use InvalidArgumentException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use RecentChange;
use Status;
use Title;
use User;
use Wikibase\DataAccess\EntitySource;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\Item;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Lib\Store\EntityRevision;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Repo\Content\EntityContent;
use Wikibase\Repo\Content\EntityContentFactory;
use Wikibase\Repo\GenericEventDispatcher;
use Wikimedia\Rdbms\IDatabase;
use WikiPage;

class BatchIngestion {
    /** @var User */
    private $user;
    /** @var mixed */
    private $body;
    /** @var IDatabase|false */
    private $db;
    /** @var DispatchableDeserializer */
    private $entityDeserializer;
    /** @var EntitySource */
    private $entitySource;
    /** @var EntityTitleStoreLookup */
    private $entityTitleStoreLookup;
    /** @var EntityContentFactory */
    private $contentFactory;
    /** @var BatchIdGenerator */
    private $idGenerator;
    /** @var EntityIdComposer */
    private $entityIdComposer;
    /** @var WikiPageFactory */
    private $wikiPageFactory;
    /** @var GenericEventDispatcher */
    private $dispatcher;
    /** @var string[] */
    private $logs = [];
    /** @var mixed */
    private $benchtimes;
    /** @var bool */
    private $verbose;
    /**
     * @param User $user
     * @param mixed $body
     * @param bool $verbose
     */
    public function __construct( $user, $body, $verbose = false ) {
        $this->user = $user;
        $this->body = $body;
        $this->verbose = $verbose;
        $this->db = MediaWikiServices::getInstance()
            ->getDBLoadBalancer()
            ->getConnection(DB_PRIMARY);
        $services = MediaWikiServices::getInstance();
        $settings = WikibaseRepo::getSettings($services);
        $this->entityDeserializer = WikibaseRepo::getAllTypesEntityDeserializer($services);
        $this->entitySource = WikibaseRepo::getLocalEntitySource($services);
        $this->entityTitleStoreLookup = WikibaseRepo::getEntityTitleStoreLookup($services);
        $this->contentFactory = WikibaseRepo::getEntityContentFactory($services);
        $this->idGenerator = new BatchIdGenerator(
            WikibaseRepo::getRepoDomainDbFactory( $services )->newRepoDb(),
            $settings->getSetting( 'reservedIds' ),
            // $settings->getSetting( 'idGeneratorSeparateDbConnection' )
            false
        );
        $this->entityIdComposer = WikibaseRepo::getEntityIdComposer($services);
        $this->wikiPageFactory = $services->getWikiPageFactory();
        $this->dispatcher = new GenericEventDispatcher( EntityStoreWatcher::class );
    }
    /**
     * @param string $log
     */
    public function log( $log ) {
        if ( $this->verbose )
            $this->logs[] = $log;
    }
    /**
     * Benchmarks a function.
     * It returns the result of the function.
     * @param string $name
     * @param callable $function
     * @return mixed
     */
    private function benchmark( $name, $function ) {
        if ( $this->verbose ) {
            $start = microtime( true );
        }
        $result = $function();
        if ( $this->verbose ) {
            $end = microtime( true );
            if ( !isset( $this->benchtimes[$name] ) ) {
                $this->benchtimes[$name] = [
                    "count" => 0,
                    "time" => 0
                ];
            }
            $this->benchtimes[$name]["count"]++;
            $this->benchtimes[$name]["time"] += $end - $start;
        }
        return $result;
    }
    /**
     * @return string
     */
    public function getLogs() {
        if ( !$this->verbose )
            return "";
        $logs = implode( "\n", $this->logs );
        // Sort benchtimes by their time
        uasort( $this->benchtimes, function ( $a, $b ) {
            return $a["time"] < $b["time"];
        } );
        foreach ( $this->benchtimes as $name => $data ) {
            $total = $data["time"];
            $count = $data["count"];
            $average = $total / $count;
            $total = rtrim(sprintf("%.20f", $total), "0");
            $average = rtrim(sprintf("%.20f", $average), "0");
            $logs .= "\n\n$name (count of $count):\nTotal: $total s\nAverage: $average s";
        }
        $logs .= "\n\n";
        // $logs .= $this->db->getCalls();
        // $logs .= $this->db2->getCalls();
        return $logs;
    }
    /**
     * @param EntityId $entityId
     *
     * @return Title|null
     */
    private function getTitleForEntity( $entityId ) {
        return $this
            ->entityTitleStoreLookup
            ->getTitleForId( $entityId );
    }
    /**
     * Returns the items from the body of the request, filtering out invalid items.
     * @return Item[]
     */
    private function getItems() {
        $items = array_map(function (array $item) {
            try {
                return $this->entityDeserializer->deserialize($item);
            } catch (\Exception $_) {
                return null;
            }
        }, $this->body["entities"]);
        $items = array_filter($items);
        return $items;
    }
    /**
     * @param Item[] $items
     */
    private function assignFreshIds( $items ) {
        if ( empty( $items ) )
            return;
        $type = $items[0]->getType();
        $handler = $this->contentFactory->getContentHandlerForType( $type );
        $contentModelId = $handler->getModelID();
        $count = count($items);
        $numericIds = $this->idGenerator->getNewIds( $contentModelId, $count );
        for ( $i = 0; $i < $count; $i++ ) {
            $numericId = $numericIds[$i];
            $item = $items[$i];
            $itemId = $this->entityIdComposer->composeEntityId( '', $type, $numericId );
            $item->setId( $itemId );
        }
    }
    /**
     * Returns the WikiPage object for the item with provided entity.
     *
     * @param EntityId $entityId
     *
     * @throws InvalidArgumentException
     * @throws StorageException
     * @return WikiPage
     */
    private function getWikiPageForEntity( $entityId ) {
        $title = $this->getTitleForEntity( $entityId );
        if ( !$title )
            throw new StorageException( 'Entity could not be mapped to a page title!' );
        return $this->wikiPageFactory->newFromTitle( $title );
    }
    /**
     * @param int $flags
     * @param RevisionRecord|null $parentRevision
     * @param string $slotRole
     * @return int
     * @throws StorageException
     */
    private function adjustFlagsForMCR( $flags, $parentRevision, $slotRole ) {
        if ( $flags & EDIT_UPDATE ) {
            if ( !$parentRevision )
                throw new StorageException( "Can't perform an update with no parent revision" );
            if ( !$parentRevision->hasSlot( $slotRole ) )
                throw new StorageException(
                    "Can't perform an update when the parent revision doesn't have expected slot: " . $slotRole
                );
        }
        if ( $flags & EDIT_NEW && $parentRevision && $slotRole !== 'main' ) {
            if ( $parentRevision->hasSlot( $slotRole ) )
                throw new StorageException( "Can't create slot, it already exists: " . $slotRole );
            $flags = ( $flags & ~EDIT_NEW ) | EDIT_UPDATE;
        }
        return $flags;
    }
    /**
     * @param EntityContent $entityContent the entity to save.
     * @param string $summary
     * @param int $flags Flags as used by WikiPage::doEditContent, use EDIT_XXX constants.
     * @param int|bool $baseRevId
     * @param string[] $tags
     *
     * @throws StorageException
     * @return RevisionRecord The new revision (or the latest one, in case of a null edit).
     */
    private function saveEntityContent(
        EntityContent $entityContent,
        $summary = '',
        $flags = 0,
        $baseRevId = false,
        array $tags = []
    ) {
        $id = $entityContent->getEntityId();
        $page = $this->getWikiPageForEntity( $id );
        $slotRole = $this->contentFactory->getSlotRoleForType( $id->getEntityType() );
        $updater = $page->newPageUpdater( $this->user );
        $updater->setRcPatrolStatus( RecentChange::PRC_AUTOPATROLLED );
        $updater->addTags( $tags );
        $flags = $this->adjustFlagsForMCR(
            $flags,
            $updater->grabParentRevision(),
            $slotRole
        );
        if ( $baseRevId && $updater->hasEditConflict( $baseRevId ) ) {
            throw new StorageException( Status::newFatal( 'edit-conflict' ) );
        }
        if (
            ( $flags & EDIT_NEW ) === 0 &&
            $page->getRevisionRecord() &&
            $page->getRevisionRecord()->hasSlot( $slotRole ) &&
            $entityContent->equals( $page->getRevisionRecord()->getContent( $slotRole ) )
        )
            return $page->getRevisionRecord();
        $page->clear();
        $page->clearPreparedEdit();
        $updater->setContent( $slotRole, $entityContent );
        $revisionRecord = $this->benchmark(
            "revision save",
            fn () => $updater->saveRevision(
                CommentStoreComment::newUnsavedComment( $summary ),
                $flags | EDIT_AUTOSUMMARY
            ),
        );
        $status = $updater->getStatus();
        if ( !$status->isOK() ) {
            throw new StorageException( $status );
        }
        if ( $revisionRecord !== null )
            return $revisionRecord;
        return $page->getRevisionRecord();
    }
    /**
     * @param EntityDocument $entity
     * @param string $summary
     * @param int $flags
     * @param int|bool $baseRevId
     * @param string[] $tags
     *
     * @throws InvalidArgumentException
     * @throws StorageException
     * @return EntityRevision
     */
    private function saveEntity(
        $entity,
        $summary,
        $flags = 0,
        $baseRevId = false,
        $tags = []
    ) {
        assertCanStoreEntity( $entity->getId(), $this->entitySource );
        $content = $this->contentFactory->newFromEntity( $entity );
        if ( !$content->isValid() ) {
            throw new StorageException( Status::newFatal( 'invalid-content-data' ) );
        }
        $revision = $this->saveEntityContent(
            $content,
            $summary,
            $flags,
            $baseRevId,
            $tags,
        );
        $entityRevision = new EntityRevision(
            $entity,
            $revision->getId(),
            $revision->getTimestamp()
        );
        $this->dispatcher->dispatch( 'entityUpdated', $entityRevision );
        return $entityRevision;
    }
    /**
     * @param Item[] $items
     * @param bool $create
     * @return array
     */
    private function editItems( $items, $create ) {
        $allRes = [];
        foreach ($items as $item) {
            $res = [];
            $itemId = $item->getId()->getSerialization();
            $statements = $item->getStatements()->toArray();
            foreach ($statements as $statement) {
                if ($statement->getGuid() !== null)
                    continue;
                $statement->setGuid(
                    $this->benchmark(
                        "claim id generation",
                        fn () => generateClaimId($itemId),
                    ),
                );
            }
            $flags = EDIT_AUTOSUMMARY;
            $flags |= $create ? EDIT_NEW : EDIT_UPDATE;
            $revision = $this->benchmark(
                "entity save",
                fn () => $this->saveEntity(
                    $item,
                    "BatchIngestion",
                    $flags,
                ),
            );
            // Success
            $res["revision"] = $revision->getRevisionId();
            $res["id"] = $revision->getEntity()->getId()->getSerialization();
            $allRes[] = $res;
        }
        return $allRes;
    }
    /**
     * @throws Exception
     * @return array
     */
    public function run() {
        if (!$this->db)
            throw new Exception('No database connection');
        $items = $this->getItems();
        // Assign fresh ids to entities without ids
        $newItems = array_filter($items, fn($item) => $item->getId() === null);
        $oldItems = array_filter($items, fn($item) => $item->getId() !== null);
        $this->benchmark(
            "id generation and assignment",
            fn () => $this->assignFreshIds($newItems),
        );
        // Save entities
        $res1 = $this->benchmark(
            "new items addition",
            fn () => $this->editItems($newItems, true),
        );
        $res2 = $this->benchmark(
            "old items addition",
            fn () => $this->editItems($oldItems, false),
        );
        $res = array_merge($res1, $res2);
        // Return the response
        return [
            "count" => count($items),
            "successes" => count($res),
            "response" => $res,
        ];
    }
}

/**
 * Generates a GUID of the form 1C0EEB2E-BD5F-46F8-BF1E-8CF90B224ED0
 * @return string
 */
function generateGuid() {
    // First generate 8 blocks of 4 hex digits
    $blocks = array();
    for ( $i = 0; $i < 8; $i++ ) {
        $blocks[] = sprintf( '%04X', mt_rand( 0, 0xffff ) );
    }
    // Then join them together with dashes where appropriate
    $guid = implode( '-', array(
        $blocks[0] . $blocks[1],
        $blocks[2],
        $blocks[3],
        $blocks[4],
        $blocks[5] . $blocks[6] . $blocks[7],
    ) );
    return $guid;
}

/**
 * Generate a claim ID
 * @param string $itemId
 * @return string
 */
function generateClaimId( $itemId ) {
    $guid = generateGuid();
    return $itemId . "$" . $guid;
}

/**
 * @param EntityId $id The entity id to check.
 * @param EntitySource $entitySource The entity source to check against.
 * @return bool
 */
function entityIdFromKnownSource( $id, $entitySource ) {
    return in_array( $id->getEntityType(), $entitySource->getEntityTypes() );
}

/**
 * @param EntityId $id The entity id to check.
 * @param EntitySource $entitySource The entity source to check against.
 * @throws InvalidArgumentException
 * @return void
 */
function assertCanStoreEntity( $id, $entitySource ) {
    if ( !entityIdFromKnownSource( $id, $entitySource ) ) {
        throw new InvalidArgumentException(
            'Entities of type: ' . $id->getEntityType() . ' is not provided by source: ' . $entitySource->getSourceName()
        );
    }
}
