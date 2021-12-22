<?php

namespace WikibaseQuality\ConstraintReport\Api;

use ApiBase;
use ApiMain;
use ApiResult;
use Config;
use IBufferingStatsdDataFactory;
use InvalidArgumentException;
use RequestContext;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Services\Statement\StatementGuidParser;
use Wikibase\DataModel\Services\Statement\StatementGuidParsingException;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Repo\Api\ApiErrorReporter;
use Wikibase\Repo\Api\ApiHelperFactory;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\DelegatingConstraintChecker;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Helper\ConstraintParameterException;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\ViolationMessageRenderer;

/**
 * API module that checks whether the parameters of a constraint statement are valid.
 *
 * @author Lucas Werkmeister
 * @license GPL-2.0-or-later
 */
class CheckConstraintParameters extends ApiBase {

	const PARAM_PROPERTY_ID = 'propertyid';
	const PARAM_CONSTRAINT_ID = 'constraintid';
	const KEY_STATUS = 'status';
	const STATUS_OKAY = 'okay';
	const STATUS_NOT_OKAY = 'not-okay';
	const STATUS_NOT_FOUND = 'not-found';
	const KEY_PROBLEMS = 'problems';
	const KEY_MESSAGE_HTML = 'message-html';

	/**
	 * @var ApiErrorReporter
	 */
	private $apiErrorReporter;

	/**
	 * @var DelegatingConstraintChecker
	 */
	private $delegatingConstraintChecker;

	/**
	 * @var ViolationMessageRenderer
	 */
	private $violationMessageRenderer;

	/**
	 * @var StatementGuidParser
	 */
	private $statementGuidParser;

	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $dataFactory;

