<?php

namespace JsonConfig;

use Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use ParserOptions;
use ParserOutput;

/**
 * This class is used in case when there is no custom view defined for JCContent object
 * @package JsonConfig
 */
class JCTabularContentView extends JCContentView {

	/**
	 * Render JCContent object as HTML
	 * Called from an override of AbstractContent::fillParserOutput()
	 *
	 * @param JCContent|JCTabularContent $content
	 * @param PageReference $page Context title for parsing
	 * @param int|null $revId Revision ID (for {{REVISIONID}})
	 * @param ParserOptions $options Parser options
	 * @param bool $generateHtml Whether or not to generate HTML
	 * @param ParserOutput &$output The output object to fill (reference).
	 * @return string
	 */
	public function valueToHtml(
		JCContent $content, PageReference $page, $revId,
		ParserOptions $options, $generateHtml, ParserOutput &$output
	) {
		// Use user's language, and split parser cache.  This should not have a big
		// impact because data namespace is rarely viewed, but viewing it localized
		// will be valuable
		$lang = $options->getUserLangObj();

		$infoClass = [ 'class' => 'mw-tabular-value-info' ];
		$titleHeaders = [];
		$nameHeaders = [];
		$typeHeaders = [];
		$rows = [];
		$headerAttributes = [];

		// Helper to add a class value to an array of attributes
		$addErr = static function ( array $attrs, $isValid ) {
			if ( !$isValid ) {
				$attrs['class'] = 'mw-tabular-error';
			}
			return $attrs;
		};

		// Helper to create a <tr> element out of an array of raw HTML values
		$makeRow = static function ( array $values, array $attrs = [] ) {
			return Html::rawElement( 'tr', $attrs, implode( '', $values ) );
		};

		$dataAttrs = [ 'class' => 'mw-tabular sortable' ];
		if ( !$content->getValidationData() || $content->getValidationData()->error() ) {
			$dataAttrs['class'] .= ' mw-tabular-error';
		}

		$flds = $content->getField( [ 'schema', 'fields' ] );
		if ( $flds && !$flds->error() ) {
			foreach ( $flds->getValue() as $fld ) {
				$name = $content->getField( 'name', $fld );
				$nameIsValid = $name && !$name->error();
				$name = $nameIsValid ? $name->getValue() : '';

				$title = $content->getField( 'title', $fld );
				$titleIsValid = $title && !$title->error();
				$title =
					$titleIsValid ? JCUtils::pickLocalizedString( $title->getValue(), $lang, $name )
						: '';

				$type = $content->getField( 'type', $fld );
				$typeIsValid = $type && !$type->error();
				$type = $typeIsValid ? $type->getValue() : '';

				$thAttr = [];
				if ( $nameIsValid ) {
					$thAttr['data-name'] = $name;
				}
				if ( $typeIsValid ) {
					$thAttr['data-type'] = $type;
					$headerAttributes[] = [ 'data-type' => $type ];
				} else {
					$headerAttributes[] = [];
				}

				$nameHeaders[] = Html::element( 'th', $addErr( $thAttr, $nameIsValid ), $name );

				$typeHeaders[] =
					Html::element( 'th', $addErr( $thAttr, $typeIsValid ),
						$typeIsValid ? wfMessage( 'jsonconfig-type-name-' . $type )->plain() : '' );

				$titleHeaders[] = Html::element( 'th', $addErr( $thAttr, $titleIsValid ), $title );
			}
		}

		$data = $content->getField( 'data' );
		if ( $data && !$data->error() ) {
			foreach ( $data->getValue() as $row ) {
				$rowIsValid = $row && $row instanceof JCValue && !$row->error();
				$row = ( $row && $row instanceof JCValue ) ? $row->getValue() : $row;
				if ( !is_array( $row ) ) {
					continue;
				}
				$vals = [];
				foreach ( $row as $column ) {
					$colIsValid = $column && $column instanceof JCValue && !$column->error();
					$column =
						( $column && $column instanceof JCValue ) ? $column->getValue() : $column;

					if ( count( $vals ) >= count( $headerAttributes ) ) {
						$header = [];
					} else {
						$header = $headerAttributes[ count( $vals ) ];
					}

					if ( !$colIsValid ) {
						$header['class'] = 'mw-tabular-error';
					}

					if ( is_object( $column ) ) {
						$valueSize = count( (array)$column );
						$column =
							htmlspecialchars( JCUtils::pickLocalizedString( $column, $lang ) ) .
							Html::element( 'span', $infoClass, "($valueSize)" );
					} elseif ( is_bool( $column ) ) {
						$column = $column ? '☑' : '☐';
					} elseif ( $column === null ) {
						$header['class'] = 'mw-tabular-value-null';
						$column = '';
					} else {
						$column = is_string( $column ) || is_numeric( $column )
							? htmlspecialchars( (string)$column )
							: '';
					}
					$vals[] = Html::rawElement( 'td', $header, $column );
				}
				$rows[] = $makeRow( $vals, $rowIsValid ? [] : [ 'class' => 'mw-tabular-error' ] );
			}
		}

		$html =
			$content->renderDescription( $lang ) .
			Html::rawElement( 'table', $dataAttrs, Html::rawElement( 'thead', [], implode( "\n", [
					$makeRow( $nameHeaders, [ 'class' => 'mw-tabular-row-key' ] ),
					$makeRow( $typeHeaders, [ 'class' => 'mw-tabular-row-type' ] ),
					$makeRow( $titleHeaders, [ 'class' => 'mw-tabular-row-name' ] ),
				] ) ) . Html::rawElement( 'tbody', [], implode( "\n", $rows ) ) ) .
			$content->renderSources(
				MediaWikiServices::getInstance()->getParserFactory()->getInstance(),
				$page,
				$revId,
				$options
			) . $content->renderLicense();

		return $html;
	}

	/**
	 * Returns default content for this object
	 * @param string $modelId
	 * @return string
	 */
	public function getDefault( $modelId ) {
		$licenseIntro = JCContentView::getLicenseIntro();

		return <<<EOT
{
    // !!!!! All comments will be automatically deleted on save !!!!!

    // Optional "description" field to describe this data
    "description": {"en": "table description"},

    // Optional "sources" field to describe the sources of the data.  Can use Wiki Markup
    "sources": "Copied from [http://example.com Example Data Source]",

    $licenseIntro

    // Mandatory fields schema. Each field must be an object with
    //   "name" being a valid identifier with consisting of letters, digits, and "_"
    //   "type" being one of the allowed types like "number", "string", "boolean", "localized"
    "schema": {
        "fields": [
            {
                "name": "header1",
                "type": "number",
                // Optional label for this field
                "title": {"en": "header 1"},
            },
            {
                "name": "header2",
                "type": "string",
                // Optional label for this field
                "title": {"en": "header 2"},
            }
        ]
    },

    // array of data, with each row being an array of values
    "data": [
        [ 42, "peace" ]
    ]
}
EOT;
	}
}
