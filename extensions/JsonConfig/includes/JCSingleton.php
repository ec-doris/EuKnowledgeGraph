<?php
namespace JsonConfig;

use Exception;
use GenderCache;
use MalformedTitleException;
use MapCacheLRU;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MainConfigNames;
use MediaWiki\MainConfigSchema;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWikiTitleCodec;
use stdClass;
use TitleParser;
use TitleValue;

/**
 * Static utility methods and configuration page hook handlers for JsonConfig extension.
 *
 * @file
 * @ingroup Extensions
 * @ingroup JsonConfig
 * @author Yuri Astrakhan
 * @copyright © 2013 Yuri Astrakhan
 * @license GPL-2.0-or-later
 */
class JCSingleton {
	/**
	 * @var array describes how a title should be handled by JsonConfig extension.
	 * The structure is an array of array of ...:
	 * { int_namespace => { name => { allows-sub-namespaces => configuration_array } } }
	 */
	public static $titleMap = [];

	/**
	 * @var string[]|false[] containing all the namespaces handled by JsonConfig
	 * Maps namespace id (int) => namespace name (string).
	 * If false, presumes the namespace has been registered by core or another extension
	 */
	public static $namespaces = [];

	/**
	 * @var MapCacheLRU[] contains a cache of recently resolved JCTitle's
	 *   as namespace => MapCacheLRU
	 */
	public static $titleMapCacheLru = [];

	/**
	 * @var MapCacheLRU[] contains a cache of recently requested content objects
	 *   as namespace => MapCacheLRU
	 */
	public static $mapCacheLru = [];

	/**
	 * @var TitleParser cached invariant title parser
	 */
	public static $titleParser;

	/**
	 * Initializes singleton state by parsing $wgJsonConfig* values
	 * @throws Exception
	 */
	public static function init() {
		static $isInitialized = false;
		if ( $isInitialized ) {
			return;
		}
		$isInitialized = true;
		global $wgNamespaceContentModels, $wgContentHandlers, $wgJsonConfigs, $wgJsonConfigModels;
		list( self::$titleMap, self::$namespaces ) = self::parseConfiguration(
			$wgNamespaceContentModels,
			$wgContentHandlers,
			array_replace_recursive(
				\ExtensionRegistry::getInstance()->getAttribute( 'JsonConfigs' ), $wgJsonConfigs
			),
			array_replace_recursive(
				\ExtensionRegistry::getInstance()->getAttribute( 'JsonConfigModels' ),
				$wgJsonConfigModels
			)
		);
	}

