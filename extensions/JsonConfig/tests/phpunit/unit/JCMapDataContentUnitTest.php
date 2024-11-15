<?php

namespace JsonConfig\Tests;

use JsonConfig\JCMapDataContent;
use JsonConfig\JCValue;
use MediaWikiUnitTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group JsonConfig
 * @covers \JsonConfig\JCMapDataContent
 */
class JCMapDataContentUnitTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideLocalizedDataToValidate
	 */
	public function testLocalizedPropertyValidation( $value, bool $expected ) {
		/** @var JCMapDataContent $content */
		$content = TestingAccessWrapper::newFromClass( JCMapDataContent::class );
		$validator = $content->isValidData();

		$value = new JCValue( 0, $value );

		$this->assertSame( $expected, $validator( $value, [] ) );
		$this->assertSame( $expected, !$value->error() );
	}

	public static function provideLocalizedDataToValidate() {
		return [
			[ null, false ],
			[ [], true ],
			[ [ (object)[] ], true ],
			[ (object)[], true ],
			[ [ (object)[ 'properties' => (object)[ 'title' => '' ] ] ], true ],
			[ [ (object)[ 'properties' => (object)[ 'title' => null ] ] ], false ],
			[ (object)[ 'properties' => (object)[ 'title' => '' ] ], true ],
			[ (object)[ 'properties' => (object)[ 'title' => null ] ], false ],
		];
	}

}
