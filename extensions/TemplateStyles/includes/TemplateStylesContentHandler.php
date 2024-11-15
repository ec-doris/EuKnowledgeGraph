<?php

namespace MediaWiki\Extension\TemplateStyles;

/**
 * @file
 * @license GPL-2.0-or-later
 */

use CodeContentHandler;
use Content;
use CSSJanus;
use MediaWiki\Content\Renderer\ContentParseParams;
use MediaWiki\Content\ValidationParams;
use MediaWiki\MediaWikiServices;
use Message;
use ParserOutput;
use Status;
use StatusValue;
use Wikimedia\CSS\Parser\Parser as CSSParser;
use Wikimedia\CSS\Util as CSSUtil;

/**
 * Content handler for sanitized CSS
 */
class TemplateStylesContentHandler extends CodeContentHandler {

	/**
	 * @param string $modelId
	 */
	public function __construct( $modelId = 'sanitized-css' ) {
		parent::__construct( $modelId, [ CONTENT_FORMAT_CSS ] );
	}

	/**
	 * @inheritDoc
	 */
	public function validateSave(
		Content $content,
		ValidationParams $validationParams
	) {
		'@phan-var TemplateStylesContent $content';
		return $this->sanitize( $content, [ 'novalue' => true, 'severity' => 'fatal' ] );
	}

	/**
	 * @return string
	 */
	protected function getContentClass() {
		return TemplateStylesContent::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function fillParserOutput(
		Content $content,
		ContentParseParams $cpoParams,
		ParserOutput &$output
	) {
		'@phan-var TemplateStylesContent $content';
		$services = MediaWikiServices::getInstance();
		$page = $cpoParams->getPage();
		$parserOptions = $cpoParams->getParserOptions();

		// Inject our warnings into the resulting ParserOutput
		parent::fillParserOutput( $content, $cpoParams, $output );

		if ( $cpoParams->getGenerateHtml() ) {
			$html = "";
			$html .= "<pre class=\"mw-code mw-css\" dir=\"ltr\">\n";
			$html .= htmlspecialchars( $content->getNativeData(), ENT_NOQUOTES );
			$html .= "\n</pre>\n";
		} else {
			$html = '';
		}

		$output->clearWrapperDivClass();
		$output->setText( $html );

		$status = $this->sanitize( $content, [ 'novalue' => true, 'class' => $parserOptions->getWrapOutputClass() ] );
		if ( $status->getErrors() ) {
			foreach ( $status->getErrors() as $error ) {
				$output->addWarningMsg( $error['message'], $error['params'] );
			}
			$services->getTrackingCategories()->addTrackingCategory(
				$output,
				'templatestyles-stylesheet-error-category',
				$page
			);
		}
	}

	/**
	 * Handle errors from the CSS parser and/or sanitizer
	 * @param StatusValue $status Object to add errors to
	 * @param array[] $errors Error array
	 * @param string $severity Whether to consider errors as 'warning' or 'fatal'
	 */
	protected static function processErrors( StatusValue $status, array $errors, $severity ) {
		if ( $severity !== 'warning' && $severity !== 'fatal' ) {
			// @codeCoverageIgnoreStart
			throw new \InvalidArgumentException( 'Invalid $severity' );
			// @codeCoverageIgnoreEnd
		}
		foreach ( $errors as $error ) {
			$error[0] = 'templatestyles-error-' . $error[0];
			call_user_func_array( [ $status, $severity ], $error );
		}
	}

	/**
	 * Sanitize the content
	 * @param TemplateStylesContent $content
	 * @param array $options Options are:
	 *  - class: (string) Class to prefix selectors with
	 *  - extraWrapper: (string) Extra simple selector to prefix selectors with
	 *  - flip: (bool) Have CSSJanus flip the stylesheet.
	 *  - minify: (bool) Whether to minify. Default true.
	 *  - novalue: (bool) Don't bother returning the actual stylesheet, just
	 *    fill the Status with warnings.
	 *  - severity: (string) Whether to consider errors as 'warning' or 'fatal'
	 * @return Status
	 */
	public function sanitize( TemplateStylesContent $content, array $options = [] ) {
		$options += [
			'class' => false,
			'extraWrapper' => null,
			'flip' => false,
			'minify' => true,
			'novalue' => false,
			'severity' => 'warning',
		];

		$status = Status::newGood();

		$style = $content->getText();
		$maxSize = Hooks::getConfig()->get( 'TemplateStylesMaxStylesheetSize' );
		if ( $maxSize !== null && strlen( $style ) > $maxSize ) {
			$status->fatal(
				// Status::getWikiText() chokes on the Message::sizeParam if we
				// don't wrap it in a Message ourself.
				wfMessage( 'templatestyles-size-exceeded', $maxSize, Message::sizeParam( $maxSize ) )
			);
			return $status;
		}

		if ( $options['flip'] ) {
			$style = CSSJanus::transform( $style, true, false );
		}

		// Parse it, and collect any errors
		$cssParser = CSSParser::newFromString( $style );
		$stylesheet = $cssParser->parseStylesheet();
		self::processErrors( $status, $cssParser->getParseErrors(), $options['severity'] );

		// Sanitize it, and collect any errors
		$sanitizer = Hooks::getSanitizer(
			$options['class'] ?: 'mw-parser-output', $options['extraWrapper']
		);
		// Just in case
		$sanitizer->clearSanitizationErrors();
		$stylesheet = $sanitizer->sanitize( $stylesheet );
		self::processErrors( $status, $sanitizer->getSanitizationErrors(), $options['severity'] );
		$sanitizer->clearSanitizationErrors();

		// Stringify it while minifying
		$value = CSSUtil::stringify( $stylesheet, [ 'minify' => $options['minify'] ] );

		// Sanity check, don't allow "</style" if one somehow sneaks through the sanitizer
		if ( preg_match( '!</style!i', $value ) ) {
			$value = '';
			$status->fatal( 'templatestyles-end-tag-injection' );
		}

		if ( !$options['novalue'] ) {
			$status->value = $value;

			// Sanity check, don't allow raw U+007F if one somehow sneaks through the sanitizer
			$status->value = strtr( $status->value, [ "\x7f" => '�' ] );
		}

		return $status;
	}
}