	/**
	 * @param array $namespaceContentModels $wgNamespaceContentModels
	 * @param array $contentHandlers $wgContentHandlers
	 * @param array $configs $wgJsonConfigs
	 * @param array $models $wgJsonConfigModels
	 * @param bool $warn if true, calls wfLogWarning() for all errors
	 * @return array [ $titleMap, $namespaces ]
	 */
	public static function parseConfiguration(
		array $namespaceContentModels, array $contentHandlers,
		array $configs, array $models, $warn = true
	) {
		$defaultModelId = 'JsonConfig';
		$warnFunc = $warn
			? 'wfLogWarning'
			: static function ( $msg ) {
			};

		$namespaces = [];
		$titleMap = [];
		foreach ( $configs as $confId => &$conf ) {
			if ( !is_string( $confId ) ) {
				$warnFunc(
					"JsonConfig: Invalid \$wgJsonConfigs['$confId'], the key must be a string"
				);
				continue;
			}
			if ( self::getConfObject( $warnFunc, $conf, $confId ) === null ) {
				continue; // warned inside the function
			}

			$modelId = property_exists( $conf, 'model' )
				? ( $conf->model ? : $defaultModelId ) : $confId;
			if ( !array_key_exists( $modelId, $models ) ) {
				if ( $modelId === $defaultModelId ) {
					$models[$defaultModelId] = null;
				} else {
					$warnFunc( "JsonConfig: Invalid \$wgJsonConfigs['$confId']: " .
						"Model '$modelId' is not defined in \$wgJsonConfigModels" );
					continue;
				}
			}
			if ( array_key_exists( $modelId, $contentHandlers ) ) {
				$warnFunc( "JsonConfig: Invalid \$wgJsonConfigs['$confId']: Model '$modelId' is " .
					"already registered in \$contentHandlers to {$contentHandlers[$modelId]}" );
				continue;
			}
			$conf->model = $modelId;

			$ns = self::getConfVal( $conf, 'namespace', NS_CONFIG );
			if ( !is_int( $ns ) || $ns % 2 !== 0 ) {
				$warnFunc( "JsonConfig: Invalid \$wgJsonConfigs['$confId']: " .
					"Namespace $ns should be an even number" );
				continue;
			}
			// Even though we might be able to override default content model for namespace,
			// lets keep things clean
			if ( array_key_exists( $ns, $namespaceContentModels ) ) {
				$warnFunc( "JsonConfig: Invalid \$wgJsonConfigs['$confId']: Namespace $ns is " .
					"already set to handle model '$namespaceContentModels[$ns]'" );
				continue;
			}

			// nsName & nsTalk are handled later
			self::getConfVal( $conf, 'pattern', '' );
			self::getConfVal( $conf, 'cacheExp', 24 * 60 * 60 );
			self::getConfVal( $conf, 'cacheKey', '' );
			self::getConfVal( $conf, 'flaggedRevs', false );
			self::getConfVal( $conf, 'license', false );
			$islocal = self::getConfVal( $conf, 'isLocal', true );

			// Decide if matching configs should be stored on this wiki
			$storeHere = $islocal || property_exists( $conf, 'store' );
			if ( !$storeHere ) {
				// 'store' does not exist, use it as a flag to indicate remote storage
				$conf->store = false;
				$remote = self::getConfObject( $warnFunc, $conf, 'remote', $confId, 'url' );
				if ( $remote === null ) {
					continue; // warned inside the function
				}
				if ( self::getConfVal( $remote, 'url', '' ) === '' ) {
					$warnFunc( "JsonConfig: Invalid \$wgJsonConfigs['$confId']['remote']['url']: " .
						"API URL is not set, and this config is not being stored locally" );
					continue;
				}
				self::getConfVal( $remote, 'username', '' );
				self::getConfVal( $remote, 'password', '' );
			} else {
				if ( property_exists( $conf, 'remote' ) ) {
					// non-fatal -- simply ignore the 'remote' setting
					$warnFunc( "JsonConfig: In \$wgJsonConfigs['$confId']['remote'] is set for " .
						"the config that will be stored on this wiki. " .
						"'remote' parameter will be ignored."
					);
				}
				$conf->remote = null;
				$store = self::getConfObject( $warnFunc, $conf, 'store', $confId );
				if ( $store === null ) {
					continue; // warned inside the function
				}
				self::getConfVal( $store, 'cacheNewValue', true );
				self::getConfVal( $store, 'notifyUrl', '' );
				self::getConfVal( $store, 'notifyUsername', '' );
				self::getConfVal( $store, 'notifyPassword', '' );
			}

			// Too lazy to write proper error messages for all parameters.
			if ( ( isset( $conf->nsTalk ) && !is_string( $conf->nsTalk ) ) ||
				!is_string( $conf->pattern ) ||
				!is_bool( $islocal ) || !is_int( $conf->cacheExp ) || !is_string( $conf->cacheKey )
				|| !is_bool( $conf->flaggedRevs )
			) {
				$warnFunc( "JsonConfig: Invalid type of one of the parameters in " .
					"\$wgJsonConfigs['$confId'], please check documentation" );
				continue;
			}
			if ( isset( $remote ) ) {
				if ( !is_string( $remote->url ) || !is_string( $remote->username ) ||
					!is_string( $remote->password )
				) {
					$warnFunc( "JsonConfig: Invalid type of one of the parameters in " .
						"\$wgJsonConfigs['$confId']['remote'], please check documentation" );
					continue;
				}
			}
			if ( isset( $store ) ) {
				if ( !is_bool( $store->cacheNewValue ) || !is_string( $store->notifyUrl ) ||
					!is_string( $store->notifyUsername ) || !is_string( $store->notifyPassword )
				) {
					$warnFunc( "JsonConfig: Invalid type of one of the parameters in " .
						" \$wgJsonConfigs['$confId']['store'], please check documentation" );
					continue;
				}
			}
			if ( $storeHere ) {
				// If nsName is given, add it to the list, together with the talk page
				// Otherwise, create a placeholder for it
				if ( property_exists( $conf, 'nsName' ) ) {
					if ( $conf->nsName === false ) {
						// Non JC-specific namespace, don't register it
						if ( !array_key_exists( $ns, $namespaces ) ) {
							$namespaces[$ns] = false;
						}
					} elseif ( $ns === NS_CONFIG ) {
						$warnFunc( "JsonConfig: Parameter 'nsName' in \$wgJsonConfigs['$confId'] " .
							"is not supported for namespace == NS_CONFIG ($ns)" );
					} else {
						$nsName = $conf->nsName;
						$nsTalk = $conf->nsTalk ?? $nsName . '_talk';
						if ( !is_string( $nsName ) || $nsName === '' ) {
							$warnFunc( "JsonConfig: Invalid \$wgJsonConfigs['$confId']: " .
									"if given, nsName must be a string" );
							continue;
						} elseif ( array_key_exists( $ns, $namespaces ) &&
								$namespaces[$ns] !== null
						) {
							if ( $namespaces[$ns] !== $nsName ||
								$namespaces[$ns + 1] !== $nsTalk
							) {
								$warnFunc( "JsonConfig: \$wgJsonConfigs['$confId'] - " .
										"nsName has already been set for namespace $ns" );
							}
						} else {
							$namespaces[$ns] = $nsName;
							$namespaces[$ns + 1] = $conf->nsTalk ?? $nsName . '_talk';
						}
					}
				} elseif ( !array_key_exists( $ns, $namespaces ) || $namespaces[$ns] === false ) {
					$namespaces[$ns] = null;
				}
			}

			if ( !array_key_exists( $ns, $titleMap ) ) {
				$titleMap[$ns] = [ $conf ];
			} else {
				$titleMap[$ns][] = $conf;
			}
		}

		// Add all undeclared namespaces
		$missingNs = 1;
		foreach ( $namespaces as $ns => $nsName ) {
			if ( $nsName === null ) {
				$nsName = 'Config';
				if ( $ns !== NS_CONFIG ) {
					$nsName .= $missingNs;
					$warnFunc(
						"JsonConfig: Namespace $ns does not have 'nsName' defined, using '$nsName'"
					);
					$missingNs += 1;
				}
				$namespaces[$ns] = $nsName;
				$namespaces[$ns + 1] = $nsName . '_talk';
			}
		}

		return [ $titleMap, $namespaces ];
	}

