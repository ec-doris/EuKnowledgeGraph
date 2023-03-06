<?php

namespace WikibaseQuality\ConstraintReport\Tests\Helper;

use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Lookup\UnresolvedEntityRedirectException;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Repo\WikibaseRepo;

/**
 * @license GPL-2.0-or-later
 * @author BP2014N1
 */
class JsonFileEntityLookup implements EntityLookup {

	/**
	 * Base dir which contains serialized entities as json files.
	 *
	 * @var string
	 */
	private $baseDir;

	/**
	 * @var EntityContentDataCodec
	 */
	private $entityContentDataCodec;

	/**
	 * @param string $baseDir
	 */
	public function __construct( $baseDir ) {
		$this->baseDir = $baseDir;

		$this->entityContentDataCodec = WikibaseRepo::getEntityContentDataCodec();
	}

	/**
	 * Returns the entity with the provided id or null if there is no such entity.
	 *
	 * @param EntityId $entityId
	 *
	 * @return EntityDocument|null
	 * @throws UnresolvedEntityRedirectException
	 */
	public function getEntity( EntityId $entityId ) {
		if ( !$this->hasEntity( $entityId ) ) {
			return null;
		}

		$filePath = $this->buildFilePath( $entityId );
		$serializedEntity = file_get_contents( $filePath );

		if ( $serializedEntity === false ) {
			return null;
		}

		$entity = $this->entityContentDataCodec->decodeEntity( $serializedEntity, CONTENT_FORMAT_JSON );
		if ( $entity ) {
			return $entity;
		}

		$redirect = $this->entityContentDataCodec->decodeRedirect( $serializedEntity, CONTENT_FORMAT_JSON );
		if ( $redirect ) {
			throw new UnresolvedEntityRedirectException( $redirect->getEntityId(), $redirect->getTargetId() );
		}
		return null;
	}

	/**
	 * Returns whether the given entity can bee looked up using getEntity().
	 *
	 * @param EntityId $entityId
	 *
	 * @return boolean
	 */
	public function hasEntity( EntityId $entityId ) {
		return file_exists( $this->buildFilePath( $entityId ) );
	}

	/**
	 * Returns path of the file, which contains the serialized entity.
	 *
	 * @param EntityId $entityId
	 *
	 * @return string
	 */
	private function buildFilePath( EntityId $entityId ) {
		$filePath = sprintf( '%s/%s.json', $this->baseDir, (string)$entityId );
		return $filePath;
	}

}
