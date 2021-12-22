<?php

namespace WikibaseQuality\ConstraintReport\Html;

use Html;
use InvalidArgumentException;
use Wikimedia\Assert\Assert;

/**
 * @author BP2014N1
 * @license GPL-2.0-or-later
 */
class HtmlTableCellBuilder {

	/**
	 * Html content of the cell.
	 *
	 * @var string
	 */
	private $content;

	/**
	 * @var array
	 */
	private $attributes;

	/**
	 * Determines, whether the content is raw html or should be escaped.
	 *
	 * @var bool
	 */
	private $isRawContent;

	/**
	 * @param string $content HTML
	 * @param array $attributes
	 * @param bool $isRawContent
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $content, array $attributes = [], $isRawContent = false ) {
		Assert::parameterType( 'string', $content, '$content' );
		Assert::parameterType( 'boolean', $isRawContent, '$isRawContent' );

		$this->content = $content;
		$this->attributes = $attributes;
		$this->isRawContent = $isRawContent;
	}

	/**
	 * @return string HTML
	 */
	public function getContent() {
		return $this->content;
	}

	/**
	 * @return array
	 */
	public function getAttributes() {
		return $this->attributes;
	}

	/**
	 * @return string HTML
	 * @suppress SecurityCheck-DoubleEscaped
	 */
	public function toHtml() {
		if ( $this->isRawContent ) {
			return Html::rawElement( 'td', $this->getAttributes(), $this->content );
		} else {
			return Html::element( 'td', $this->getAttributes(), $this->content );
		}
	}

}
