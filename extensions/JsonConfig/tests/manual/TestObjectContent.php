<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( -1 );
}

use JsonConfig\JCObjContent;

class TestObjectContent extends JCObjContent {
	public function __construct( $text, $modelId, $thorough ) {
		if ( $text === null ) {
			$text = <<<END
{
    "dict": {
        "string": "value",
        "int": 2,
        "double": 1.5,
        "dict2": {"string2":"value2"},
        "list": ["val1","val2"]
    },
    "list": ["a",2,null],
    "emptylist": [],
    "emptydict": {}
}
END;
		}
		parent::__construct( $text, $modelId, $thorough );
	}

	/**
	 * Derived classes must implement this method to perform custom validation
	 * using the check(...) calls
	 */
	public function validateContent() {
		// $this->check( 'emptylist', [] );
		// $this->check( 'emptydict', new stdClass() );
		// $this->check( [ 'dict', 'string' ], "" );
		$this->testOptional( [ 'emptydict', 'new1' ], new stdClass() );
		// $this->check( [ 'emptydict', 1 ], new stdClass() );
		$this->testOptional( [ 'emptydict', 'new1', 'blah', 2 ], new stdClass(),
			function () {
				return wfMessage( 'fail' );
			}
		);
		// $this->check( [ 'emptydict', 'newObj', 'newInt' ], 1 );
	}
}

$wgExtensionFunctions[] = function () {
	$o = new TestObjectContent( null, null, true );
	print_r( $o );
};