	/**
	 * Creates new instance from global state.
	 */
	public static function newFromGlobalState(
		ApiMain $main,
		string $name,
		Config $config,
		IBufferingStatsdDataFactory $dataFactory,
		DelegatingConstraintChecker $delegatingConstraintChecker
	): self {
		$repo = WikibaseRepo::getDefaultInstance();
		$helperFactory = $repo->getApiHelperFactory( RequestContext::getMain() );
		$language = $repo->getUserLanguage();

		$entityIdHtmlLinkFormatterFactory = $repo->getEntityIdHtmlLinkFormatterFactory();
		$entityIdHtmlLinkFormatter = $entityIdHtmlLinkFormatterFactory
			->getEntityIdFormatter( $language );
		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $language->getCode() );
		$valueFormatterFactory = $repo->getValueFormatterFactory();
		$dataValueFormatter = $valueFormatterFactory
			->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions );
		$violationMessageRenderer = new MultilingualTextViolationMessageRenderer(
			$entityIdHtmlLinkFormatter,
			$dataValueFormatter,
			$main,
			$config
		);

		return new self(
			$main,
			$name,
			$helperFactory,
			$delegatingConstraintChecker,
			$violationMessageRenderer,
			$repo->getStatementGuidParser(),
			$dataFactory
		);
	}

	/**
	 * @param ApiMain $main
	 * @param string $name
	 * @param ApiHelperFactory $apiHelperFactory
	 * @param DelegatingConstraintChecker $delegatingConstraintChecker
	 * @param StatementGuidParser $statementGuidParser
	 * @param IBufferingStatsdDataFactory $dataFactory
	 */
	public function __construct(
		ApiMain $main,
		$name,
		ApiHelperFactory $apiHelperFactory,
		DelegatingConstraintChecker $delegatingConstraintChecker,
		ViolationMessageRenderer $violationMessageRenderer,
		StatementGuidParser $statementGuidParser,
		IBufferingStatsdDataFactory $dataFactory
	) {
		parent::__construct( $main, $name );

		$this->apiErrorReporter = $apiHelperFactory->getErrorReporter( $this );
		$this->delegatingConstraintChecker = $delegatingConstraintChecker;
		$this->violationMessageRenderer = $violationMessageRenderer;
		$this->statementGuidParser = $statementGuidParser;
		$this->dataFactory = $dataFactory;
	}

	public function execute() {
		$this->dataFactory->increment(
			'wikibase.quality.constraints.api.checkConstraintParameters.execute'
		);

		$params = $this->extractRequestParams();
		$result = $this->getResult();

		$propertyIds = $this->parsePropertyIds( $params[self::PARAM_PROPERTY_ID] );
		$constraintIds = $this->parseConstraintIds( $params[self::PARAM_CONSTRAINT_ID] );

		$this->checkPropertyIds( $propertyIds, $result );
		$this->checkConstraintIds( $constraintIds, $result );

		$result->addValue( null, 'success', 1 );
	}

	/**
	 * @param array|null $propertyIdSerializations
	 * @return PropertyId[]
	 */
	private function parsePropertyIds( $propertyIdSerializations ) {
		if ( $propertyIdSerializations === null ) {
			return [];
		} elseif ( empty( $propertyIdSerializations ) ) {
			$this->apiErrorReporter->dieError(
				'If ' . self::PARAM_PROPERTY_ID . ' is specified, it must be nonempty.',
				'no-data'
			);
		}

		return array_map(
			function( $propertyIdSerialization ) {
				try {
					return new PropertyId( $propertyIdSerialization );
				} catch ( InvalidArgumentException $e ) {
					$this->apiErrorReporter->dieError(
						"Invalid id: $propertyIdSerialization",
						'invalid-property-id',
						0, // default argument
						[ self::PARAM_PROPERTY_ID => $propertyIdSerialization ]
					);
				}
			},
			$propertyIdSerializations
		);
	}

	/**
	 * @param array|null $constraintIds
	 * @return string[]
	 */
	private function parseConstraintIds( $constraintIds ) {
		if ( $constraintIds === null ) {
			return [];
		} elseif ( empty( $constraintIds ) ) {
			$this->apiErrorReporter->dieError(
				'If ' . self::PARAM_CONSTRAINT_ID . ' is specified, it must be nonempty.',
				'no-data'
			);
		}

		return array_map(
			function( $constraintId ) {
				try {
					$propertyId = $this->statementGuidParser->parse( $constraintId )->getEntityId();
					if ( !$propertyId instanceof PropertyId ) {
						$this->apiErrorReporter->dieError(
							"Invalid property ID: {$propertyId->getSerialization()}",
							'invalid-property-id',
							0, // default argument
							[ self::PARAM_CONSTRAINT_ID => $constraintId ]
						);
					}
					return $constraintId;
				} catch ( StatementGuidParsingException $e ) {
					$this->apiErrorReporter->dieError(
						"Invalid statement GUID: $constraintId",
						'invalid-guid',
						0, // default argument
						[ self::PARAM_CONSTRAINT_ID => $constraintId ]
					);
				}
			},
			$constraintIds
		);
	}

	/**
	 * @param PropertyId[] $propertyIds
	 * @param ApiResult $result
	 */
	private function checkPropertyIds( array $propertyIds, ApiResult $result ) {
		foreach ( $propertyIds as $propertyId ) {
			$result->addArrayType( $this->getResultPathForPropertyId( $propertyId ), 'assoc' );
			$allConstraintExceptions = $this->delegatingConstraintChecker
				->checkConstraintParametersOnPropertyId( $propertyId );
			foreach ( $allConstraintExceptions as $constraintId => $constraintParameterExceptions ) {
				$this->addConstraintParameterExceptionsToResult(
					$constraintId,
					$constraintParameterExceptions,
					$result
				);
			}
		}
	}

	/**
	 * @param string[] $constraintIds
	 * @param ApiResult $result
	 */
	private function checkConstraintIds( array $constraintIds, ApiResult $result ) {
		foreach ( $constraintIds as $constraintId ) {
			if ( $result->getResultData( $this->getResultPathForConstraintId( $constraintId ) ) ) {
				// already checked as part of checkPropertyIds()
				continue;
			}
			$constraintParameterExceptions = $this->delegatingConstraintChecker
				->checkConstraintParametersOnConstraintId( $constraintId );
			$this->addConstraintParameterExceptionsToResult( $constraintId, $constraintParameterExceptions, $result );
		}
	}

	/**
	 * @param PropertyId $propertyId
	 * @return string[]
	 */
	private function getResultPathForPropertyId( PropertyId $propertyId ) {
		return [ $this->getModuleName(), $propertyId->getSerialization() ];
	}

	/**
	 * @param string $constraintId
	 * @return string[]
	 */
	private function getResultPathForConstraintId( $constraintId ) {
		$propertyId = $this->statementGuidParser->parse( $constraintId )->getEntityId();
		'@phan-var PropertyId $propertyId';
		return array_merge( $this->getResultPathForPropertyId( $propertyId ), [ $constraintId ] );
	}

	/**
	 * Add the ConstraintParameterExceptions for $constraintId to the API result.
	 *
	 * @param string $constraintId
	 * @param ConstraintParameterException[]|null $constraintParameterExceptions
	 * @param ApiResult $result
	 */
	private function addConstraintParameterExceptionsToResult(
		$constraintId,
		$constraintParameterExceptions,
		ApiResult $result
	) {
		$path = $this->getResultPathForConstraintId( $constraintId );
		if ( $constraintParameterExceptions === null ) {
			$result->addValue(
				$path,
				self::KEY_STATUS,
				self::STATUS_NOT_FOUND
			);
		} else {
			$result->addValue(
				$path,
				self::KEY_STATUS,
				empty( $constraintParameterExceptions ) ? self::STATUS_OKAY : self::STATUS_NOT_OKAY
			);
			$result->addValue(
				$path,
				self::KEY_PROBLEMS,
				array_map( [ $this, 'formatConstraintParameterException' ], $constraintParameterExceptions )
			);
		}
	}

	/**
	 * Convert a ConstraintParameterException to an array structure for the API response.
	 *
	 * @param ConstraintParameterException $e
	 * @return string[]
	 */
	private function formatConstraintParameterException( ConstraintParameterException $e ) {
		return [
			self::KEY_MESSAGE_HTML => $this->violationMessageRenderer->render(
				$e->getViolationMessage()
			),
		];
	}

	/**
	 * @return array[]
	 * @codeCoverageIgnore
	 */
	public function getAllowedParams() {
		return [
			self::PARAM_PROPERTY_ID => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true
			],
			self::PARAM_CONSTRAINT_ID => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true
			]
		];
	}

	/**
	 * @return string[]
	 * @codeCoverageIgnore
	 */
	public function getExamplesMessages() {
		return [
			'action=wbcheckconstraintparameters&propertyid=P247'
				=> 'apihelp-wbcheckconstraintparameters-example-propertyid-1',
			'action=wbcheckconstraintparameters&' .
			'constraintid=P247$0fe1711e-4c0f-82ce-3af0-830b721d0fba|' .
			'P225$cdc71e4a-47a0-12c5-dfb3-3f6fc0b6613f'
				=> 'apihelp-wbcheckconstraintparameters-example-constraintid-2',
		];
	}

}
