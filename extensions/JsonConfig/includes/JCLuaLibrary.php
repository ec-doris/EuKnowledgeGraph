<?php

namespace JsonConfig;

use Language;
use Scribunto_LuaError;
use Scribunto_LuaLibraryBase;

class JCLuaLibrary extends Scribunto_LuaLibraryBase {

	/**
	 * @param string $engine
	 * @param string[] &$extraLibraries
	 *
	 * @return bool
	 */
	public static function onScribuntoExternalLibraries( $engine, array &$extraLibraries ) {
		global $wgJsonConfigEnableLuaSupport;
		if ( $wgJsonConfigEnableLuaSupport && $engine == 'lua' ) {
			$extraLibraries['mw.ext.data'] = 'JsonConfig\JCLuaLibrary';
		}

		return true;
	}

	public function register() {
		$functions = [ 'get' => [ $this, 'get' ] ];
		$moduleFileName = __DIR__ . DIRECTORY_SEPARATOR . 'JCLuaLibrary.lua';
		return $this->getEngine()->registerInterface( $moduleFileName, $functions, [] );
	}

	/**
	 * Returns data page as a data table
	 * @param string $titleStr name of the page in the Data namespace
	 * @param string $langCode language code. If '_' is given, returns all codes
	 * @return false[]|object[]
	 * @throws Scribunto_LuaError
	 */
	public function get( $titleStr, $langCode ) {
		$this->checkType( 'get', 1, $titleStr, 'string' );
		if ( $langCode === null ) {
			$language = $this->getParser()->getTargetLanguage();
		} elseif ( $langCode !== '_' ) {
			$this->checkType( 'get', 2, $langCode, 'string' );
			$language = Language::factory( $langCode );
		} else {
			$language = null;
		}

		$jct = JCSingleton::parseTitle( $titleStr, NS_DATA );
		if ( !$jct ) {
			throw new Scribunto_LuaError( 'bad argument #1 to "get" (not a valid title)' );
		}

		$content = JCSingleton::getContentFromLocalCache( $jct );
		if ( $content === null ) {
			$this->incrementExpensiveFunctionCount();
			$content = JCSingleton::getContent( $jct );

			$prop = 'jsonconfig_getdata';
			$output = $this->getParser()->getOutput();
			$output->setProperty( $prop, 1 + ( $output->getProperty( $prop ) ? : 0 ) );
		}

		if ( !$content ) {
			$result = false;
		} else {
			if ( $language === null || !method_exists( $content, 'getLocalizedData' ) ) {
				$result = $content->getData();
			} else {
				/** @var JCDataContent $content */
				// @phan-suppress-next-line PhanUndeclaredMethod
				$result = $content->getLocalizedData( $language );
			}
			// Always re-index tabular data
			if ( $content instanceof JCTabularContent ) {
				self::reindexTabularData( $result );
			}
		}

		return [ self::objectToArray( $result ) ];
	}

	/**
	 * Replace objects with arrays, recursively
	 *
	 * Since LuaSandbox 3.0.0, we can't return objects to Lua anymore. And it
	 * never worked with Scribunto's LuaStandalone engine.
	 *
	 * @param mixed $v
	 * @return mixed
	 */
	private static function objectToArray( $v ) {
		if ( is_object( $v ) ) {
			$v = get_object_vars( $v );
		}
		if ( is_array( $v ) ) {
			$v = array_map( 'self::objectToArray', $v );
		}
		return $v;
	}

	/**
	 * Reindex tabular data so it can be processed by Lua more easily
	 * @param object $data
	 */
	public static function reindexTabularData( $data ) {
		$columnCount = count( $data->schema->fields );
		$rowCount = count( $data->data );
		if ( $columnCount > 0 ) {
			$rowIndexes = range( 1, $columnCount );
			$data->schema->fields = array_combine( $rowIndexes, $data->schema->fields );
			if ( $rowCount > 0 ) {
				$data->data =
					array_combine( range( 1, $rowCount ),
						array_map( function ( $row ) use ( $rowIndexes ) {
							return array_combine( $rowIndexes, $row );
						}, $data->data ) );
			}
		} elseif ( $rowCount > 0 ) {
			// Weird, but legal
			$data->data = array_combine( range( 1, $rowCount ), $data->data );
		}
	}
}
