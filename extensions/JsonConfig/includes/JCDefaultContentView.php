<?php

namespace JsonConfig;

use FormatJson;
use Html;
use ParserOptions;
use ParserOutput;
use Title;

/**
 * This class is used in case when there is no custom view defined for JCContent object
 * @package JsonConfig
 */
class JCDefaultContentView extends JCContentView {

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
	public function valueToHtml(
		JCContent $content, Title $title, $revId, ParserOptions $options, $generateHtml,
		 ParserOutput &$output
	) {
		return $this->renderValue( $content, $content->getData(), [] );
	}

	/**
	 * Returns default content for this object
	 * @param string $modelId
	 * @return string
	 */
	public function getDefault( $modelId ) {
		return "{\n}";
	}

	/**
	 * Constructs an HTML representation of a JSON object.
	 * @param JCContent $content
	 * @param mixed $data
	 * @param array $path path to this field
	 * @return string HTML.
	 */
	public function renderValue( JCContent $content, $data, array $path ) {
		$isList = $this->isList( $content, $data, $path );
		$isContainer = !$isList && $this->isContainer( $content, $data, $path );
		if ( $isList || $isContainer ) {
			$rows = [];
			$level = count( $path );
			foreach ( $data as $k => $v ) {
				$path[$level] = $k;
				if ( $isList ) {
					$rows[] = $this->renderValue( $content, $v, $path );
				} else {
					$rows[] = $this->renderTableRow( $content, $v, $path );
				}
			}
			if ( !$rows ) {
				$res = '';
			} elseif ( $isList ) {
				$res = implode( ', ', $rows );
				// HACK: The space prevents caller from treating it as a complex value
				if ( substr( $res, 0, 1 ) === '<' ) {
					$res = ' ' . $res;
				}
			} else {
				$res =
					Html::rawElement( 'table', [ 'class' => 'mw-jsonconfig' ],
						Html::rawElement( 'tbody', null, implode( "\n", $rows ) ) );
			}
		} else {
			if ( is_string( $data ) ) {
				$res = $data;
			} else {
				$res = FormatJson::encode( $data );
			}
			$res = htmlspecialchars( $res );
		}

		return $res;
	}

	/**
	 * Convert $data into a table row, returning <tr>...</tr> element.
	 * @param JCContent $content
	 * @param mixed $data - treats it as opaque - renderValue will know how to handle it
	 * @param array $path path to this field
	 * @return string
	 */
	public function renderTableRow( JCContent $content, $data, array $path ) {
		$content = $this->renderRowContent( $content, $data, $path );
		return Html::rawElement( 'tr', null, $content );
	}

	/**
	 * Converts $data into the content of the <tr>...</tr> tag.
	 * By default returns <th> with the last path element and <td> with the renderValue() result.
	 * @param JCContent $content
	 * @param mixed $data - treats it as opaque - renderValue will know how to handle it
	 * @param array $path
	 * @return string
	 */
	public function renderRowContent( JCContent $content, $data, array $path ) {
		$key = end( $path );
		$th = is_string( $key ) ? Html::element( 'th', null, $key ) : '';

		$tdVal = $this->renderValue( $content, $data, $path );
		// If html begins with a '<', its a complex object, and should not have a class
		$attribs = null;
		if ( substr( $tdVal, 0, 1 ) !== '<' ) {
			$attribs = [ 'class' => 'mw-jsonconfig-value' ];
		}
		$td = Html::rawElement( 'td', $attribs, $tdVal );

		return $th . $td;
	}

	/**
	 * Determine if data is a container and should be rendered as a complex structure
	 * @param JCContent $content
	 * @param array|object $data
	 * @param array $path
	 * @return bool
	 */
	public function isContainer(
		/** @noinspection PhpUnusedParameterInspection */
		JCContent $content, $data, array $path
	) {
		return is_array( $data ) || is_object( $data );
	}

	/**
	 * Determine if data is a special container that needs to be rendered as a comma-separated list.
	 * By default,
	 * @param JCContent $content
	 * @param array|object $data
	 * @param array $path
	 * @return bool
	 */
	public function isList(
		/** @noinspection PhpUnusedParameterInspection */
		JCContent $content, $data, array $path
	) {
		if ( !is_array( $data ) ) {
			return false;
		}
		foreach ( $data as $k => $v ) {
			if ( !is_int( $k ) || !( is_string( $v ) || is_numeric( $v ) ) ) {
				return false;
			}
		}
		return true;
	}
}
