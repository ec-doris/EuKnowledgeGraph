<?php

namespace JsonConfig\Tests;

use FormatJson;
use JsonConfig\JCLuaLibrary;
use JsonConfig\JCTabularContent;
use Language;
use MediaWikiTestCase;
use Scribunto_LuaLibraryBase;

/**
 * @package JsonConfigTests
 * @group JsonConfig
 * @covers \JsonConfig\JCTabularContent
 */
class JCTabularContentTest extends MediaWikiTestCase {

	public function getAnnotations() {
		// HACK phpunit can't handle @covers annotations referring to classes which cannot be loaded
		$annotations = parent::getAnnotations();
		if (
			$this->getName( false ) === 'testLuaTabDataReindexing'
			&& class_exists( Scribunto_LuaLibraryBase::class )
		) {
			unset( $annotations['method']['@coversNothing'] );
			$annotations['method']['@covers'][0] = '\JsonConfig\JCLuaLibrary::reindexTabularData';
		}
		return $annotations;
	}

	/**
	 * @dataProvider provideTestCases
	 * @param string $fileName
	 * @param bool $thorough
	 */
	public function testValidateContent( $fileName, $thorough ) {
		$file = __DIR__ . '/' . $fileName;
		$content = file_get_contents( $file );
		if ( $content === false ) {
			$this->fail( "Can't read file $file" );
		}
		$content = FormatJson::parse( $content );
		if ( !$content->isGood() ) {
			$this->fail( $content->getMessage()->plain() );
		}

		$content = $content->getValue();
		$testData = FormatJson::encode( $content->raw, false, FormatJson::ALL_OK );

		$c = new JCTabularContent( $testData, 'Tabular.JsonConfig', $thorough );
		if ( $c->isValid() ) {
			$this->assertTrue( true );
			foreach ( $content as $langCode => $expected ) {
				if ( $langCode == 'raw' ) {
					continue;
				} elseif ( $langCode == '_' ) {
					$actual = $c->getData();
				} else {
					$actual = $c->getLocalizedData( Language::factory( $langCode ) );
					unset( $actual->license->text );
					unset( $actual->license->url );
				}
				$this->assertEquals( $expected, $actual, "langCode='$langCode'" );
			}
		} else {
			$this->fail( $c->getStatus()->getMessage()->plain() );
		}
	}

	public function provideTestCases() {
		$result = [];

		foreach ( glob( __DIR__ . "/tabular-good/*.json" ) as $file ) {
			$file = substr( $file, strlen( __DIR__ ) + 1 );
			$result[] = [ $file, false ];
			$result[] = [ $file, true ];
		}

		return $result;
	}

	/**
	 * @dataProvider provideBadTestCases
	 * @param string $fileName
	 */
	public function testValidateBadContent( $fileName ) {
		$file = __DIR__ . '/' . $fileName;
		$content = file_get_contents( $file );
		if ( $content === false ) {
			$this->fail( "Can't read file $file" );
		}

		$c = new JCTabularContent( $content, 'Tabular.JsonConfig', true );
		$this->assertFalse( $c->isValid(), 'Validation unexpectedly succeeded' );
	}

	public function provideBadTestCases() {
		$result = [];

		foreach ( glob( __DIR__ . "/tabular-bad/*.json" ) as $file ) {
			$file = substr( $file, strlen( __DIR__ ) + 1 );
			$result[] = [ $file ];
		}

		return $result;
	}

	/**
	 * @dataProvider provideLuaReindexingTests
	 * @coversNothing
	 * @param int $fieldCount
	 * @param array $data
	 * @param array $expected
	 */
	public function testLuaTabDataReindexing( $fieldCount, $data, $expected ) {
		if ( !class_exists( Scribunto_LuaLibraryBase::class ) ) {
			$this->markTestSkipped( "Scribunto is required for this integration test" );
		}

		$value = (object)[ 'schema' => (object)[] ];
		$value->data = $data;
		$value->schema->fields = $fieldCount > 0 ? array_fill( 0, $fieldCount, (object)[] ) : [];
		JCLuaLibrary::reindexTabularData( $value );
		$this->assertEquals( $expected, $value->data );
		$this->assertEquals( $fieldCount > 0 ? range( 1, $fieldCount ) : [],
			array_keys( $value->schema->fields ) );
	}

	public function provideLuaReindexingTests() {
		return [
			// fieldCount, data, expected
			[ 0, [], [] ],
			[ 1, [], [] ],
			[
				1,
				[ [ "a" ] ],
				[ 1 => [ 1 => "a" ] ]
			],
			[
				2,
				[ [ 0, "a" ] ],
				[ 1 => [ 1 => 0, 2 => "a" ] ]
			],
			[
				2,
				[ [ 0, "a" ], [ - 1, "-" ] ],
				[ 1 => [ 1 => 0, 2 => "a" ], 2 => [ 1 => -1, 2 => "-" ] ]
			],
		];
	}
}
