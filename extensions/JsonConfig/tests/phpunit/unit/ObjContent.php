<?php
namespace JsonConfig\Tests;

use JsonConfig\JCObjContent;

class ObjContent extends JCObjContent {
	/** @var callable */
	private $validators;

	public function __construct( $data, $validators, $thorough, $isRootArray = false ) {
		$this->validators = $validators;
		$this->isRootArray = $isRootArray;
		$text = is_string( $data ) ? $data : json_encode( $data );
		parent::__construct( $text, 'JsonConfig.Test', $thorough );
	}

	public function validateContent() {
		if ( $this->validators ) {
			call_user_func( $this->validators, $this );
		}
	}
}
