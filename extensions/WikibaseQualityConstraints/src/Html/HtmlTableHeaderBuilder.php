<?php

namespace WikibaseQuality\ConstraintReport\Html;

use Html;
use InvalidArgumentException;
use Wikimedia\Assert\Assert;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class HtmlTableHeaderBuilder {

	/**
	 * Html content of the header
	 *
	 * @var string
	 */
	private $content;

	/**
	 * Determines, whether the column should be sortable or not.
	 *
	 * @var bool
	 */
	private $isSortable;

	/**
	 * Determines, whether the content is raw html or should be escaped.
	 *
	 * @var bool
	 */
	private $isRawContent;

	/**
	 * @param string $content HTML
	 * @param bool $isSortable
	 * @param bool $isRawContent
	 * @param-taint $content escapes_html
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $content, $isSortable = false, $isRawContent = false ) {
		Assert::parameterType( 'string', $content, '$content' );
		Assert::parameterType( 'boolean', $isSortable, '$isSortable' );
		Assert::parameterType( 'boolean', $isRawContent, '$isRawContent' );

		$this->content = $content;
		$this->isSortable = $isSortable;
		$this->isRawContent = $isRawContent;
	}

	/**
	 * @return string
	 */
	public function getContent() {
		return $this->content;
	}

	/**
	 * @return bool
	 */
	public function getIsSortable() {
		return $this->isSortable;
	}

	/**
	 * Returns header as html.
	 *
	 * @return string HTML
	 */
	public function toHtml() {
		$attributes = [ 'role' => 'columnheader button' ];

		if ( !$this->isSortable ) {
			$attributes['class'] = 'unsortable';
		}

		if ( !$this->isRawContent ) {
			// @phan-suppress-next-line SecurityCheck-DoubleEscaped
			$content = htmlspecialchars( $this->content );
		} else {
			$content = $this->content;
		}

		return Html::rawElement( 'th', $attributes, $content );
	}

}
