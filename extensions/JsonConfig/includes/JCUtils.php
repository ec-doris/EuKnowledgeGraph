<?php

namespace JsonConfig;

use Exception;
use FormatJson;
use Language;
use MediaWiki\MediaWikiServices;
use MWHttpRequest;
use Status;
use stdClass;
use StubUserLang;

/**
 * Various useful utility functions (all static)
 */
class JCUtils {

	/**
	 * Uses wfLogWarning() to report an error.
	 * All complex arguments are escaped with FormatJson::encode()
	 * @param string $msg
	 * @param mixed|array $vals
	 * @param bool|array $query
	 */
	public static function warn( $msg, $vals, $query = false ) {
		if ( !is_array( $vals ) ) {
			$vals = [ $vals ];
		}
		if ( $query ) {
			foreach ( $query as $k => &$v ) {
				if ( stripos( $k, 'password' ) !== false ) {
					$v = '***';
				}
			}
			$vals['query'] = $query;
		}
		$isFirst = true;
		foreach ( $vals as $k => &$v ) {
			if ( $isFirst ) {
				$isFirst = false;
				$msg .= ': ';
			} else {
				$msg .= ', ';
			}
			if ( is_string( $k ) ) {
				$msg .= $k . '=';
			}
			if ( is_string( $v ) || is_int( $v ) ) {
				$msg .= $v;
			} else {
				$msg .= FormatJson::encode( $v );
			}
		}
		wfLogWarning( $msg );
	}

	/** Init HTTP request object to make requests to the API, and login
	 * @param string $url
	 * @param string $username
	 * @param string $password
	 * @throws \Exception
	 * @return MWHttpRequest|false
	 */
	public static function initApiRequestObj( $url, $username, $password ) {
		$apiUri = wfAppendQuery( $url, [ 'format' => 'json' ] );
		$options = [
			'timeout' => 3,
			'connectTimeout' => 'default',
			'method' => 'POST',
		];
		$req = MediaWikiServices::getInstance()->getHttpRequestFactory()
			->create( $apiUri, $options, __METHOD__ );

		if ( $username && $password ) {
			$tokenQuery = [
				'action' => 'query',
				'meta' => 'tokens',
				'type' => 'login',
			];
			$query = [
				'action' => 'login',
				'lgname' => $username,
				'lgpassword' => $password,
			];
			$res = self::callApi( $req, $tokenQuery, 'get login token' );
			if ( $res !== false ) {
				if ( isset( $res['query']['tokens']['logintoken'] ) ) {
					$query['lgtoken'] = $res['query']['tokens']['logintoken'];
					$res = self::callApi( $req, $query, 'login with token' );
				}
			}
			if ( $res === false ) {
				$req = false;
			} elseif ( !isset( $res['login']['result'] ) ||
				$res['login']['result'] !== 'Success'
			) {
				self::warn( 'Failed to login', [
						'url' => $url,
						'user' => $username,
						'result' => $res['login']['result'] ?? '???'
				] );
				$req = false;
			}
		}
		return $req;
	}

	/**
	 * Make an API call on a given request object and warn in case of failures
	 * @param MWHttpRequest $req logged-in session
	 * @param array $query api call parameters
	 * @param string $debugMsg extra message for debug logs in case of failure
	 * @return array|false api result or false on error
	 */
	public static function callApi( $req, $query, $debugMsg ) {
		$req->setData( $query );
		$status = $req->execute();
		if ( !$status->isGood() ) {
			self::warn(
				'API call failed to ' . $debugMsg,
				[ 'status' => Status::wrap( $status )->getWikiText() ],
				$query
			);
			return false;
		}
		$res = FormatJson::decode( $req->getContent(), true );
		if ( isset( $res['warnings'] ) ) {
			self::warn( 'API call had warnings trying to ' . $debugMsg,
				[ 'warnings' => $res['warnings'] ], $query );
		}
		if ( isset( $res['error'] ) ) {
			self::warn(
				'API call failed trying to ' . $debugMsg, [ 'error' => $res['error'] ], $query
			);
			return false;
		}
		return $res;
	}

	/**
	 * Helper function to check if the given value is an array,
	 * and all keys are integers (non-associative array)
	 * @param array $value array to check
	 * @return bool
	 */
	public static function isList( $value ) {
		return is_array( $value ) &&
			count( array_filter( array_keys( $value ), 'is_int' ) ) === count( $value );
	}

	/**
	 * Helper function to check if the given value is an array,
	 * and all keys are strings (associative array)
	 * @param array $value array to check
	 * @return bool
	 */
	public static function isDictionary( $value ) {
		return is_array( $value ) &&
			count( array_filter( array_keys( $value ), 'is_string' ) ) === count( $value );
	}

	/**
	 * Helper function to check if the given value is an array and if each value in it is a string
	 * @param array $array array to check
	 * @return bool
	 */
	public static function allValuesAreStrings( $array ) {
		return is_array( $array )
			&& count( array_filter( $array, 'is_string' ) ) === count( $array );
	}

