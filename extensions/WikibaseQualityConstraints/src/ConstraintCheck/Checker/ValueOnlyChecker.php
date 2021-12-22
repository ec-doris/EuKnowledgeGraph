<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Checker;

use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\ConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class ValueOnlyChecker implements ConstraintChecker {

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
		if ( $context->getType() === Context::TYPE_STATEMENT ) {
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_COMPLIANCE );
		} else {
			$message = new ViolationMessage( 'wbqc-violation-message-valueOnly' );
			return new CheckResult( $context, $constraint, [], CheckResult::STATUS_VIOLATION, $message );
		}
	}

	public function checkConstraintParameters( Constraint $constraint ) {
		// no parameters
		return [];
	}

}
