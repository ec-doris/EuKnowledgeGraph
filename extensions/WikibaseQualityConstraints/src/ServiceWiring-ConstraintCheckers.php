<?php

namespace WikibaseQuality\ConstraintReport;

use MediaWiki\MediaWikiServices;
use MediaWiki\Site\MediaWikiPageNameNormalizer;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\AllowedUnitsChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CitationNeededChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\CommonsLinkChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ConflictsWithChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ContemporaryChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\DiffWithinRangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\EntityTypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\FormatChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\IntegerChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\InverseChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ItemChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MandatoryQualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\MultiValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\NoBoundsChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\NoneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\OneOfChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\PropertyScopeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifierChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\QualifiersChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\RangeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ReferenceChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleBestValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SingleValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\SymmetricChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TargetRequiredClaimChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\TypeChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\UniqueValueChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueOnlyChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Checker\ValueTypeChecker;

return [
	ConstraintCheckerServices::CONFLICTS_WITH_CHECKER => function( MediaWikiServices $services ) {
		return new ConflictsWithChecker(
			ConstraintsServices::getConstraintParameterParser( $services ),
			ConstraintsServices::getConnectionCheckerHelper( $services )
		);
	},

	ConstraintCheckerServices::ITEM_CHECKER => function( MediaWikiServices $services ) {
		return new ItemChecker(
			ConstraintsServices::getConstraintParameterParser( $services ),
			ConstraintsServices::getConnectionCheckerHelper( $services )
		);
	},

	ConstraintCheckerServices::TARGET_REQUIRED_CLAIM_CHECKER => function( MediaWikiServices $services ) {
		return new TargetRequiredClaimChecker(
			WikibaseServices::getEntityLookup( $services ),
			ConstraintsServices::getConstraintParameterParser( $services ),
			ConstraintsServices::getConnectionCheckerHelper( $services )
		);
	},

	ConstraintCheckerServices::SYMMETRIC_CHECKER => function( MediaWikiServices $services ) {
		return new SymmetricChecker(
			WikibaseServices::getEntityLookupWithoutCache( $services ),
			ConstraintsServices::getConnectionCheckerHelper( $services )
		);
	},

	ConstraintCheckerServices::INVERSE_CHECKER => function( MediaWikiServices $services ) {
		return new InverseChecker(
			WikibaseServices::getEntityLookup( $services ),
			ConstraintsServices::getConstraintParameterParser( $services ),
			ConstraintsServices::getConnectionCheckerHelper( $services )
		);
	},

	ConstraintCheckerServices::QUALIFIER_CHECKER => function( MediaWikiServices $services ) {
		return new QualifierChecker();
	},

	ConstraintCheckerServices::QUALIFIERS_CHECKER => function( MediaWikiServices $services ) {
		return new QualifiersChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::MANDATORY_QUALIFIERS_CHECKER => function( MediaWikiServices $services ) {
		return new MandatoryQualifiersChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::RANGE_CHECKER => function( MediaWikiServices $services ) {
		return new RangeChecker(
			WikibaseServices::getPropertyDataTypeLookup( $services ),
			ConstraintsServices::getConstraintParameterParser( $services ),
			ConstraintsServices::getRangeCheckerHelper( $services )
		);
	},

	ConstraintCheckerServices::DIFF_WITHIN_RANGE_CHECKER => function( MediaWikiServices $services ) {
		return new DiffWithinRangeChecker(
			ConstraintsServices::getConstraintParameterParser( $services ),
			ConstraintsServices::getRangeCheckerHelper( $services ),
			$services->getMainConfig()
		);
	},

	ConstraintCheckerServices::TYPE_CHECKER => function( MediaWikiServices $services ) {
		return new TypeChecker(
			ConstraintsServices::getConstraintParameterParser( $services ),
			ConstraintsServices::getTypeCheckerHelper( $services ),
			$services->getMainConfig()
		);
	},

	ConstraintCheckerServices::VALUE_TYPE_CHECKER => function( MediaWikiServices $services ) {
		return new ValueTypeChecker(
			WikibaseServices::getEntityLookup( $services ),
			ConstraintsServices::getConstraintParameterParser( $services ),
			ConstraintsServices::getTypeCheckerHelper( $services ),
			$services->getMainConfig()
		);
	},

	ConstraintCheckerServices::SINGLE_VALUE_CHECKER => function( MediaWikiServices $services ) {
		return new SingleValueChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::MULTI_VALUE_CHECKER => function( MediaWikiServices $services ) {
		return new MultiValueChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::UNIQUE_VALUE_CHECKER => function( MediaWikiServices $services ) {
		// TODO return a different, dummy implementation if SPARQL is not available
		return new UniqueValueChecker(
			ConstraintsServices::getSparqlHelper( $services )
		);
	},

	ConstraintCheckerServices::FORMAT_CHECKER => function( MediaWikiServices $services ) {
		// TODO return a different, dummy implementation if SPARQL is not available
		return new FormatChecker(
			ConstraintsServices::getConstraintParameterParser( $services ),
			$services->getMainConfig(),
			ConstraintsServices::getSparqlHelper( $services )
		);
	},

	ConstraintCheckerServices::COMMONS_LINK_CHECKER => function( MediaWikiServices $services ) {
		$pageNameNormalizer = new MediaWikiPageNameNormalizer();
		return new CommonsLinkChecker(
			ConstraintsServices::getConstraintParameterParser( $services ),
			$pageNameNormalizer
		);
	},

	ConstraintCheckerServices::ONE_OF_CHECKER => function( MediaWikiServices $services ) {
		return new OneOfChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::VALUE_ONLY_CHECKER => function( MediaWikiServices $services ) {
		return new ValueOnlyChecker();
	},

	ConstraintCheckerServices::REFERENCE_CHECKER => function( MediaWikiServices $services ) {
		return new ReferenceChecker();
	},

	ConstraintCheckerServices::NO_BOUNDS_CHECKER => function( MediaWikiServices $services ) {
		return new NoBoundsChecker();
	},

	ConstraintCheckerServices::ALLOWED_UNITS_CHECKER => function( MediaWikiServices $services ) {
		// TODO in the future, get UnitConverter from $services?
		$repo = WikibaseRepo::getDefaultInstance();
		$unitConverter = $repo->getUnitConverter();

		return new AllowedUnitsChecker(
			ConstraintsServices::getConstraintParameterParser( $services ),
			$unitConverter
		);
	},

	ConstraintCheckerServices::SINGLE_BEST_VALUE_CHECKER => function( MediaWikiServices $services ) {
		return new SingleBestValueChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::ENTITY_TYPE_CHECKER => function( MediaWikiServices $services ) {
		return new EntityTypeChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::NONE_OF_CHECKER => function( MediaWikiServices $services ) {
		return new NoneOfChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::INTEGER_CHECKER => function( MediaWikiServices $services ) {
		return new IntegerChecker();
	},

	ConstraintCheckerServices::CITATION_NEEDED_CHECKER => function( MediaWikiServices $services ) {
		return new CitationNeededChecker();
	},

	ConstraintCheckerServices::PROPERTY_SCOPE_CHECKER => function( MediaWikiServices $services ) {
		return new PropertyScopeChecker(
			ConstraintsServices::getConstraintParameterParser( $services )
		);
	},

	ConstraintCheckerServices::CONTEMPORARY_CHECKER => function( MediaWikiServices $services ) {
		return new ContemporaryChecker(
			WikibaseServices::getEntityLookup( $services ),
			ConstraintsServices::getRangeCheckerHelper( $services ),
			$services->getMainConfig()
		);
	},
];
