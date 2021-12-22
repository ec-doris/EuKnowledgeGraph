<?php
namespace JsonConfig;

use ExtensionRegistry;
use FormatJson;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParserOutput;
use Title;

/**
 * @package JsonConfig
 */

class JCMapDataContentView extends JCContentView {

	/**
	 * Render JCContent object as HTML
	 * Called from an override of AbstractContent::fillParserOutput()
	 *
	 * Render JCContent object as HTML - replaces valueToHtml()
	 * @param JCContent|JCDataContent $content
	 * @param Title $title Context title for parsing
	 * @param int|null $revId Revision ID (for {{REVISIONID}})
	 * @param ParserOptions $options Parser options
	 * @param bool $generateHtml Whether or not to generate HTML
	 * @param ParserOutput &$output The output object to fill (reference).
	 * @return string
	 */
	public function valueToHtml(
		JCContent $content, Title $title, $revId, ParserOptions $options,
		$generateHtml, ParserOutput &$output
	) {
		$mainParser = MediaWikiServices::getInstance()->getParser();
		$parser = $mainParser->getFreshParser();

		$localizedData = $content->getLocalizedData( $options->getUserLangObj() );
		if ( $localizedData ) {
			$extReg = ExtensionRegistry::getInstance();

			// Test both because for some reason mTagHooks is not set during preview
			if ( isset( $mainParser->mTagHooks['mapframe'] ) ||
				$extReg->isLoaded( 'Kartographer' )
			) {
				$zoom = $content->getField( 'zoom' );
				$lat = $content->getField( 'latitude' );
				$lon = $content->getField( 'longitude' );
				if ( $zoom && $lat && $lon && !$zoom->error() && !$lat->error() &&
					!$lon->error()
				) {
					$zoom = $zoom->getValue();
					$lat = $lat->getValue();
					$lon = $lon->getValue();
				} else {
					$zoom = 3;
					$lat = $lon = 0;
				}

				$jsonText = FormatJson::encode( $localizedData->data, false, FormatJson::UTF8_OK );
				$text = <<<EOT
<mapframe width="100%" height="600" latitude="$lat" longitude="$lon" zoom="$zoom">
$jsonText
</mapframe>
EOT;
			} else {
				$jsonText = FormatJson::encode( $localizedData->data, true, FormatJson::UTF8_OK );
				if ( isset( $mainParser->mTagHooks['syntaxhighlight'] ) ||
					$extReg->isLoaded( 'SyntaxHighlight' )
				) {
					$text = "<syntaxhighlight lang=json>\n$jsonText\n</syntaxhighlight>";
				} else {
					$text = "<pre>\n$jsonText\n</pre>";
				}
			}
			$output = $parser->parse( $text, $title, $options, true, true, $revId );
		}

		return $content->renderDescription( $options->getUserLangObj() ) . '<br>' .
			$output->getRawText() . '<br clear=all>' .
			$content->renderSources( $parser, $title, $revId, $options ) .
			$content->renderLicense();
	}

	/**
	 * Returns default content for this object.
	 * The returned valued does not have to be valid JSON
	 * @param string $modelId
	 * @return string
	 */
	public function getDefault( $modelId ) {
		$licenseIntro = JCContentView::getLicenseIntro();

		return <<<EOT
{
    // !!!!! All comments will be automatically deleted on save !!!!!

    // Optional "description" field to describe this map
    "description": {"en": "map description"},

    // Optional "sources" field to describe the sources of the map.  Can use Wiki Markup
    "sources": "Copied from [http://example.com Example Map Source]",

    $licenseIntro

    "zoom": 3,
    "latitude": 0,
    "longitude": 0,
    "data": {

        ... GeoJSON ...

    }
}
EOT;
	}
}
