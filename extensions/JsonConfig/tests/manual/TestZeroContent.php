<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( -1 );
}

define( 'NS_ZERO', 480 );
define( 'NS_ZERO_TALK', 481 );

$wgJsonConfigModels['Test.JsonZeroConfig'] = 'TestZeroContent';

/*
 * $wgJsonConfigs['Test.JsonZeroConfig'] = [
 * 	// model is the same as key
 * 	'name' => 'ZeroSingle',
 * 	'isLocal' => true,
 * ];
 * $wgJsonConfigs['Test.Zero.Subpages'] = [
 * 	'model' => 'Test.JsonZeroConfig',
 * 	'name' => 'Zero',
 * 	'issubspace' => true,
 * 	'isLocal' => true,
 * ];
 * $wgJsonConfigs['Test.Zero.Ns'] = [
 * 	'model' => 'Test.JsonZeroConfig',
 * 	'namespace' => 600,
 * 	'nsname' => 'Z',
 * 	'isLocal' => true,
 * ];
 */
$wgJsonConfigs['Test.Zero.Ns'] = [
	'model' => 'Test.JsonZeroConfig',
	'namespace' => NS_ZERO,
	'nsname' => 'Zero',
	'isLocal' => false,
	'url' => 'https://zero.wikimedia.org/w/api.php',
	'username' => $wmgZeroPortalApiUserName,
	'password' => $wmgZeroPortalApiPassword,
];

$wgExtensionFunctions[] = function () {
	$content = \JsonConfig\JCSingleton::getContent( new TitleValue( NS_ZERO, '250-99' ) );
};
