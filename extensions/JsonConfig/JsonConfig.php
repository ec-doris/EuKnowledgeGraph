<?php
/**
 * Extension JsonConfig
 *
 * @file
 * @ingroup Extensions
 * @ingroup JsonConfig
 * @author Yuri Astrakhan <yurik@wikimedia.org>
 * @copyright © 2013 Yuri Astrakhan
 * @note Some of the code and ideas were based on Ori Livneh <ori@wikimedia.org> schema extension
 * @license GPL-2.0-or-later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'JsonConfig' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['JsonConfig'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['JsonConfigNamespaces'] = __DIR__ . '/JsonConfig.namespaces.php';
	wfWarn(
		'Deprecated PHP entry point used for JsonConfig extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the JsonConfig extension requires MediaWiki 1.29+' );
}
