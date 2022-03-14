<?php

namespace WikibaseQuality\ConstraintReport\Api;

use ApiBase;
use ApiMain;
use Config;
use IBufferingStatsdDataFactory;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\DataModel\Services\Statement\StatementGuidValidator;
use Wikibase\Lib\Formatters\OutputFormatValueFormatterFactory;
use Wikibase\Lib\Formatters\SnakFormatter;
use Wikibase\Lib\Store\EntityTitleLookup;
use Wikibase\Repo\Api\ApiErrorReporter;
use Wikibase\Repo\Api\ApiHelperFactory;
use Wikibase\Repo\Api\ResultBuilder;
use Wikibase\Repo\EntityIdLabelFormatterFactory;
use Wikibase\Repo\WikibaseRepo;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Message\MultilingualTextViolationMessageRenderer;
use WikibaseQuality\ConstraintReport\ConstraintCheck\Result\CheckResult;

/**
 * API module that performs constraint check of entities, claims and constraint ID
 *
 * @author Olga Bode
 * @license GPL-2.0-or-later
 */
class CheckConstraints extends ApiBase {

	public const PARAM_ID = 'id';
	public const PARAM_CLAIM_ID = 'claimid';
	public const PARAM_CONSTRAINT_ID = 'constraintid';
	public const PARAM_STATUS = 'status';

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var StatementGuidValidator
	 */
	private $statementGuidValidator;

	/**
	 * @var ResultBuilder
	 */
	private $resultBuilder;

	/**
	 * @var ApiErrorReporter
	 */
	private $errorReporter;

	/**
	 * @var ResultsSource
	 */
	private $resultsSource;

	/**
	 * @var CheckResultsRenderer
	 */
	private $checkResultsRenderer;

	/**
	 * @var IBufferingStatsdDataFactory
	 */
	private $dataFactory;

	public static function factory(
		ApiMain $main,
		string $name,
		Config $config,
		IBufferingStatsdDataFactory $dataFactory,
		EntityIdParser $entityIdParser,
		EntityTitleLookup $entityTitleLookup,
		StatementGuidValidator $statementGuidValidator,
		OutputFormatValueFormatterFactory $valueFormatterFactory,
		ResultsSource $resultsSource
	): self {
		$repo = WikibaseRepo::getDefaultInstance();

		$language = WikibaseRepo::getUserLanguage();
		$formatterOptions = new FormatterOptions();
		$formatterOptions->setOption( SnakFormatter::OPT_LANG, $language->getCode() );
		$valueFormatter = $valueFormatterFactory->getValueFormatter( SnakFormatter::FORMAT_HTML, $formatterOptions );

		$entityIdHtmlLinkFormatterFactory = $repo->getEntityIdHtmlLinkFormatterFactory();
		$entityIdHtmlLinkFormatter = $entityIdHtmlLinkFormatterFactory->getEntityIdFormatter( $language );
		$entityIdLabelFormatterFactory = new EntityIdLabelFormatterFactory();
		$entityIdLabelFormatter = $entityIdLabelFormatterFactory->getEntityIdFormatter( $language );

		$checkResultsRenderer = new CheckResultsRenderer(
			$entityTitleLookup,
			$entityIdLabelFormatter,
			new MultilingualTextViolationMessageRenderer(
				$entityIdHtmlLinkFormatter,
				$valueFormatter,
				$main,
				$config
			)
		);

		return new self(
			$main,
			$name,
			$entityIdParser,
			$statementGuidValidator,
			$repo->getApiHelperFactory( $main->getContext() ),
			$resultsSource,
			$checkResultsRenderer,
			$dataFactory
		);
	}

	/**
	 * @param ApiMain $main
	 * @param string $name
	 * @param EntityIdParser $entityIdParser
	 * @param StatementGuidValidator $statementGuidValidator
	 * @param ApiHelperFactory $apiHelperFactory
	 * @param ResultsSource $resultsSource
	 * @param CheckResultsRenderer $checkResultsRenderer
	 * @param IBufferingStatsdDataFactory $dataFactory
	 */
	public function __construct(
		ApiMain $main,
		$name,
		EntityIdParser $entityIdParser,
		StatementGuidValidator $statementGuidValidator,
		ApiHelperFactory $apiHelperFactory,
		ResultsSource $resultsSource,
		CheckResultsRenderer $checkResultsRenderer,
		IBufferingStatsdDataFactory $dataFactory
	) {
		parent::__construct( $main, $name );
		$this->entityIdParser = $entityIdParser;
		$this->statementGuidValidator = $statementGuidValidator;
		$this->resultBuilder = $apiHelperFactory->getResultBuilder( $this );
		$this->errorReporter = $apiHelperFactory->getErrorReporter( $this );
		$this->resultsSource = $resultsSource;
		$this->checkResultsRenderer = $checkResultsRenderer;
		$this->dataFactory = $dataFactory;
	}

