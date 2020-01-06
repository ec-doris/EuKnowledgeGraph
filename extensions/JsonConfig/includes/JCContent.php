<?php

namespace JsonConfig;

use FormatJson;
use ParserOptions;
use ParserOutput;
use stdClass;
use Title;
use Status;

/**
 * Represents the content of a JSON Json Config article.
 * @file
 * @ingroup Extensions
 * @ingroup JsonConfig
 *
 * @author Yuri Astrakhan <yurik@wikimedia.org>,
 *   based on Ori Livneh <ori@wikimedia.org> extension schema
 */
class JCContent extends \TextContent {
	/** @var array */
	private $rawData = null;
	/** @var stdClass|array */
	protected $data = null;
	/** @var Status */
	private $status;
	/** @var bool */
	private $thorough;
	/** @var JCContentView|null contains an instance of the view class */
	private $view = null;

	/**
	 * @param string $text Json configuration. If null, default content will be inserted instead
	 * @param string $modelId
	 * @param bool $thorough True if extra validation should be performed
	 */
	public function __construct( $text, $modelId, $thorough ) {
		if ( $text === null ) {
			$text = $this->getView( $modelId )->getDefault( $modelId );
		}
		parent::__construct( $text, $modelId );
		$this->thorough = $thorough;
		$this->status = new Status();
		$this->parse();
	}

	/**
	 * Get validated data
	 * @return stdClass|stdClass[]
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Returns data after sanitization, suitable for third-party use
	 *
	 * @param stdClass|stdClass[] $data
	 * @return stdClass|stdClass[]
	 */
	public function getSafeData( $data ) {
		return $data;
	}

	/**
	 * Returns JSON object as resulted from parsing initial text,
	 * before any validation/modifications took place
	 * @return mixed
	 */
	public function getRawData() {
		return $this->rawData;
	}

	/**
	 * Get content status object
	 * @return Status
	 */
	public function getStatus() {
		return $this->status;
	}

	/**
	 * @return bool False if this configuration has parsing or validation errors
	 */
	public function isValid() {
		return $this->status->isGood();
	}

	public function isEmpty() {
		$text = trim( $this->getNativeData() );
		return $text === '' || $text === '{}';
	}

	/**
	 * Determines whether this content should be considered a "page" for statistics
	 * In our case, just making sure it's not empty or a redirect
	 * @param bool|null $hasLinks
	 * @return bool
	 */
	public function isCountable( $hasLinks = null ) {
		return !$this->isEmpty() && !$this->isRedirect();
	}

	/**
	 * Returns true if the text is in JSON format.
	 * @return bool
	 */
	public function isValidJson() {
		return $this->rawData !== null;
	}

	/**
	 * @return bool true if thorough validation may be needed -
	 *   e.g. rendering HTML or saving new value
	 */
	public function thorough() {
		return $this->thorough;
	}

	/**
	 * Override this method to perform additional data validation
	 * @param mixed $data
	 * @return mixed
	 */
	public function validate( $data ) {
		return $data;
	}

	/**
	 * Perform initial json parsing and validation
	 */
	private function parse() {
		$rawText = $this->getNativeData();
		$parseOpts = FormatJson::STRIP_COMMENTS + FormatJson::TRY_FIXING;
		$status = FormatJson::parse( $rawText, $parseOpts );
		if ( !$status->isOK() ) {
			$this->status = $status;
			return;
		}
		$data = $status->getValue();
		// @fixme: HACK - need a deep clone of the data
		// @fixme: but doing (object)(array)$data will re-encode empty [] as {}
		// @performance: re-encoding is likely faster than stripping comments in PHP twice
		$this->rawData = FormatJson::decode(
			FormatJson::encode( $data, FormatJson::ALL_OK ), true
		);
		$this->data = $this->validate( $data );
	}

	/**
	 * Beautifies JSON prior to save.
	 * @param Title $title Title
	 * @param \User $user User
	 * @param \ParserOptions $popts
	 * @return JCContent
	 */
	public function preSaveTransform( Title $title, \User $user, \ParserOptions $popts ) {
		if ( !$this->isValidJson() ) {
			return $this; // Invalid JSON - can't do anything with it
		}
		$formatted = FormatJson::encode( $this->getData(), false, FormatJson::ALL_OK );
		if ( $this->getNativeData() !== $formatted ) {
			return new static( $formatted, $this->getModel(), $this->thorough() );
		}
		return $this;
	}

	protected function fillParserOutput( Title $title, $revId, ParserOptions $options,
										 $generateHtml, ParserOutput &$output ) {
		if ( !$generateHtml ) {
			return;
		}

		$status = $this->getStatus();
		if ( !$status->isGood() ) {
			// Use user's language, and split parser cache.  This should not have a big
			// impact because data namespace is rarely viewed, but viewing it localized
			// will be valuable
			$lang = $options->getUserLangObj();
			$html = $status->getHTML( false, false, $lang );
		} else {
			$html = '';
		}

		if ( $status->isOK() ) {
			$html .= $this
				->getView( $this->getModel() )
				->valueToHtml( $this, $title, $revId, $options, $generateHtml, $output );
		}

		$output->setText( $html );
	}

	/**
	 * Get a view object for this content object
	 * @param string $modelId is required here because parent ctor might not have ran yet
	 * @return JCContentView
	 */
	protected function getView( $modelId ) {
		global $wgJsonConfigModels;
		$view = $this->view;
		if ( $view === null ) {
			$configModels = \ExtensionRegistry::getInstance()->getAttribute( 'JsonConfigModels' )
				+ $wgJsonConfigModels;
			if ( array_key_exists( $modelId, $configModels ) ) {
				$value = $configModels[$modelId];
				if ( is_array( $value ) && array_key_exists( 'view', $value ) ) {
					$class = $value['view'];
					$view = new $class();
				}
			}
			if ( $view === null ) {
				$view = $this->createDefaultView();
			}
			$this->view = $view;
		}
		return $view;
	}

	/**
	 * In case view is not associated with the model for this class, this function will instantiate
	 * a default. Override may instantiate a more appropriate view
	 * @return JCContentView
	 */
	protected function createDefaultView() {
		return new JCDefaultContentView();
	}
}
