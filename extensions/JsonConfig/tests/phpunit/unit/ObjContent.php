<?php

namespace JsonConfig\Tests;

use JsonConfig\JCObjContent;

class ObjContent extends JCObjContent {
	/** @var callable|null */
	private $validators;

	/**
	 * @param mixed $data
	 * @param callable|null $validators
	 * @param bool $thorough
	 * @param bool $isRootArray
	 */
	public function __construct( $data, $validators, $thorough, $isRootArray = false ) {
		$this->validators = $validators;
		$this->isRootArray = $isRootArray;
		$text = is_string( $data ) ? $data : json_encode( $data );
		parent::__construct( $text, 'JsonConfig.Test', $thorough );
	}

	public function validateContent() {
		if ( $this->validators ) {
			( $this->validators )( $this );
		}
	}
}
