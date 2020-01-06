<?php

namespace JsonConfig;

use FormatJson;
use TextContentHandler;

/**
 * JSON Json Config content handler
 *
 * @file
 * @ingroup Extensions
 * @ingroup JsonConfig
 *
 * @author Yuri Astrakhan <yurik@wikimedia.org>
 */
class JCContentHandler extends TextContentHandler {

	/**
	 * Internal format to force pretty-printed json serialization
	 */
	const CONTENT_FORMAT_JSON_PRETTY = 'application/json+pretty';

	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId ) {
		parent::__construct( $modelId, [ CONTENT_FORMAT_JSON, self::CONTENT_FORMAT_JSON_PRETTY ] );
	}

	/**
	 * Returns the content's text as-is.
	 *
	 * @param \Content|JCContent $content This is actually a Content object
	 * @param string|null $format
	 * @return mixed
	 */
	public function serializeContent( \Content $content, $format = null ) {
		$this->checkFormat( $format );
		$status = $content->getStatus();
		if ( $status->isGood() ) {
			$data = $content->getData(); // There are no errors, normalize data
		} elseif ( $status->isOK() ) {
			$data = $content->getRawData(); // JSON is valid, but the data has errors
		} else {
			return $content->getNativeData(); // Invalid JSON - can't do anything with it
		}

		return FormatJson::encode( $data, $format === self::CONTENT_FORMAT_JSON_PRETTY,
			FormatJson::ALL_OK );
	}

	/**
	 * @param \Content|JCContent $oldContent
	 * @param \Content|JCContent $myContent
	 * @param \Content|JCContent $yourContent
	 * @return bool|JCContent
	 */
	public function merge3( \Content $oldContent, \Content $myContent, \Content $yourContent ) {
		// Almost identical clone of the parent's merge3, except that we use pretty-printed merge,
		// thus allowing much more lenient line-based merging.

		$this->checkModelID( $oldContent->getModel() );
		$this->checkModelID( $myContent->getModel() );
		$this->checkModelID( $yourContent->getModel() );

		$format = self::CONTENT_FORMAT_JSON_PRETTY;

		$old = $this->serializeContent( $oldContent, $format );
		$mine = $this->serializeContent( $myContent, $format );
		$yours = $this->serializeContent( $yourContent, $format );

		$ok = wfMerge( $old, $mine, $yours, $result );

		if ( !$ok ) {
			return false;
		}

		if ( !$result ) {
			return $this->makeEmptyContent();
		}

		$mergedContent = $this->unserializeContent( $result, $format );

		return $mergedContent;
	}

	/**
	 * Returns the name of the diff engine to use.
	 *
	 * @since 1.21
	 *
	 * @return string
	 */
	protected function getDiffEngineClass() {
		return JCJsonDifferenceEngine::class;
	}

	/**
	 * Unserializes a JsonSchemaContent object.
	 *
	 * @param string $text Serialized form of the content
	 * @param null|string $format The format used for serialization
	 * @param bool $isSaving Perform extra validation
	 * @return JCContent the JsonSchemaContent object wrapping $text
	 */
	public function unserializeContent( $text, $format = null, $isSaving = true ) {
		$this->checkFormat( $format );
		$modelId = $this->getModelID();
		$class = JCSingleton::getContentClass( $modelId );
		return new $class( $text, $modelId, $isSaving );
	}

	/**
	 * Returns the name of the associated Content class, to
	 * be used when creating new objects. Override expected
	 * by subclasses.
	 *
	 * @return string
	 */
	protected function getContentClass() {
		$modelId = $this->getModelID();
		return JCSingleton::getContentClass( $modelId );
	}

	/**
	 * Creates an empty JsonSchemaContent object.
	 *
	 * @return JCContent
	 */
	public function makeEmptyContent() {
		// Each model could have its own default JSON value
		// null notifies that default should be used
		return $this->unserializeContent( null );
	}
}
