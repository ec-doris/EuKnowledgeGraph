<?php

namespace JsonConfig;

use DifferenceEngine;
use MWException;

/**
 * @package JsonConfig
 */
class JCJsonDifferenceEngine extends DifferenceEngine {

	/**
	 * Generate a diff, no caching.
	 *
	 * Instead of the default implementation, compares pretty-printed JSON
	 *
	 * @param \Content|JCContent $old Old content
	 * @param \Content|JCContent $new New content
	 *
	 * @throws MWException If old or new content is not an instance of TextContent.
	 * @return bool|string
	 */
	public function generateContentDiffBody( \Content $old, \Content $new ) {
		if ( !( $old instanceof JCContent ) || !( $new instanceof JCContent ) ) {
			throw new MWException( __CLASS__ . " does not support diffing between " .
								   get_class( $old ) . " and " . get_class( $new ) );
		}

		$format = JCContentHandler::CONTENT_FORMAT_JSON_PRETTY;

		$oldText = $old->serialize( $format );
		$newText = $new->serialize( $format );

		return $this->generateTextDiffBody( $oldText, $newText );
	}
}
