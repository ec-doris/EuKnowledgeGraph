<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;

/**
 * @license GPL-2.0-or-later
 */
class ConstraintCheckerServices {

	const CONFLICTS_WITH_CHECKER = 'WBQC_ConflictsWithChecker';
	const ITEM_CHECKER = 'WBQC_ItemChecker';
	const TARGET_REQUIRED_CLAIM_CHECKER = 'WBQC_TargetRequiredClaimChecker';
	const SYMMETRIC_CHECKER = 'WBQC_SymmetricChecker';
	const INVERSE_CHECKER = 'WBQC_InverseChecker';
	const QUALIFIER_CHECKER = 'WBQC_QualifierChecker';
	const QUALIFIERS_CHECKER = 'WBQC_QualifiersChecker';
	const MANDATORY_QUALIFIERS_CHECKER = 'WBQC_MandatoryQualifiersChecker';
	const RANGE_CHECKER = 'WBQC_RangeChecker';
	const DIFF_WITHIN_RANGE_CHECKER = 'WBQC_DiffWithinRangeChecker';
	const TYPE_CHECKER = 'WBQC_TypeChecker';
	const VALUE_TYPE_CHECKER = 'WBQC_ValueTypeChecker';
	const SINGLE_VALUE_CHECKER = 'WBQC_SingleValueChecker';
	const MULTI_VALUE_CHECKER = 'WBQC_MultiValueChecker';
	const UNIQUE_VALUE_CHECKER = 'WBQC_UniqueValueChecker';
	const FORMAT_CHECKER = 'WBQC_FormatChecker';
	const COMMONS_LINK_CHECKER = 'WBQC_CommonsLinkChecker';
	const ONE_OF_CHECKER = 'WBQC_OneOfChecker';
	const VALUE_ONLY_CHECKER = 'WBQC_ValueOnlyChecker';
	const REFERENCE_CHECKER = 'WBQC_ReferenceChecker';
	const NO_BOUNDS_CHECKER = 'WBQC_NoBoundsChecker';
	const ALLOWED_UNITS_CHECKER = 'WBQC_AllowedUnitsChecker';
	const SINGLE_BEST_VALUE_CHECKER = 'WBQC_SingleBestValueChecker';
	const ENTITY_TYPE_CHECKER = 'WBQC_EntityTypeChecker';
	const NONE_OF_CHECKER = 'WBQC_NoneOfChecker';
	const INTEGER_CHECKER = 'WBQC_IntegerChecker';
	const CITATION_NEEDED_CHECKER = 'WBQC_CitationNeededChecker';
	const PROPERTY_SCOPE_CHECKER = 'WBQC_PropertyScopeChecker';
	const CONTEMPORARY_CHECKER = 'WBQC_ContemporaryChecker';

	private static function getService( ?MediaWikiServices $services, $name ) {
		if ( $services === null ) {
			$services = MediaWikiServices::getInstance();
		}
		return $services->getService( $name );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getConflictsWithChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CONFLICTS_WITH_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getItemChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::ITEM_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getTargetRequiredClaimChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::TARGET_REQUIRED_CLAIM_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getSymmetricChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::SYMMETRIC_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getInverseChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::INVERSE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getQualifierChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::QUALIFIER_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getQualifiersChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::QUALIFIERS_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getMandatoryQualifiersChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::MANDATORY_QUALIFIERS_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getRangeChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::RANGE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getDiffWithinRangeChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::DIFF_WITHIN_RANGE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getTypeChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::TYPE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getValueTypeChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::VALUE_TYPE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getSingleValueChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::SINGLE_VALUE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getMultiValueChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::MULTI_VALUE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getUniqueValueChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::UNIQUE_VALUE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getFormatChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::FORMAT_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getCommonsLinkChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::COMMONS_LINK_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getOneOfChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::ONE_OF_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getValueOnlyChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::VALUE_ONLY_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getReferenceChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::REFERENCE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getNoBoundsChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::NO_BOUNDS_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getAllowedUnitsChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::ALLOWED_UNITS_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getSingleBestValueChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::SINGLE_BEST_VALUE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getEntityTypeChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::ENTITY_TYPE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getNoneOfChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::NONE_OF_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getIntegerChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::INTEGER_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getCitationNeededChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CITATION_NEEDED_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getPropertyScopeChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::PROPERTY_SCOPE_CHECKER );
	}

	/**
	 * @param MediaWikiServices|null $services
	 * @return ConstraintChecker
	 */
	public static function getContemporaryChecker( MediaWikiServices $services = null ) {
		return self::getService( $services, self::CONTEMPORARY_CHECKER );
	}

}
