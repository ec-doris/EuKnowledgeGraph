<?php

namespace WikibaseQuality\ConstraintReport;

use Wikibase\DataModel\Entity\PropertyId;

/**
 * @license GPL-2.0-or-later
 */
interface ConstraintLookup {

	/**
	 * @param PropertyId $propertyId
	 *
	 * @return Constraint[]
	 */
	public function queryConstraintsForProperty( PropertyId $propertyId );

}
