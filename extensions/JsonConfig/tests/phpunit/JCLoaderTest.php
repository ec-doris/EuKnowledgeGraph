<?php
namespace JsonConfig\Tests;

use JsonConfig\JCSingleton;
use MediaWikiTestCase;
use stdClass;

/**
 * @package JsonConfigTests
 * @group JsonConfig
 * @covers \JsonConfig\JCSingleton
*/
class JCLoaderTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideConfigs
	 * @param $config
	 * @param bool|stdClass $expected
	 */
	public function testConfigLoad( $config, $expected = false ) {
		$actual =
			JCSingleton::parseConfiguration(
				[ 'modelForNs0', 'modelForNs1' ],
				[ 'globalModel' => 'something' ],
				[ 'test.model' => $config ],
				[ 'test.model' => null, 'globalModel' => 'conflicts with JsonConfig models' ],
				$expected !== false );

		$this->assertArrayEquals( $expected === false ? [ [], [] ] : $expected, $actual, false, true );
	}

	public function provideConfigs() {
		return [
			[
				[], self::getExpectedObj( [], true, false )
			],
			[
				[ 'pattern' => '/\.json$/' ],
				self::getExpectedObj( [ 'pattern' => '/\.json$/' ], true, false )
			],
			[
				[ 'namespace' => 400, 'nsName' => 'abc' ],
				self::getExpectedObj( [ 'namespace' => 400, 'nsName' => 'abc' ], true, false, 'abc' )
			],
			[
				[ 'namespace' => 400, 'nsName' => 'abc' ],
				self::getExpectedObj( [ 'namespace' => 400, 'nsName' => 'abc' ], true, false, 'abc' )
			],
			[
				[ 'isLocal' => false, 'remote' => [ 'url' => 'host' ] ],
				self::getExpectedObj( [ 'isLocal' => false ], false, true )
			],
			[
				[ 'isLocal' => false, 'remote' => 'host' ],
				self::getExpectedObj( [ 'isLocal' => false ], false, true )
			],

			// errors
			[ 'non-config' ],
			[ [ 'model' => 'missingClass' ] ],
			[ [ 'model' => 'globalModel' ] ],
			[ [ 'namespace' => 'a' ] ],
			[ [ 'namespace' => 13 ] ],
			[ [ 'namespace' => 0 ] ],
			[ [ 'isLocal' => false, 'remote' => 42 ] ],
			[ [ 'isLocal' => false, 'remote' => [] ] ],
			[ [ 'isLocal' => true, 'store' => 'aa' ] ],
			[ [ 'nsTalk' => 42 ] ],
			[ [ 'pattern' => 42 ] ],
			[ [ 'isLocal' => 42 ] ],
			[ [ 'cacheExp' => 'a' ] ],
			[ [ 'cacheKey' => 42 ] ],
			[ [ 'flaggedRevs' => 42 ] ],
			[ [ 'isLocal' => false, 'remote' => [ 'url' => 42 ] ] ],
			[ [ 'isLocal' => false, 'remote' => [ 'username' => 42 ] ] ],
			[ [ 'isLocal' => false, 'remote' => [ 'password' => 42 ] ] ],
			[ [ 'isLocal' => false, 'store' => [ 'cacheNewValue' => 42 ] ] ],
			[ [ 'isLocal' => false, 'store' => [ 'notifyUrl' => 42 ] ] ],
			[ [ 'isLocal' => false, 'store' => [ 'notifyUsername' => 42 ] ] ],
			[ [ 'isLocal' => false, 'store' => [ 'notifyPassword' => 42 ] ] ],
		];
	}

	/**
	 * Generate expected configuration object, merging in test customizations
	 * @param array $obj
	 * @param array|false $store
	 * @param array|false $remote
	 * @param string|bool $nsName
	 * @param string|bool $nsTalk
	 * @return array
	 */
	private static function getExpectedObj( $obj, $store, $remote, $nsName = false, $nsTalk = false ) {
		$result = (object)array_merge( [
			'model' => 'test.model',
			'isLocal' => true,
			'license' => false,
			'namespace' => 482,
			'pattern' => '',
			'cacheExp' => 86400,
			'cacheKey' => '',
			'flaggedRevs' => false,
			'store' => null,
			'remote' => null,
		], $obj );
		if ( $store !== false ) {
			$result->store = (object)array_merge( [
				'cacheNewValue' => true,
				'notifyUrl' => '',
				'notifyUsername' => '',
				'notifyPassword' => '',
			], $store === true ? [] : $store );
		} elseif ( $remote !== false ) {
			$result->remote = (object)array_merge( [
				'url' => 'host',
				'username' => '',
				'password' => '',
			], $remote === true ? [] : $remote );
		}

		$map = [ $result->namespace => [ $result ] ];
		if ( $result->isLocal ) {
			$nsName = $nsName ?: 'Config';
			$nsTalk = $nsTalk ?: ( $nsName . '_talk' );
			$namespaces = [ $result->namespace => $nsName, $result->namespace + 1 => $nsTalk ];
		} else {
			$namespaces = [];
		}
		return [ $map, $namespaces ];
	}
}
