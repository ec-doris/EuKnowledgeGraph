<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck;

use InvalidArgumentException;
use LogicException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\StatementListProvidingEntity;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Services\Lookup\EntityLookup;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Statement\Statement;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\DependencyMetadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\EntityContextCursor;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\MainSnakContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\QualifierContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ReferenceContext;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\LoggingHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelperException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\NullResult;
use WikibaseQuality\ConstraintReport\ConstraintLookup;

/**
 * Used to start the constraint-check process and to delegate
 * the statements that has to be checked to the corresponding checkers
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class DelegatingConstraintChecker {

	/**
	 * Wikibase entity lookup.
	 *
	 * @var EntityLookup
	 */
	private $entityLookup;

	/**
	 * @var ConstraintChecker[]
	 */
	private $checkerMap;

	/**
	 * @var ConstraintLookup
	 */
	private $constraintLookup;

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var StatementGuidParser
	 */
	private $statementGuidParser;

	/**
	 * @var LoggingHelper
	 */
	private $loggingHelper;

	/**
	 * @var bool
	 */
	private $checkQualifiers;

	/**
	 * @var bool
	 */
	private $checkReferences;

	/**
	 * @var string[]
	 */
	private $propertiesWithViolatingQualifiers;

	/**
	 * @param EntityLookup $lookup
	 * @param ConstraintChecker[] $checkerMap
	 * @param ConstraintLookup $constraintRepository
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param StatementGuidParser $statementGuidParser
	 * @param LoggingHelper $loggingHelper
	 * @param bool $checkQualifiers whether to check qualifiers
	 * @param bool $checkReferences whether to check references
	 * @param string[] $propertiesWithViolatingQualifiers on statements of these properties,
	 * qualifiers will not be checked
	 */
	public function __construct(
		EntityLookup $lookup,
		array $checkerMap,
		ConstraintLookup $constraintRepository,
		ConstraintParameterParser $constraintParameterParser,
		StatementGuidParser $statementGuidParser,
		LoggingHelper $loggingHelper,
		$checkQualifiers,
		$checkReferences,
		array $propertiesWithViolatingQualifiers
	) {
		$this->entityLookup = $lookup;
		$this->checkerMap = $checkerMap;
		$this->constraintLookup = $constraintRepository;
		$this->constraintParameterParser = $constraintParameterParser;
		$this->statementGuidParser = $statementGuidParser;
		$this->loggingHelper = $loggingHelper;
		$this->checkQualifiers = $checkQualifiers;
		$this->checkReferences = $checkReferences;
		$this->propertiesWithViolatingQualifiers = $propertiesWithViolatingQualifiers;
	}

	/**
	 * Starts the whole constraint-check process for entity or constraint ID on entity.
	 * Statements of the entity will be checked against every constraint that is defined on the property.
	 *
	 * @param EntityId $entityId
	 * @param string[]|null $constraintIds
	 * @param callable|null $defaultResultsPerContext
	 * Optional function to pre-populate the check results per context.
	 * For each {@link Context} where constraints will be checked,
	 * this function (if not null) is first called with that context as argument,
	 * and may return an array of check results to which the regular results are appended.
	 * @param callable|null $defaultResultsPerEntity
	 * Optional function to pre-populate the check results per entity.
	 * This function (if not null) is called once with $entityId as argument,
	 * and may return an array of check results to which the regular results are appended.
	 *
	 * @return CheckResult[]
	 */
	public function checkAgainstConstraintsOnEntityId(
		EntityId $entityId,
		array $constraintIds = null,
		callable $defaultResultsPerContext = null,
		callable $defaultResultsPerEntity = null
	) {
		$checkResults = [];
		$entity = $this->entityLookup->getEntity( $entityId );

		if ( $entity instanceof StatementListProvidingEntity ) {
			$startTime = microtime( true );

			$checkResults = $this->checkEveryStatement(
				$entity,
				$constraintIds,
				$defaultResultsPerContext
			);

			$endTime = microtime( true );

			if ( $constraintIds === null ) { // only log full constraint checks
				$this->loggingHelper->logConstraintCheckOnEntity(
					$entityId,
					$checkResults,
					$endTime - $startTime,
					__METHOD__
				);
			}
		}

		if ( $defaultResultsPerEntity !== null ) {
			$checkResults = array_merge( $defaultResultsPerEntity( $entityId ), $checkResults );
		}

		return $this->sortResult( $checkResults );
	}

	/**
	 * Starts the whole constraint-check process.
	 * Statements of the entity will be checked against every constraint that is defined on the claim.
	 *
	 * @param string $guid
	 * @param string[]|null $constraintIds
	 * @param callable|null $defaultResults Optional function to pre-populate the check results.
	 * For each {@link Context} where constraints will be checked,
	 * this function (if not null) is first called with that context as argument,
	 * and may return an array of check results to which the regular results are appended.
	 *
	 * @return CheckResult[]
	 */
	public function checkAgainstConstraintsOnClaimId(
		$guid,
		array $constraintIds = null,
		callable $defaultResults = null
	) {

		$parsedGuid = $this->statementGuidParser->parse( $guid );
		$entityId = $parsedGuid->getEntityId();
		$entity = $this->entityLookup->getEntity( $entityId );
		if ( $entity instanceof StatementListProvidingEntity ) {
			$statement = $entity->getStatements()->getFirstStatementWithGuid( $guid );
			if ( $statement ) {
				$result = $this->checkStatement(
					$entity,
					$statement,
					$constraintIds,
					$defaultResults
				);
				$output = $this->sortResult( $result );
				return $output;
			}
		}

		return [];
	}

	private function getAllowedContextTypes( Constraint $constraint ) {
		if ( !array_key_exists( $constraint->getConstraintTypeItemId(), $this->checkerMap ) ) {
			return [
				Context::TYPE_STATEMENT,
				Context::TYPE_QUALIFIER,
				Context::TYPE_REFERENCE,
			];
		}

		return array_keys( array_filter(
			$this->checkerMap[$constraint->getConstraintTypeItemId()]->getSupportedContextTypes(),
			function ( $resultStatus ) {
				return $resultStatus !== CheckResult::STATUS_NOT_IN_SCOPE;
			}
		) );
	}

	/**
	 * Like ConstraintChecker::checkConstraintParameters,
	 * but for meta-parameters common to all checkers.
	 *
	 * @param Constraint $constraint
	 *
	 * @return ConstraintParameterException[]
	 */
	private function checkCommonConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		try {
			$this->constraintParameterParser->checkError( $constraintParameters );
		} catch ( ConstraintParameterException $e ) {
			return [ $e ];
		}

		$problems = [];
		try {
			$this->constraintParameterParser->parseExceptionParameter( $constraintParameters );
		} catch ( ConstraintParameterException $e ) {
			$problems[] = $e;
		}
		try {
			$this->constraintParameterParser->parseConstraintStatusParameter( $constraintParameters );
		} catch ( ConstraintParameterException $e ) {
			$problems[] = $e;
		}
		try {
			$this->constraintParameterParser->parseConstraintScopeParameter(
				$constraintParameters,
				$constraint->getConstraintTypeItemId(),
				$this->getAllowedContextTypes( $constraint )
			);
		} catch ( ConstraintParameterException $e ) {
			$problems[] = $e;
		}
		return $problems;
	}

	/**
	 * Check the constraint parameters of all constraints for the given property ID.
	 *
	 * @param PropertyId $propertyId
	 * @return ConstraintParameterException[][] first level indexed by constraint ID,
	 * second level like checkConstraintParametersOnConstraintId (but without possibility of null)
	 */
	public function checkConstraintParametersOnPropertyId( PropertyId $propertyId ) {
		$constraints = $this->constraintLookup->queryConstraintsForProperty( $propertyId );
		$result = [];

		foreach ( $constraints as $constraint ) {
			$problems = $this->checkCommonConstraintParameters( $constraint );

			if ( array_key_exists( $constraint->getConstraintTypeItemId(), $this->checkerMap ) ) {
				$checker = $this->checkerMap[$constraint->getConstraintTypeItemId()];
				$problems = array_merge( $problems, $checker->checkConstraintParameters( $constraint ) );
			}

			$result[$constraint->getConstraintId()] = $problems;
		}

		return $result;
	}

	/**
	 * Check the constraint parameters of the constraint with the given ID.
	 *
	 * @param string $constraintId
	 *
	 * @return ConstraintParameterException[]|null list of constraint parameter exceptions
	 * (empty means all parameters okay), or null if constraint is not found
	 */
	public function checkConstraintParametersOnConstraintId( $constraintId ) {
		$propertyId = $this->statementGuidParser->parse( $constraintId )->getEntityId();
		'@phan-var PropertyId $propertyId';
		$constraints = $this->constraintLookup->queryConstraintsForProperty( $propertyId );

		foreach ( $constraints as $constraint ) {
			if ( $constraint->getConstraintId() === $constraintId ) {
				$problems = $this->checkCommonConstraintParameters( $constraint );

				if ( array_key_exists( $constraint->getConstraintTypeItemId(), $this->checkerMap ) ) {
					$checker = $this->checkerMap[$constraint->getConstraintTypeItemId()];
					$problems = array_merge( $problems, $checker->checkConstraintParameters( $constraint ) );
				}

				return $problems;
			}
		}

		return null;
	}

	/**
	 * @param StatementListProvidingEntity $entity
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 * @param callable|null $defaultResultsPerContext optional function to pre-populate the check results
	 *
	 * @return CheckResult[]
	 */
	private function checkEveryStatement(
		StatementListProvidingEntity $entity,
		array $constraintIds = null,
		callable $defaultResultsPerContext = null
	) {
		$result = [];

		/** @var Statement $statement */
		foreach ( $entity->getStatements() as $statement ) {
			$result = array_merge( $result,
				$this->checkStatement(
					$entity,
					$statement,
					$constraintIds,
					$defaultResultsPerContext
				) );
		}

		return $result;
	}

	/**
	 * @param StatementListProvidingEntity $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 * @param callable|null $defaultResultsPerContext optional function to pre-populate the check results
	 *
	 * @return CheckResult[]
	 */
	private function checkStatement(
		StatementListProvidingEntity $entity,
		Statement $statement,
		array $constraintIds = null,
		callable $defaultResultsPerContext = null
	) {
		$result = [];

		$result = array_merge( $result,
			$this->checkConstraintsForMainSnak(
				$entity,
				$statement,
				$constraintIds,
				$defaultResultsPerContext
			) );

		if ( $this->checkQualifiers ) {
			$result = array_merge( $result,
				$this->checkConstraintsForQualifiers(
					$entity,
					$statement,
					$constraintIds,
					$defaultResultsPerContext
				) );
		}

		if ( $this->checkReferences ) {
			$result = array_merge( $result,
				$this->checkConstraintsForReferences(
					$entity,
					$statement,
					$constraintIds,
					$defaultResultsPerContext
				) );
		}

		return $result;
	}

	/**
	 * Get the constraints to actually check for a given property ID.
	 * If $constraintIds is not null, only check constraints with those constraint IDs,
	 * otherwise check all constraints for that property.
	 *
	 * @param PropertyId $propertyId
	 * @param string[]|null $constraintIds
	 * @return Constraint[]
	 */
	private function getConstraintsToUse( PropertyId $propertyId, array $constraintIds = null ) {
		$constraints = $this->constraintLookup->queryConstraintsForProperty( $propertyId );
		if ( $constraintIds !== null ) {
			$constraintsToUse = [];
			foreach ( $constraints as $constraint ) {
				if ( in_array( $constraint->getConstraintId(), $constraintIds ) ) {
					$constraintsToUse[] = $constraint;
				}
			}
			return $constraintsToUse;
		} else {
			return $constraints;
		}
	}

	/**
	 * @param StatementListProvidingEntity $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 * @param callable|null $defaultResults optional function to pre-populate the check results
	 *
	 * @return CheckResult[]
	 */
	private function checkConstraintsForMainSnak(
		StatementListProvidingEntity $entity,
		Statement $statement,
		array $constraintIds = null,
		callable $defaultResults = null
	) {
		$context = new MainSnakContext( $entity, $statement );
		$constraints = $this->getConstraintsToUse(
			$statement->getPropertyId(),
			$constraintIds
		);
		$result = $defaultResults !== null ? $defaultResults( $context ) : [];

		foreach ( $constraints as $constraint ) {
			$parameters = $constraint->getConstraintParameters();
			try {
				$exceptions = $this->constraintParameterParser->parseExceptionParameter( $parameters );
			} catch ( ConstraintParameterException $e ) {
				$result[] = new CheckResult( $context, $constraint, [], CheckResult::STATUS_BAD_PARAMETERS, $e->getViolationMessage() );
				continue;
			}

			if ( in_array( $entity->getId(), $exceptions ) ) {
				$message = new ViolationMessage( 'wbqc-violation-message-exception' );
				$result[] = new CheckResult( $context, $constraint, [], CheckResult::STATUS_EXCEPTION, $message );
				continue;
			}

			$result[] = $this->getCheckResultFor( $context, $constraint );
		}

		return $result;
	}

	/**
	 * @param StatementListProvidingEntity $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 * @param callable|null $defaultResultsPerContext optional function to pre-populate the check results
	 *
	 * @return CheckResult[]
	 */
	private function checkConstraintsForQualifiers(
		StatementListProvidingEntity $entity,
		Statement $statement,
		array $constraintIds = null,
		callable $defaultResultsPerContext = null
	) {
		$result = [];

		if ( in_array(
			$statement->getPropertyId()->getSerialization(),
			$this->propertiesWithViolatingQualifiers
		) ) {
			return $result;
		}

		foreach ( $statement->getQualifiers() as $qualifier ) {
			$qualifierContext = new QualifierContext( $entity, $statement, $qualifier );
			if ( $defaultResultsPerContext !== null ) {
				$result = array_merge( $result, $defaultResultsPerContext( $qualifierContext ) );
			}
			$qualifierConstraints = $this->getConstraintsToUse(
				$qualifierContext->getSnak()->getPropertyId(),
				$constraintIds
			);
			foreach ( $qualifierConstraints as $qualifierConstraint ) {
				$result[] = $this->getCheckResultFor( $qualifierContext, $qualifierConstraint );
			}
		}

		return $result;
	}

	/**
	 * @param StatementListProvidingEntity $entity
	 * @param Statement $statement
	 * @param string[]|null $constraintIds list of constraints to check (if null: all constraints)
	 * @param callable|null $defaultResultsPerContext optional function to pre-populate the check results
	 *
	 * @return CheckResult[]
	 */
	private function checkConstraintsForReferences(
		StatementListProvidingEntity $entity,
		Statement $statement,
		array $constraintIds = null,
		callable $defaultResultsPerContext = null
	) {
		$result = [];

		/** @var Reference $reference */
		foreach ( $statement->getReferences() as $reference ) {
			foreach ( $reference->getSnaks() as $snak ) {
				$referenceContext = new ReferenceContext(
					$entity, $statement, $reference, $snak
				);
				if ( $defaultResultsPerContext !== null ) {
					$result = array_merge( $result, $defaultResultsPerContext( $referenceContext ) );
				}
				$referenceConstraints = $this->getConstraintsToUse(
					$referenceContext->getSnak()->getPropertyId(),
					$constraintIds
				);
				foreach ( $referenceConstraints as $referenceConstraint ) {
					$result[] = $this->getCheckResultFor(
						$referenceContext,
						$referenceConstraint
					);
				}
			}
		}

		return $result;
	}

	/**
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws InvalidArgumentException
	 * @return CheckResult
	 */
	private function getCheckResultFor( Context $context, Constraint $constraint ) {
		if ( array_key_exists( $constraint->getConstraintTypeItemId(), $this->checkerMap ) ) {
			$checker = $this->checkerMap[$constraint->getConstraintTypeItemId()];
			$result = $this->handleScope( $checker, $context, $constraint );

			if ( $result !== null ) {
				$this->addMetadata( $context, $result );
				return $result;
			}

			$startTime = microtime( true );
			try {
				$result = $checker->checkConstraint( $context, $constraint );
			} catch ( ConstraintParameterException $e ) {
				$result = new CheckResult( $context, $constraint, [], CheckResult::STATUS_BAD_PARAMETERS, $e->getViolationMessage() );
			} catch ( SparqlHelperException $e ) {
				$message = new ViolationMessage( 'wbqc-violation-message-sparql-error' );
				$result = new CheckResult( $context, $constraint, [], CheckResult::STATUS_TODO, $message );
			}
			$endTime = microtime( true );

			$this->addMetadata( $context, $result );

			$this->downgradeResultStatus( $context, $result );

			$this->loggingHelper->logConstraintCheck(
				$context,
				$constraint,
				$result,
				get_class( $checker ),
				$endTime - $startTime,
				__METHOD__
			);

			return $result;
		} else {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_TODO, null );
		}
	}

	private function handleScope(
		ConstraintChecker $checker,
		Context $context,
		Constraint $constraint
	) {
		try {
			$checkedContextTypes = $this->constraintParameterParser->parseConstraintScopeParameter(
				$constraint->getConstraintParameters(),
				$constraint->getConstraintTypeItemId()
			);
		} catch ( ConstraintParameterException $e ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_BAD_PARAMETERS, $e->getViolationMessage() );
		}
		if ( $checkedContextTypes === null ) {
			$checkedContextTypes = $checker->getDefaultContextTypes();
		}
		if ( !in_array( $context->getType(), $checkedContextTypes ) ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_NOT_IN_SCOPE, null );
		}
		if ( $checker->getSupportedContextTypes()[$context->getType()] === CheckResult::STATUS_TODO ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_TODO, null );
		}
		return null;
	}

	private function addMetadata( Context $context, CheckResult $result ) {
		$result->withMetadata( Metadata::merge( [
			$result->getMetadata(),
			Metadata::ofDependencyMetadata( DependencyMetadata::merge( [
				DependencyMetadata::ofEntityId( $context->getEntity()->getId() ),
				DependencyMetadata::ofEntityId( $result->getConstraint()->getPropertyId() ),
			] ) ),
		] ) );
	}

	private function downgradeResultStatus( Context $context, CheckResult &$result ) {
		$constraint = $result->getConstraint();
		try {
			$constraintStatus = $this->constraintParameterParser
				->parseConstraintStatusParameter( $constraint->getConstraintParameters() );
		} catch ( ConstraintParameterException $e ) {
			$result = new CheckResult( $context, $constraint, [], CheckResult::STATUS_BAD_PARAMETERS, $e->getViolationMessage() );
			$constraintStatus = null;
		}
		if ( $constraintStatus === null ) {
			// downgrade violation to warning
			if ( $result->getStatus() === CheckResult::STATUS_VIOLATION ) {
				$result->setStatus( CheckResult::STATUS_WARNING );
			}
		} elseif ( $constraintStatus === 'suggestion' ) {
			// downgrade violation to suggestion
			if ( $result->getStatus() === CheckResult::STATUS_VIOLATION ) {
				$result->setStatus( CheckResult::STATUS_SUGGESTION );
			}
			$result->addParameter( 'constraint_status', $constraintStatus );
		} else {
			if ( $constraintStatus !== 'mandatory' ) {
				// @codeCoverageIgnoreStart
				throw new LogicException(
					"Unknown constraint status '$constraintStatus', " .
					"only known statuses are 'mandatory' and 'suggestion'"
				);
				// @codeCoverageIgnoreEnd
			}
			$result->addParameter( 'constraint_status', $constraintStatus );
		}
	}

	/**
	 * @param CheckResult[] $result
	 *
	 * @return CheckResult[]
	 */
	private function sortResult( array $result ) {
		if ( count( $result ) < 2 ) {
			return $result;
		}

		$sortFunction = function ( CheckResult $a, CheckResult $b ) {
			$orderNum = 0;
			$order = [
				CheckResult::STATUS_BAD_PARAMETERS => $orderNum++,
				CheckResult::STATUS_VIOLATION => $orderNum++,
				CheckResult::STATUS_WARNING => $orderNum++,
				CheckResult::STATUS_SUGGESTION => $orderNum++,
				CheckResult::STATUS_EXCEPTION => $orderNum++,
				CheckResult::STATUS_COMPLIANCE => $orderNum++,
				CheckResult::STATUS_DEPRECATED => $orderNum++,
				CheckResult::STATUS_NOT_IN_SCOPE => $orderNum++,
				'other' => $orderNum++,
			];

			$statusA = $a->getStatus();
			$statusB = $b->getStatus();

			$orderA = array_key_exists( $statusA, $order ) ? $order[ $statusA ] : $order[ 'other' ];
			$orderB = array_key_exists( $statusB, $order ) ? $order[ $statusB ] : $order[ 'other' ];

			if ( $orderA === $orderB ) {
				$cursorA = $a->getContextCursor();
				$cursorB = $b->getContextCursor();

				if ( $cursorA instanceof EntityContextCursor ) {
					return $cursorB instanceof EntityContextCursor ? 0 : -1;
				}
				if ( $cursorB instanceof EntityContextCursor ) {
					return $cursorA instanceof EntityContextCursor ? 0 : 1;
				}

				$pidA = $cursorA->getSnakPropertyId();
				$pidB = $cursorB->getSnakPropertyId();

				if ( $pidA === $pidB ) {
					$hashA = $cursorA->getSnakHash();
					$hashB = $cursorB->getSnakHash();

					if ( $hashA === $hashB ) {
						if ( $a instanceof NullResult ) {
							return $b instanceof NullResult ? 0 : -1;
						}
						if ( $b instanceof NullResult ) {
							return $a instanceof NullResult ? 0 : 1;
						}

						$typeA = $a->getConstraint()->getConstraintTypeItemId();
						$typeB = $b->getConstraint()->getConstraintTypeItemId();

						if ( $typeA == $typeB ) {
							return 0;
						} else {
							return ( $typeA > $typeB ) ? 1 : -1;
						}
					} else {
						return ( $hashA > $hashB ) ? 1 : -1;
					}
				} else {
					return ( $pidA > $pidB ) ? 1 : -1;
				}
			} else {
				return ( $orderA > $orderB ) ? 1 : -1;
			}
		};

		uasort( $result, $sortFunction );

		return $result;
	}

}