	/**
	 * Helper function to check if configuration has a field set, and if not, set it to default
	 * @param stdClass &$conf
	 * @param string $field
	 * @param mixed $default
	 * @return mixed
	 */
	private static function getConfVal( &$conf, $field, $default ) {
		if ( property_exists( $conf, $field ) ) {
			return $conf->$field;
		}
		$conf->$field = $default;
		return $default;
	}

	/**
	 * Helper function to check if configuration has a field set, and if not, set it to default
	 * @param callable $warnFunc
	 * @param stdClass &$value
	 * @param string $field
	 * @param string|null $confId
	 * @param string|null $treatAsField
	 * @return null|stdClass
	 */
	private static function getConfObject(
		$warnFunc, &$value, $field, $confId = null, $treatAsField = null
	) {
		if ( !$confId ) {
			$val = & $value;
		} else {
			if ( !property_exists( $value, $field ) ) {
				$value->$field = null;
			}
			$val = & $value->$field;
		}
		if ( $val === null || $val === true ) {
			$val = (object)[];
		} elseif ( is_array( $val ) ) {
			$val = (object)$val;
		} elseif ( is_string( $val ) && $treatAsField !== null ) {
			// treating this string value as a sub-field
			$val = (object)[ $treatAsField => $val ];
		} elseif ( !is_object( $val ) ) {
			$warnFunc( "JsonConfig: Invalid \$wgJsonConfigs" . ( $confId ? "['$confId']" : "" ) .
				"['$field'], the value must be either an array or an object" );
			return null;
		}
		return $val;
	}

	/**
	 * Get content object from the local LRU cache, or null if doesn't exist
	 * @param TitleValue $titleValue
	 * @return null|JCContent
	 */
	public static function getContentFromLocalCache( TitleValue $titleValue ) {
		// Some of the titleValues are remote, and their namespace might not be declared
		// in the current wiki. Since TitleValue is a content object, it does not validate
		// the existence of namespace, hence we use it as a simple storage.
		// Producing an artificial string key by appending (namespaceID . ':' . titleDbKey)
		// seems wasteful and redundant, plus most of the time there will be just a single
		// namespace declared, so this structure seems efficient and easy enough.
		if ( !array_key_exists( $titleValue->getNamespace(), self::$mapCacheLru ) ) {
			// TBD: should cache size be a config value?
			self::$mapCacheLru[$titleValue->getNamespace()] = $cache = new MapCacheLRU( 10 );
		} else {
			$cache = self::$mapCacheLru[$titleValue->getNamespace()];
		}

		return $cache->get( $titleValue->getDBkey() );
	}

