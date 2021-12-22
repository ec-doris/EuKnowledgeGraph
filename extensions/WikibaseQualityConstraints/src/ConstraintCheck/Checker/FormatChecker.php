<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use Config;
use DataValues\MonolingualTextValue;
use DataValues\StringValue;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\DummySparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\SparqlHelper;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;
use WikibaseQuality\ConstraintReport\Role;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class FormatChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	/**
	 * @var SparqlHelper
	 */
	private $sparqlHelper;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 * @param ConstraintParameterParser $constraintParameterParser
	 * @param Config $config
	 * @param SparqlHelper $sparqlHelper
	 */
	public function __construct(
		ConstraintParameterParser $constraintParameterParser,
		Config $config,
		SparqlHelper $sparqlHelper
	) {
		$this->constraintParameterParser = $constraintParameterParser;
		$this->config = $config;
		$this->sparqlHelper = $sparqlHelper;
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getSupportedContextTypes() {
		return [
			Context::TYPE_STATEMENT => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_QUALIFIER => CheckResult::STATUS_COMPLIANCE,
			Context::TYPE_REFERENCE => CheckResult::STATUS_COMPLIANCE,
		];
	}

	/**
	 * @codeCoverageIgnore This method is purely declarative.
	 */
	public function getDefaultContextTypes() {
		return [
			Context::TYPE_STATEMENT,
			Context::TYPE_QUALIFIER,
			Context::TYPE_REFERENCE,
		];
	}

	/**
	 * Checks 'Format' constraint.
	 *
	 * @param Context $context
	 * @param Constraint $constraint
	 *
	 * @throws ConstraintParameterException
	 * @return CheckResult
	 */
	public function checkConstraint( Context $context, Constraint $constraint ) {
		$parameters = [];
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();

		$format = $this->constraintParameterParser->parseFormatParameter(
			$constraintParameters,
			$constraintTypeItemId
		);
		$parameters['pattern'] = [ $format ];

		$syntaxClarifications = $this->constraintParameterParser->parseSyntaxClarificationParameter(
			$constraintParameters
		);

		$snak = $context->getSnak();

		if ( !$snak instanceof PropertyValueSnak ) {
			// nothing to check
			return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_COMPLIANCE );
		}

		$dataValue = $snak->getDataValue();

		/*
		 * error handling:
		 *   type of $dataValue for properties with 'Format' constraint has to be 'string' or 'monolingualtext'
		 */
		switch ( $dataValue->getType() ) {
			case 'string':
				$text = $dataValue->getValue();
				break;
			case 'monolingualtext':
				/** @var MonolingualTextValue $dataValue */
				'@phan-var MonolingualTextValue $dataValue';
				$text = $dataValue->getText();
				break;
			default:
				$message = ( new ViolationMessage( 'wbqc-violation-message-value-needed-of-types-2' ) )
					->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM )
					->withDataValueType( 'string' )
					->withDataValueType( 'monolingualtext' );
				return new CheckResult( $context, $constraint, $parameters, CheckResult::STATUS_VIOLATION, $message );
		}

		if (
			!( $this->sparqlHelper instanceof DummySparqlHelper ) &&
			$this->config->get( 'WBQualityConstraintsCheckFormatConstraint' )
		) {
			if ( $this->sparqlHelper->matchesRegularExpression( $text, $format ) ) {
				$message = null;
				$status = CheckResult::STATUS_COMPLIANCE;
			} else {
				$message = ( new ViolationMessage( 'wbqc-violation-message-format-clarification' ) )
					->withEntityId( $context->getSnak()->getPropertyId(), Role::CONSTRAINT_PROPERTY )
					->withDataValue( new StringValue( $text ), Role::OBJECT )
					->withInlineCode( $format, Role::CONSTRAINT_PARAMETER_VALUE )
					->withMultilingualText( $syntaxClarifications, Role::CONSTRAINT_PARAMETER_VALUE );
				$status = CheckResult::STATUS_VIOLATION;
			}
		} else {
			$message = ( new ViolationMessage( 'wbqc-violation-message-security-reason' ) )
				->withEntityId( new ItemId( $constraintTypeItemId ), Role::CONSTRAINT_TYPE_ITEM );
			$status = CheckResult::STATUS_TODO;
		}
		return new CheckResult( $context, $constraint, $parameters, $status, $message );
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parseFormatParameter(
				$constraintParameters,
				$constraintTypeItemId
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		try {
			$this->constraintParameterParser->parseSyntaxClarificationParameter(
				$constraintParameters
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
