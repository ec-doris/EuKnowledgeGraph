<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\StatementListProvidingEntity;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * A constraint check context for a qualifier of a statement.
 *
 * @license GPL-2.0-or-later
 */
class QualifierContext extends AbstractContext {

	/**
	 * @var Statement
	 */
	private $statement;

	public function __construct(
		StatementListProvidingEntity $entity,
		Statement $statement,
		Snak $snak
	) {
		parent::__construct( $entity, $snak );
		$this->statement = $statement;
	}

	public function getType() {
		return self::TYPE_QUALIFIER;
	}

	public function getSnakGroup( $groupingMode, array $separators = [] ) {
		$snaks = $this->statement->getQualifiers();
		return array_values( $snaks->getArrayCopy() );
	}

	public function getCursor() {
		return new QualifierContextCursor(
			$this->entity->getId()->getSerialization(),
			$this->statement->getPropertyId()->getSerialization(),
			$this->statement->getGuid(),
			$this->snak->getHash(),
			$this->snak->getPropertyId()->getSerialization()
		);
	}

}