	/** Helper function to check if the given value is a valid string no longer than maxlength,
	 * that it has no tabs or new line chars, and that it does not begin or end with spaces
	 * @param string $str
	 * @param int $maxlength
	 * @return bool
	 */
	public static function isValidLineString( $str, $maxlength ) {
		return is_string( $str ) && mb_strlen( $str ) <= $maxlength &&
			!preg_match( '/^\s|[\r\n\t]|\s$/', $str );
	}

	/**
	 * Converts an array representing path to a field into a string in 'a/b/c[0]/d' format
	 * @param array $fieldPath
	 * @throws \Exception
	 * @return string
	 */
	public static function fieldPathToString( array $fieldPath ) {
		$res = '';
		foreach ( $fieldPath as $fld ) {
			if ( is_int( $fld ) ) {
				$res .= '[' . $fld . ']';
			} elseif ( is_string( $fld ) ) {
				$res .= $res !== '' ? ( '/' . $fld ) : $fld;
			} else {
				throw new Exception(
					'Unexpected field type, only strings and integers are allowed'
				);
			}
		}
		return $res === '' ? '/' : $res;
	}

	/**
	 * Recursively copies values from the data, converting JCValues into the actual values
	 * @param mixed|JCValue $data
	 * @param bool $skipDefaults if true, will clone all items except those marked as default
	 * @return mixed
	 */
	public static function sanitize( $data, $skipDefaults = false ) {
		if ( is_a( $data, JCValue::class ) ) {
			$value = $data->getValue();
			if ( $skipDefaults && $data->defaultUsed() ) {
				return is_array( $value ) ? [] : ( is_object( $value ) ? new stdClass() : null );
			}
		} else {
			$value = $data;
		}
		return self::sanitizeRecursive( $value, $skipDefaults );
	}

	/**
	 * @param mixed $data
	 * @param bool $skipDefaults
	 * @return mixed
	 */
	private static function sanitizeRecursive( $data, $skipDefaults ) {
		if ( !is_array( $data ) && !is_object( $data ) ) {
			return $data;
		}
		if ( is_array( $data ) ) {
			// do not filter lists - only subelements if they were checked
			foreach ( $data as &$valRef ) {
				if ( is_a( $valRef, JCValue::class ) ) {
					/** @var JCValue $valRef */
					$valRef = self::sanitizeRecursive( $valRef->getValue(), $skipDefaults );
				}
			}
			return $data;
		}
		$result = new stdClass();
		foreach ( $data as $fld => $val ) {
			if ( is_a( $val, JCValue::class ) ) {
				/** @var JCValue $val */
				if ( $skipDefaults === true && $val->defaultUsed() ) {
					continue;
				}
				$result->$fld = self::sanitizeRecursive( $val->getValue(), $skipDefaults );
			} else {
				$result->$fld = $val;
			}
		}
		return $result;
	}

	/**
	 * Returns true if each of the array's values is a valid language code
	 * @param array $arr
	 * @return bool
	 */
	public static function isListOfLangs( $arr ) {
		return count( $arr ) === count( array_filter( $arr, function ( $v ) {
			return is_string( $v ) && Language::isValidBuiltInCode( $v );
		} ) );
	}

	/**
	 * Returns true if the array is a valid key->value localized nonempty array
	 * @param array $arr
	 * @param int $maxlength
	 * @return bool
	 */
	public static function isLocalizedArray( $arr, $maxlength ) {
		if ( is_array( $arr ) &&
			$arr &&
			self::isListOfLangs( array_keys( $arr ) )
		) {
			$validStrCount = count( array_filter( $arr, function ( $str ) use ( $maxlength ) {
				return self::isValidLineString( $str, $maxlength );
			} ) );
			if ( $validStrCount === count( $arr ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find a message in a dictionary for the given language,
	 * or use language fallbacks if message is not defined.
	 * @param stdClass $map Dictionary of languageCode => string
	 * @param Language|StubUserLang $lang language object
	 * @param bool|string $defaultValue if non-false, use this value in case no fallback and no 'en'
	 * @return string message from the dictionary or "" if nothing found
	 */
	public static function pickLocalizedString( stdClass $map, $lang, $defaultValue = false ) {
		$langCode = $lang->getCode();
		if ( property_exists( $map, $langCode ) ) {
			return $map->$langCode;
		}
		foreach ( $lang->getFallbackLanguages() as $l ) {
			if ( property_exists( $map, $l ) ) {
				return $map->$l;
			}
		}
		// If fallbacks fail, check if english is defined
		if ( property_exists( $map, 'en' ) ) {
			return $map->en;
		}

		// We have a custom default, return that
		if ( $defaultValue !== false ) {
			return $defaultValue;
		}

		// Return first available value, or an empty string
		// There might be a better way to get the first value from an object
		$map = (array)$map;
		return reset( $map ) ? : '';
	}
}
