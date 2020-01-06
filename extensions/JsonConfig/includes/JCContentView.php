<?php

namespace JsonConfig;

use ParserOptions;
use ParserOutput;
use Title;

/**
 * This class is used as a way to specify how to edit/view JCContent object
 * To use it, set $wgJsonConfigModels[$modelId]['view'] = 'MyJCContentViewClass';
 * @package JsonConfig
 */
abstract class JCContentView {

	/**
	 * Render JCContent object as HTML
	 * Called from an override of AbstractContent::fillParserOutput()
	 *
	 * Render JCContent object as HTML - replaces valueToHtml()
	 * @param JCContent $content
	 * @param Title $title Context title for parsing
	 * @param int|null $revId Revision ID (for {{REVISIONID}})
	 * @param ParserOptions $options Parser options
	 * @param bool $generateHtml Whether or not to generate HTML
	 * @param ParserOutput &$output The output object to fill (reference).
	 * @return string
	 */
	abstract public function valueToHtml(
		JCContent $content, Title $title, $revId, ParserOptions $options, $generateHtml,
		ParserOutput &$output );

	/**
	 * Returns default content for this object.
	 * The returned valued does not have to be valid JSON
	 * @param string $modelId
	 * @return string
	 */
	abstract public function getDefault( $modelId );
}
