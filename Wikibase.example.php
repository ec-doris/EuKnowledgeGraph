<?php

/**
 * Example configuration for the Wikibase extension.
 *
 * This file is NOT an entry point the Wikibase extension. Use Wikibase.php.
 * It should furthermore not be included from outside the extension.
 *
 * @see docs/options.wiki
 *
 * @license GPL-2.0-or-later
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

call_user_func( function() {
	global $wgDBname,
		$wgExtraNamespaces,
		$wgNamespacesToBeSearchedDefault,
		$wgWBRepoSettings;

	$baseNs = 120;

	// Define custom namespaces. Use these exact constant names.
	define( 'WB_NS_ITEM', $baseNs );
	define( 'WB_NS_ITEM_TALK', $baseNs + 1 );
	define( 'WB_NS_PROPERTY', $baseNs + 2 );
	define( 'WB_NS_PROPERTY_TALK', $baseNs + 3 );

	// Register extra namespaces.
	$wgExtraNamespaces[WB_NS_ITEM] = 'Item';
	$wgExtraNamespaces[WB_NS_ITEM_TALK] = 'Item_talk';
	$wgExtraNamespaces[WB_NS_PROPERTY] = 'Property';
	$wgExtraNamespaces[WB_NS_PROPERTY_TALK] = 'Property_talk';

	// Tell Wikibase which namespace to use for which kind of entity
	$wgWBRepoSettings['entityNamespaces']['item'] = WB_NS_ITEM;
	$wgWBRepoSettings['entityNamespaces']['property'] = WB_NS_PROPERTY;

	// Make sure we use the same keys on repo and clients, so we can share cached objects.
	$wgWBRepoSettings['sharedCacheKeyPrefix'] = $wgDBname . ':WBL';
	$wgWBRepoSettings['sharedCacheKeyGroup'] = $wgDBname;

	// NOTE: no need to set up $wgNamespaceContentModels, Wikibase will do that automatically based on $wgWBRepoSettings

	// Tell MediaWiki to search the item namespace
	$wgNamespacesToBeSearchedDefault[WB_NS_ITEM] = true;

	// Example configuration for enabling termbox
	// both exemplary and used to enable it for CI tests
	$wgWBRepoSettings['termboxEnabled'] = true;
	$wgWBRepoSettings['ssrServerUrl'] = 'http://termbox-ssr.example.com';
	$wgWBRepoSettings['ssrServerTimeout'] = 0.1;
	$wgUseInstantCommons = true;
} );