	/**
	 * Get content object for the given title.
	 * Namespace ID does not need to be defined in the current wiki,
	 * as long as it is defined in $wgJsonConfigs.
	 * @param TitleValue|JCTitle $titleValue
	 * @return bool|JCContent Returns false if the title is not handled by the settings
	 */
	public static function getContent( TitleValue $titleValue ) {
		$content = self::getContentFromLocalCache( $titleValue );

		if ( $content === null ) {
			$jct = self::parseTitle( $titleValue );
			if ( $jct ) {
				$store = new JCCache( $jct );
				$content = $store->get();
				if ( is_string( $content ) ) {
					// Convert string to the content object if needed
					$handler = new JCContentHandler( $jct->getConfig()->model );
					$content = $handler->unserializeContent( $content, null, false );
				}
			} else {
				$content = false;
			}
			self::$mapCacheLru[$titleValue->getNamespace()]
				->set( $titleValue->getDBkey(), $content );
		}

		return $content;
	}

	/**
	 * Parse json text into a content object for the given title.
	 * Namespace ID does not need to be defined in the current wiki,
	 * as long as it is defined in $wgJsonConfigs.
	 * @param TitleValue $titleValue
	 * @param string $jsonText json content
	 * @param bool $isSaving if true, performs extensive validation during unserialization
	 * @return bool|JCContent Returns false if the title is not handled by the settings
	 * @throws Exception
	 */
	public static function parseContent( TitleValue $titleValue, $jsonText, $isSaving = false ) {
		$jct = self::parseTitle( $titleValue );
		if ( $jct ) {
			$handler = new JCContentHandler( $jct->getConfig()->model );
			return $handler->unserializeContent( $jsonText, null, $isSaving );
		}

		return false;
	}

	/**
	 * Mostly for debugging purposes, this function returns initialized internal JsonConfig settings
	 * @return array[] map of namespaceIDs to list of configurations
	 */
	public static function getTitleMap() {
		self::init();
		return self::$titleMap;
	}

	/**
	 * Get the name of the class for a given content model
	 * @param string $modelId
	 * @return string
	 * @phan-return class-string
	 */
	public static function getContentClass( $modelId ) {
		global $wgJsonConfigModels;
		$configModels = array_replace_recursive(
			\ExtensionRegistry::getInstance()->getAttribute( 'JsonConfigModels' ),
			$wgJsonConfigModels
		);
		$class = null;
		if ( array_key_exists( $modelId, $configModels ) ) {
			$value = $configModels[$modelId];
			if ( is_array( $value ) ) {
				if ( !array_key_exists( 'class', $value ) ) {
					wfLogWarning( "JsonConfig: Invalid \$wgJsonConfigModels['$modelId'] array " .
						"value, 'class' not found" );
				} else {
					$class = $value['class'];
				}
			} else {
				$class = $value;
			}
		}
		if ( !$class ) {
			$class = JCContent::class;
		}
		return $class;
	}

