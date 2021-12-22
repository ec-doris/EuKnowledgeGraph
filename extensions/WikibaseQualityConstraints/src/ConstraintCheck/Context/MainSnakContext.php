<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use LogicException;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Entity\StatementListProvidingEntity;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Snak\SnakList;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementList;

/**
 * A constraint check context for the main snak of a statement.
 *
 * @license GPL-2.0-or-later
 */
class MainSnakContext extends AbstractContext {

	/**
	 * @var Statement
	 */
	private $statement;

	public function __construct( StatementListProvidingEntity $entity, Statement $statement ) {
		parent::__construct( $entity, $statement->getMainSnak() );

		$this->statement = $statement;
	}

	public function getType() {
		return self::TYPE_STATEMENT;
	}

	public function getSnakRank() {
		return $this->statement->getRank();
	}

	public function getSnakStatement() {
		return $this->statement;
	}

	public function getSnakGroup( $groupingMode, array $separators = [] ) {
		/** @var StatementList $statements */
		$statements = $this->entity->getStatements();
		switch ( $groupingMode ) {
			case Context::GROUP_NON_DEPRECATED:
				$statements = $statements->getByRank( [
					Statement::RANK_NORMAL,
					Statement::RANK_PREFERRED,
				] );
				break;
			case Context::GROUP_BEST_RANK:
				$statements = $this->getBestStatementsPerPropertyId( $statements );
				break;
			default:
				throw new LogicException( 'Unknown $groupingMode ' . $groupingMode );
		}
		return $this->getStatementsWithSameQualifiersForProperties(
			$this->statement,
			$statements,
			$separators
		)->getMainSnaks();
	}

	private function getBestStatementsPerPropertyId( StatementList $statements ) {
		$allBestStatements = new StatementList();
		foreach ( $statements->getPropertyIds() as $propertyId ) {
			$bestStatements = $statements->getByPropertyId( $propertyId )
				->getBestStatements();
			foreach ( $bestStatements as $bestStatement ) {
				$allBestStatements->addStatement( $bestStatement );
			}
		}
		return $allBestStatements;
	}

	/**
	 * Returns the statements of a statement list
	 * which for a set of propert IDs have the same qualifiers as a certain statement.
	 * “unknown value” qualifiers are considered different from each other.
	 *
	 * @param Statement $currentStatement
	 * @param StatementList $allStatements
	 * @param PropertyId[] $qualifierPropertyIds
	 * @return StatementList
	 */
	private function getStatementsWithSameQualifiersForProperties(
		Statement $currentStatement,
		StatementList $allStatements,
		array $qualifierPropertyIds
	) {
		$similarStatements = new StatementList();
		foreach ( $allStatements as $statement ) {
			if ( $statement === $currentStatement ) {
				// if the statement has an “unknown value” qualifier,
				// it might be considered different from itself,
				// so add it explicitly to ensure it’s always included
				$similarStatements->addStatement( $statement );
				continue;
			}
			foreach ( $qualifierPropertyIds as $qualifierPropertyId ) {
				if ( !$this->haveSameQualifiers( $currentStatement, $statement, $qualifierPropertyId ) ) {
					continue 2;
				}
			}
			$similarStatements->addStatement( $statement );
		}
		return $similarStatements;
	}

	/**
	 * Tests whether two statements have the same qualifiers with a certain property ID.
	 * “unknown value” qualifiers are considered different from each other.
	 *
	 * @param Statement $s1
	 * @param Statement $s2
	 * @param PropertyId $propertyId
	 * @return bool
	 */
	private function haveSameQualifiers( Statement $s1, Statement $s2, PropertyId $propertyId ) {
		$q1 = $this->getSnaksWithPropertyId( $s1->getQualifiers(), $propertyId );
		$q2 = $this->getSnaksWithPropertyId( $s2->getQualifiers(), $propertyId );

		if ( $q1->count() !== $q2->count() ) {
			return false;
		}

		foreach ( $q1 as $qualifier ) {
			switch ( $qualifier->getType() ) {
				case 'value':
				case 'novalue':
					if ( !$q2->hasSnak( $qualifier ) ) {
						return false;
					}
					break;
				case 'somevalue':
					return false; // all “unknown value”s are considered different from each other
			}
		}

		// a SnakList cannot contain the same snak more than once,
		// so if every snak of q1 is also in q2 and their cardinality is identical,
		// then they must be entirely identical
		return true;
	}

	/**
	 * Returns the snaks of the given snak list with the specified property ID.
	 *
	 * @param SnakList $allSnaks
	 * @param PropertyId $propertyId
	 * @return SnakList
	 */
	private function getSnaksWithPropertyId( SnakList $allSnaks, PropertyId $propertyId ) {
		$snaks = new SnakList();
		/** @var Snak $snak */
		foreach ( $allSnaks as $snak ) {
			if ( $snak->getPropertyId()->equals( $propertyId ) ) {
				$snaks->addSnak( $snak );
			}
		}
		return $snaks;
	}

	public function getCursor() {
		return new MainSnakContextCursor(
			$this->entity->getId()->getSerialization(),
			$this->statement->getPropertyId()->getSerialization(),
			$this->statement->getGuid(),
			$this->statement->getMainSnak()->getHash()
		);
	}

}
