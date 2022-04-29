<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Context;

use Wikibase\DataModel\Entity\StatementListProvidingEntity;
use Wikibase\DataModel\Reference;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\DataModel\Statement\Statement;

/**
 * A constraint check context for a snak of a reference of a statement.
 *
 * @license GPL-2.0-or-later
 */
class ReferenceContext extends AbstractContext {

	/**
	 * @var Statement
	 */
	private $statement;

	/**
	 * @var Reference
	 */
	private $reference;

	public function __construct(
		StatementListProvidingEntity $entity,
		Statement $statement,
		Reference $reference,
		Snak $snak
	) {
		parent::__construct( $entity, $snak );
		$this->statement = $statement;
		$this->reference = $reference;
	}

	public function getType() {
		return self::TYPE_REFERENCE;
	}

	public function getSnakGroup( $groupingMode, array $separators = [] ) {
		$snaks = $this->reference->getSnaks();
		return array_values( $snaks->getArrayCopy() );
	}

	public function getCursor() {
		return new ReferenceContextCursor(
			$this->entity->getId()->getSerialization(),
			$this->statement->getPropertyId()->getSerialization(),
			$this->statement->getGuid(),
			$this->snak->getHash(),
			$this->snak->getPropertyId()->getSerialization(),
			$this->reference->getHash()
		);
	}

}