	/**
	 * Given a title (either a user-given string, or as an object), return JCTitle
	 * @param Title|TitleValue|string $value
	 * @param int|null $namespace Only used when title is a string
	 * @return JCTitle|null|false false if unrecognized namespace,
	 * and null if namespace is handled but does not match this title
	 * @throws Exception
	 */
	public static function parseTitle( $value, $namespace = null ) {
		if ( $value === null || $value === '' || $value === false ) {
			// In some weird cases $value is null
			return false;
		} elseif ( $value instanceof JCTitle ) {
			// Nothing to do
			return $value;
		} elseif ( $namespace !== null && !is_int( $namespace ) ) {
			throw new Exception( '$namespace parameter must be either null or an integer' );
		}

		// figure out the namespace ID (int) - we don't need to parse the string if ns is unknown
		if ( $value instanceof LinkTarget ) {
			if ( $namespace === null ) {
				$namespace = $value->getNamespace();
			}
		} elseif ( is_string( $value ) ) {
			if ( $namespace === null ) {
				throw new Exception( '$namespace parameter is missing for string $value' );
			}
		} else {
			wfLogWarning( 'Unexpected title param type ' . gettype( $value ) );
			return false;
		}

		// Search title map for the matching configuration
		$map = self::getTitleMap();
		if ( array_key_exists( $namespace, $map ) ) {
			// Get appropriate LRU cache object
			if ( !array_key_exists( $namespace, self::$titleMapCacheLru ) ) {
				self::$titleMapCacheLru[$namespace] = $cache = new MapCacheLRU( 20 );
			} else {
				$cache = self::$titleMapCacheLru[$namespace];
			}

			// Parse string if needed
			// TODO: should the string parsing also be cached?
			if ( is_string( $value ) ) {
				$language = MediaWikiServices::getInstance()->getLanguageFactory()->getLanguage( 'en' );
				if ( !self::$titleParser ) {
					// XXX Direct instantiation of MediaWikiTitleCodec isn't allowed. If core
					// doesn't support our use-case, core needs to be fixed to allow this.
					$oldArgStyle =
						( new \ReflectionMethod( \MediaWikiTitleCodec::class, '__construct' ) )
						->getParameters()[2]->getName() === 'localInterwikis';
					self::$titleParser = new MediaWikiTitleCodec(
						$language,
						new GenderCache(),
						$oldArgStyle ? []
							// @phan-suppress-next-line PhanUndeclaredConstantOfClass Not merged yet
							: new ServiceOptions( MediaWikiTitleCodec::CONSTRUCTOR_OPTIONS, [
								MainConfigNames::LegalTitleChars =>
									MainConfigSchema::LegalTitleChars['default'],
								MainConfigNames::LocalInterwikis => [],
							] ),
						new FauxInterwikiLookup(),
						MediaWikiServices::getInstance()->getNamespaceInfo()
					);
				}
				// Interwiki prefixes are a special case for title parsing:
				// first letter is not capitalized, namespaces are not resolved, etc.
				// So we prepend an interwiki prefix to fool title codec, and later remove it.
				try {
					$value = FauxInterwikiLookup::INTERWIKI_PREFIX . ':' . $value;
					$title = self::$titleParser->parseTitle( $value );

					// Defensive coding - ensure the parsing has proceeded as expected
					if ( $title->getDBkey() === '' || $title->getNamespace() !== NS_MAIN ||
						$title->hasFragment() ||
						$title->getInterwiki() !== FauxInterwikiLookup::INTERWIKI_PREFIX
					) {
						return null;
					}
				} catch ( MalformedTitleException $e ) {
					return null;
				}

				// At this point, only support wiki namespaces that capitalize title's first char,
				// but do not enable sub-pages.
				// This way data can already be stored on Mediawiki namespace everywhere, or
				// places like commons and zerowiki.
				// Another implicit limitation: there might be an issue if data is stored on a wiki
				// with the non-default ucfirst(), e.g. az, kaa, kk, tr -- they convert "i" to "İ"
				$dbKey = $language->ucfirst( $title->getDBkey() );
			} else {
				$dbKey = $value->getDBkey();
			}

			// A bit weird here: cache will store JCTitle objects or false if the namespace
			// is known to JsonConfig but the dbkey does not match. But in case the title is not
			// handled, this function returns null instead of false if the namespace is known,
			// and false otherwise
			$result = $cache->get( $dbKey );
			if ( $result === null ) {
				$result = false;
				foreach ( $map[$namespace] as $conf ) {
					$re = $conf->pattern;
					if ( !$re || preg_match( $re, $dbKey ) ) {
						$result = new JCTitle( $namespace, $dbKey, $conf );
						break;
					}
				}

				$cache->set( $dbKey, $result );
			}

			// return null if the given namespace is mentioned in the config,
			// but title doesn't match
			return $result ?: null;

		} else {
			// return false if JC doesn't know anything about this namespace
			return false;
		}
	}

	/**
	 * Returns an array with settings if the $titleValue object is handled by the JsonConfig
	 * extension, false if unrecognized namespace,
	 * and null if namespace is handled but not this title
	 * @param TitleValue $titleValue
	 * @return stdClass|false|null
	 * @deprecated use JCSingleton::parseTitle() instead
	 */
	public static function getMetadata( $titleValue ) {
		$jct = self::parseTitle( $titleValue );
		return $jct ? $jct->getConfig() : $jct;
	}

}
