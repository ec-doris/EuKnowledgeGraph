<?php

namespace WikibaseQuality\ConstraintReport\ConstraintCheck\Result;

use DataValues\DataValue;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use WikibaseQuality\ConstraintReport\Constraint;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Cache\Metadata;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\Context;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Context\ContextCursor;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessage;

/**
 * Used for getting information about the result of a constraint check
 *
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class CheckResult {

	// Constants for statuses
	/**
	 * The statement satisfies the constraint.
	 */
	const STATUS_COMPLIANCE = 'compliance';
	/**
	 * The statement violates the constraint.
	 */
	const STATUS_VIOLATION = 'violation';
	/**
	 * The subject entity of the statement is a known exception to the constraint.
	 */
	const STATUS_EXCEPTION = 'exception';
	/**
	 * The constraint is not implemented.
	 */
	const STATUS_TODO = 'todo';
	/**
	 * The constraint parameters are broken.
	 */
	const STATUS_BAD_PARAMETERS = 'bad-parameters';
	/**
	 * The constraint has not been checked because the statement is deprecated.
	 */
	const STATUS_DEPRECATED = 'deprecated';
	/**
	 * The statement violates the constraint, but the constraint is not mandatory.
	 *
	 * DelegatingConstraintChecker downgrades violations to warnings automatically based on the constraint parameters;
	 * constraint checkers should not assign this status directly.
	 */
	const STATUS_WARNING = 'warning';
	/**
	 * The statement violates the constraint, but the constraint is just a suggestion.
	 *
	 * DelegatingConstraintChecker downgrades violations to suggestions automatically based on the constraint parameters;
	 * constraint checkers should not assign this status directly.
	 */
	const STATUS_SUGGESTION = 'suggestion';
	/**
	 * The constraint is not checked on this kind of snak
	 * (main snak, qualifier or reference),
	 * so the constraint check is skipped.
	 */
	const STATUS_NOT_IN_SCOPE = 'not-in-scope';
	/*
	 * When adding another status, don’t forget to also do the following:
	 * * define messages for it in i18n/:
	 *   * wbqc-constraintreport-status-
	 *   * apihelp-wbcheckconstraints-paramvalue-status-
	 * * declare a color for it in modules/SpecialConstraintReportPage.less
	 * * update $order in DelegatingConstraintChecker::sortResult
	 * * update PARAM_STATUS type in CheckConstraints::getAllowedParams
	 */

	/**
	 * @var Constraint
	 */
	private $constraint;

	/**
	 * @var ContextCursor
	 */
	private $contextCursor;

	/**
	 * @var array[]
	 * Includes arrays of ItemIds or PropertyIds or strings.
	 */
	private $parameters;

	/**
	 * @var string One of the self::STATUS_… constants
	 */
	private $status;

	/**
	 * @var ViolationMessage|null
	 */
	private $message;

	/**
	 * @var Metadata
	 */
	private $metadata;

	/**
	 * @var string|null
	 */
	private $snakType;

	/**
	 * @var DataValue|null
	 */
	private $dataValue;

	/**
	 * @param Context|ContextCursor $contextCursor
	 * @param Constraint $constraint
	 * @param array[] $parameters (string => string[]) parsed constraint parameters
	 * ($constraint->getParameters() contains the unparsed parameters)
	 * @param string $status One of the self::STATUS_… constants
	 * @param ViolationMessage|null $message
	 */
	public function __construct(
		$contextCursor,
		Constraint $constraint,
		array $parameters = [],
		$status = self::STATUS_TODO,
		ViolationMessage $message = null
	) {
		if ( $contextCursor instanceof Context ) {
			$context = $contextCursor;
			$this->contextCursor = $context->getCursor();
			$this->snakType = $context->getSnak()->getType();
			$mainSnak = $context->getSnak();
			if ( $mainSnak instanceof PropertyValueSnak ) {
				$this->dataValue = $mainSnak->getDataValue();
			} else {
				$this->dataValue = null;
			}
		} else {
			$this->contextCursor = $contextCursor;
			$this->snakType = null;
			$this->dataValue = null;
		}
		$this->constraint = $constraint;
		$this->parameters = $parameters;
		$this->status = $status;
		$this->message = $message;
		$this->metadata = Metadata::blank();
	}

	/**
	 * @return ContextCursor
	 */
	public function getContextCursor() {
		return $this->contextCursor;
	}

	/**
	 * @return string|null only available if the CheckResult was created from a full Context
	 */
	public function getSnakType() {
		return $this->snakType;
	}

	/**
	 * @return DataValue|null only available if the CheckResult was created from a full Context
	 */
	public function getDataValue() {
		return $this->dataValue;
	}

	/**
	 * @return Constraint
	 */
	public function getConstraint() {
		return $this->constraint;
	}

	/**
	 * @return string
	 */
	public function getConstraintId() {
		return $this->constraint->getConstraintId();
	}

	/**
	 * @return array[]
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * @param string $key
	 * @param string $value
	 */
	public function addParameter( $key, $value ) {
		$this->parameters[$key][] = $value;
	}

	/**
	 * @return string One of the self::STATUS_… constants
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @param string $status
	 */
	public function setStatus( $status ) {
		$this->status = $status;
	}

	/**
	 * @return ViolationMessage|null
	 */
	public function getMessage() {
		return $this->message;
	}

	/**
	 * @param Metadata $metadata
	 * @return self
	 */
	public function withMetadata( Metadata $metadata ) {
		$this->metadata = $metadata;
		return $this;
	}

	/**
	 * @return Metadata
	 */
	public function getMetadata() {
		return $this->metadata;
	}

}
