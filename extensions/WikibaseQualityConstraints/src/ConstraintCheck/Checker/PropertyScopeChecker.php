<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterParser;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class PropertyScopeChecker implements ConstraintChecker {

	/**
	 * @var ConstraintParameterParser
	 */
	private $constraintParameterParser;

	public function __construct(
		ConstraintParameterParser $constraintParameterParser
	) {
		$this->constraintParameterParser = $constraintParameterParser;
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

	public function checkConstraint( Context $context, Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();

		$allowedContextTypes = $this->constraintParameterParser->parsePropertyScopeParameter(
			$constraintParameters,
			$constraintTypeItemId
		);

		if ( in_array( $context->getType(), $allowedContextTypes ) ) {
			return new CheckResult(
				$context->getCursor(),
				$constraint,
				[],
				CheckResult::STATUS_COMPLIANCE
			);
		} else {
			return new CheckResult(
				$context->getCursor(),
				$constraint,
				[],
				CheckResult::STATUS_VIOLATION,
				( new ViolationMessage( 'wbqc-violation-message-property-scope' ) )
					->withEntityId( $context->getSnak()->getPropertyId() )
					->withPropertyScope( $context->getType() )
					->withPropertyScopeList( $allowedContextTypes )
			);
		}
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		$constraintParameters = $constraint->getConstraintParameters();
		$constraintTypeItemId = $constraint->getConstraintTypeItemId();
		$exceptions = [];
		try {
			$this->constraintParameterParser->parsePropertyScopeParameter(
				$constraintParameters,
				$constraintTypeItemId
			);
		} catch ( ConstraintParameterException $e ) {
			$exceptions[] = $e;
		}
		return $exceptions;
	}

}
