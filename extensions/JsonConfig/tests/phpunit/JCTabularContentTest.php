<?php

namespace JsonConfig\Tests;

use FormatJson;
use JsonConfig\JCLuaLibrary;
use JsonConfig\JCTabularContent;
use MediaWikiIntegrationTestCase;
use Scribunto_LuaLibraryBase;

/**
 * @package JsonConfigTests
 * @group JsonConfig
 * @covers \JsonConfig\JCTabularContent
 */
class JCTabularContentTest extends MediaWikiIntegrationTestCase {

	public function getAnnotations(): array {
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
	 * @param string $file
	 * @param bool $thorough
	 */
	public function testValidateContent( $file, $thorough ) {
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
			$languageFactory = $this->getServiceContainer()->getLanguageFactory();
			foreach ( $content as $langCode => $expected ) {
				if ( $langCode == 'raw' ) {
					continue;
				} elseif ( $langCode == '_' ) {
					$actual = $c->getData();
				} else {
					$actual = $c->getLocalizedData( $languageFactory->getLanguage( $langCode ) );
					unset( $actual->license->text );
					unset( $actual->license->url );
				}
				$this->assertEquals( $expected, $actual, "langCode='$langCode'" );
			}
		} else {
			$this->fail( $c->getStatus()->getMessage()->plain() );
		}
	}

	public static function provideTestCases() {
		foreach ( glob( __DIR__ . "/tabular-good/*.json" ) as $file ) {
			yield [ $file, false ];
			yield [ $file, true ];
		}
	}

	/**
	 * @dataProvider provideBadTestCases
	 * @param string $file
	 */
	public function testValidateBadContent( $file ) {
		$content = file_get_contents( $file );
		if ( $content === false ) {
			$this->fail( "Can't read file $file" );
		}

		$c = new JCTabularContent( $content, 'Tabular.JsonConfig', true );
		$this->assertFalse( $c->isValid(), 'Validation unexpectedly succeeded' );
	}

	public static function provideBadTestCases() {
		foreach ( glob( __DIR__ . "/tabular-bad/*.json" ) as $file ) {
			yield [ $file ];
		}
	}

	/**
	 * @dataProvider provideLuaReindexingTests
	 * @coversNothing
	 * @param int $fieldCount
	 * @param array $data
	 * @param array $expected
	 */
	public function testLuaTabDataReindexing( int $fieldCount, array $data, array $expected ) {
		if ( !class_exists( Scribunto_LuaLibraryBase::class ) ) {
			$this->markTestSkipped( "Scribunto is required for this integration test" );
		}

		$value = (object)[
			'schema' => (object)[
				'fields' => array_fill( 0, $fieldCount, (object)[] ),
			],
			'data' => $data,
		];
		JCLuaLibrary::reindexTabularData( $value );
		$this->assertSame( $expected, $value->data );
		if ( !$fieldCount ) {
			$this->assertSame( [], $value->schema->fields );
		} else {
			$this->assertSame( range( 1, $fieldCount ), array_keys( $value->schema->fields ) );
		}
	}

	public static function provideLuaReindexingTests() {
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
				[ [ 0, "a" ], [ -1, "-" ] ],
				[ 1 => [ 1 => 0, 2 => "a" ], 2 => [ 1 => -1, 2 => "-" ] ]
			],
		];
	}
}
