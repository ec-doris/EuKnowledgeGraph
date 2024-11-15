<?php

namespace JsonConfig\Tests;

use Scribunto_LuaEngineTestBase;

/**
 * @covers \JsonConfig\JCLuaLibrary
 *
 * @license GPL-2.0-or-later
 */
class JCLuaLibraryTest extends Scribunto_LuaEngineTestBase {

	/** @var string */
	protected static $moduleName = 'JCLuaLibraryTest';

	protected function getTestModules() {
		return parent::getTestModules() + [
				'JCLuaLibraryTest' => __DIR__ . '/JCLuaLibraryTest.lua',
			];
	}

	// enable $wgJsonConfigEnableLuaSupport during the tests
	// needs both suite() and setUp() + tearDown()

	/** @var bool */
	private static $originalJsonConfigEnableLuaSupport;

	private static function doMock(): void {
		global $wgJsonConfigEnableLuaSupport;
		self::$originalJsonConfigEnableLuaSupport = $wgJsonConfigEnableLuaSupport;
		$wgJsonConfigEnableLuaSupport = true;
	}

	private static function doUnmock(): void {
		global $wgJsonConfigEnableLuaSupport;
		$wgJsonConfigEnableLuaSupport = self::$originalJsonConfigEnableLuaSupport;
	}

	public static function suite( $className ) {
		self::doMock();
		try {
			return parent::suite( $className );
		} finally {
			self::doUnmock();
		}
	}

	protected function setUp(): void {
		parent::setUp();
		self::doMock();
	}

	protected function tearDown(): void {
		self::doUnmock();
		parent::tearDown();
	}

}