	/**
	 * Evaluates the parameters, runs the requested constraint check, and sets up the result
	 */
	public function execute() {
		$this->dataFactory->increment(
			'wikibase.quality.constraints.api.checkConstraints.execute'
		);

		$params = $this->extractRequestParams();

		$this->validateParameters( $params );
		$entityIds = $this->parseEntityIds( $params );
		$claimIds = $this->parseClaimIds( $params );
		$constraintIDs = $params[self::PARAM_CONSTRAINT_ID];
		$statuses = $params[self::PARAM_STATUS];

		$this->getResult()->addValue(
			null,
			$this->getModuleName(),
			$this->checkResultsRenderer->render(
				$this->resultsSource->getResults(
					$entityIds,
					$claimIds,
					$constraintIDs,
					$statuses
				)
			)->getArray()
		);
		$this->resultBuilder->markSuccess( 1 );
	}

	/**
	 * @param array $params
	 *
	 * @return EntityId[]
	 */
	private function parseEntityIds( array $params ) {
		$ids = $params[self::PARAM_ID];

		if ( $ids === null ) {
			return [];
		} elseif ( $ids === [] ) {
			$this->errorReporter->dieError(
				'If ' . self::PARAM_ID . ' is specified, it must be nonempty.', 'no-data' );
		}

		return array_map( function ( $id ) {
			try {
				return $this->entityIdParser->parse( $id );
			} catch ( EntityIdParsingException $e ) {
				$this->errorReporter->dieError(
					"Invalid id: $id", 'invalid-entity-id', 0, [ self::PARAM_ID => $id ] );
			}
		}, $ids );
	}

	/**
	 * @param array $params
	 *
	 * @return string[]
	 */
	private function parseClaimIds( array $params ) {
		$ids = $params[self::PARAM_CLAIM_ID];

		if ( $ids === null ) {
			return [];
		} elseif ( $ids === [] ) {
			$this->errorReporter->dieError(
				'If ' . self::PARAM_CLAIM_ID . ' is specified, it must be nonempty.', 'no-data' );
		}

		foreach ( $ids as $id ) {
			if ( !$this->statementGuidValidator->validate( $id ) ) {
				$this->errorReporter->dieError(
					"Invalid claim id: $id", 'invalid-guid', 0, [ self::PARAM_CLAIM_ID => $id ] );
			}
		}

		return $ids;
	}

	private function validateParameters( array $params ) {
		if ( $params[self::PARAM_CONSTRAINT_ID] !== null
			 && empty( $params[self::PARAM_CONSTRAINT_ID] )
		) {
			$paramConstraintId = self::PARAM_CONSTRAINT_ID;
			$this->errorReporter->dieError(
				"If $paramConstraintId is specified, it must be nonempty.", 'no-data' );
		}
		if ( $params[self::PARAM_ID] === null && $params[self::PARAM_CLAIM_ID] === null ) {
			$paramId = self::PARAM_ID;
			$paramClaimId = self::PARAM_CLAIM_ID;
			$this->errorReporter->dieError(
				"At least one of $paramId, $paramClaimId must be specified.", 'no-data' );
		}
		// contents of PARAM_ID and PARAM_CLAIM_ID are validated by parse{Entity,Claim}Ids()
	}

	/**
	 * @return array[]
	 * @codeCoverageIgnore
	 */
	public function getAllowedParams() {
		return [
			self::PARAM_ID => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			self::PARAM_CLAIM_ID => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			self::PARAM_CONSTRAINT_ID => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			],
			self::PARAM_STATUS => [
				ApiBase::PARAM_TYPE => [
					CheckResult::STATUS_COMPLIANCE,
					CheckResult::STATUS_VIOLATION,
					CheckResult::STATUS_WARNING,
					CheckResult::STATUS_SUGGESTION,
					CheckResult::STATUS_EXCEPTION,
					CheckResult::STATUS_NOT_IN_SCOPE,
					CheckResult::STATUS_DEPRECATED,
					CheckResult::STATUS_BAD_PARAMETERS,
					CheckResult::STATUS_TODO,
				],
				ApiBase::PARAM_ISMULTI => true,
				ApiBase::PARAM_ALL => true,
				ApiBase::PARAM_DFLT => implode( '|', CachingResultsSource::CACHED_STATUSES ),
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
		];
	}

	/**
	 * Returns usage examples for this module
	 *
	 * @return string[]
	 * @codeCoverageIgnore
	 */
	public function getExamplesMessages() {
		return [
			'action=wbcheckconstraints&id=Q5|Q42'
				=> 'apihelp-wbcheckconstraints-example-1',
			'action=wbcheckconstraints&claimid=q42%248419C20C-8EF8-4EC0-80D6-AF1CA55E7557'
				=> 'apihelp-wbcheckconstraints-example-2',
			'action=wbcheckconstraints&format=json&id=Q2&constraintid=P1082%24DA39C2DA-47DA-48FB-8A9A-DA80200FB2DB'
				=> 'apihelp-wbcheckconstraints-example-3',
		];
	}

}
