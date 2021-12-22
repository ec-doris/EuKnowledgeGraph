<?php

namespace WikibaseQuality\ConstraintReport\Job;

use Config;
use Job;
use JobQueueGroup;
use MediaWiki\MediaWikiServices;
use Serializers\Serializer;
use Title;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\LookupConstants;
use Wikibase\Repo\Store\Store;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintsServices;
use WikibaseQuality\ConstraintReport\ConstraintStore;
use Wikimedia\Assert\Assert;

/**
 * A job that updates the constraints table
 * when changes were made on a property.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class UpdateConstraintsTableJob extends Job {

	const BATCH_SIZE = 10;

	public static function newFromGlobalState( Title $title, array $params ) {
		Assert::parameterType( 'string', $params['propertyId'], '$params["propertyId"]' );
		$repo = WikibaseRepo::getDefaultInstance();
		return new UpdateConstraintsTableJob(
			$title,
			$params,
			$params['propertyId'],
			$params['revisionId'] ?? null,
			MediaWikiServices::getInstance()->getMainConfig(),
			ConstraintsServices::getConstraintStore(),
			$repo->getEntityRevisionLookup( Store::LOOKUP_CACHING_DISABLED ),
			$repo->getBaseDataModelSerializerFactory()->newSnakSerializer()
		);
	}

	/**
	 * @var string
	 */
	private $propertyId;

	/**
	 * @var int|null
	 */
	private $revisionId;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @var ConstraintStore
	 */
	private $constraintStore;

	/**
	 * @var EntityRevisionLookup
	 */
	private $entityRevisionLookup;

	/**
	 * @var Serializer
	 */
	private $snakSerializer;

	/**
	 * @param Title $title
	 * @param string[] $params should contain 'propertyId' => 'P...'
	 * @param string $propertyId property ID of the property for this job (which has the constraint statements)
	 * @param int|null $revisionId revision ID that triggered this job, if any
	 * @param Config $config
	 * @param ConstraintStore $constraintStore
	 * @param EntityRevisionLookup $entityRevisionLookup
	 * @param Serializer $snakSerializer
	 */
	public function __construct(
		Title $title,
		array $params,
		$propertyId,
		$revisionId,
		Config $config,
		ConstraintStore $constraintStore,
		EntityRevisionLookup $entityRevisionLookup,
		Serializer $snakSerializer
	) {
		parent::__construct( 'constraintsTableUpdate', $title, $params );

		$this->propertyId = $propertyId;
		$this->revisionId = $revisionId;
		$this->config = $config;
		$this->constraintStore = $constraintStore;
		$this->entityRevisionLookup = $entityRevisionLookup;
		$this->snakSerializer = $snakSerializer;
	}

	public function extractParametersFromQualifiers( SnakList $qualifiers ) {
		$parameters = [];
		foreach ( $qualifiers as $qualifier ) {
			$qualifierId = $qualifier->getPropertyId()->getSerialization();
			$paramSerialization = $this->snakSerializer->serialize( $qualifier );
			$parameters[$qualifierId][] = $paramSerialization;
		}
		return $parameters;
	}

	public function extractConstraintFromStatement(
		PropertyId $propertyId,
		Statement $constraintStatement
	) {
		$constraintId = $constraintStatement->getGuid();
		$snak = $constraintStatement->getMainSnak();
		'@phan-var \Wikibase\DataModel\Snak\PropertyValueSnak $snak';
		$dataValue = $snak->getDataValue();
		'@phan-var \Wikibase\DataModel\Entity\EntityIdValue $dataValue';
		$entityId = $dataValue->getEntityId();
		$constraintTypeQid = $entityId->getSerialization();
		$parameters = $this->extractParametersFromQualifiers( $constraintStatement->getQualifiers() );
		return new Constraint(
			$constraintId,
			$propertyId,
			$constraintTypeQid,
			$parameters
		);
	}

	public function importConstraintsForProperty(
		Property $property,
		ConstraintStore $constraintStore,
		PropertyId $propertyConstraintPropertyId
	) {
		$constraintsStatements = $property->getStatements()
			->getByPropertyId( $propertyConstraintPropertyId )
			->getByRank( [ Statement::RANK_PREFERRED, Statement::RANK_NORMAL ] );
		$constraints = [];
		foreach ( $constraintsStatements->getIterator() as $constraintStatement ) {
			$constraints[] = $this->extractConstraintFromStatement( $property->getId(), $constraintStatement );
			if ( count( $constraints ) >= self::BATCH_SIZE ) {
				$constraintStore->insertBatch( $constraints );
				$constraints = [];
			}
		}
		$constraintStore->insertBatch( $constraints );
	}

	/**
	 * @see Job::run
	 *
	 * @return bool
	 */
	public function run() {
		// TODO in the future: only touch constraints affected by the edit (requires T163465)

		$propertyId = new PropertyId( $this->propertyId );
		$propertyRevision = $this->entityRevisionLookup->getEntityRevision(
			$propertyId,
			0, // latest
			LookupConstants::LATEST_FROM_REPLICA
		);

		if ( $this->revisionId !== null && $propertyRevision->getRevisionId() < $this->revisionId ) {
			JobQueueGroup::singleton()->push( $this );
			return true;
		}

		$this->constraintStore->deleteForProperty( $propertyId );

		/** @var Property $property */
		$property = $propertyRevision->getEntity();
		'@phan-var Property $property';
		$this->importConstraintsForProperty(
			$property,
			$this->constraintStore,
			new PropertyId( $this->config->get( 'WBQualityConstraintsPropertyConstraintId' ) )
		);

		return true;
	}

}
