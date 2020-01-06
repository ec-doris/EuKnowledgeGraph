<?php
namespace JsonConfig;

use Interwiki;
use MediaWiki\Interwiki\InterwikiLookup;

/**
 * This class simplifies title parsing using MediaWikiTitleCodec.
 * Prepend INTERWIKI_PREFIX constant in front of a title, plus ':',
 * and use it with a custom MediaWikiTitleCodec instance. The original
 * string will be normalized as an interwiki title, without any custom
 * logic like namespace or capitalization changes.
 */
class FauxInterwikiLookup implements InterwikiLookup {

	/**
	 * This class will only accept this string as a valid interwiki
	 */
	const INTERWIKI_PREFIX = 'xyz';

	/**
	 * Check whether an interwiki prefix exists
	 *
	 * @param string $prefix Interwiki prefix to use
	 * @return bool Whether it exists
	 */
	public function isValidInterwiki( $prefix ) {
		return (bool)$this->fetch( $prefix );
	}

	/**
	 * We don't care about local interwikis in this faux lookup
	 * @inheritDoc
	 */
	public function isLocalInterwiki( $prefix ) {
		return false;
	}

	/**
	 * Fetch an Interwiki object
	 *
	 * @param string $prefix Interwiki prefix to use
	 * @return Interwiki|null|bool
	 */
	public function fetch( $prefix ) {
		if ( $prefix !== self::INTERWIKI_PREFIX ) {
			return false;
		}
		return new Interwiki( self::INTERWIKI_PREFIX );
	}

	/**
	 * Returns all interwiki prefixes
	 *
	 * @param string|null $local If set, limits output to local/non-local interwikis
	 * @return string[] List of prefixes
	 */
	public function getAllPrefixes( $local = null ) {
		return ( $local === null || $local === false ) ? [ self::INTERWIKI_PREFIX ] : [];
	}

	/**
	 * Purge the in-process and persistent object cache for an interwiki prefix
	 * @param string $prefix
	 */
	public function invalidateCache( $prefix ) {
	}
}
