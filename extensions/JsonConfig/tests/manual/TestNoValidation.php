<?php
if ( !defined( 'MEDIAWIKI' ) ) {
	die( -1 );
}

$wgJsonConfigModels['Test.NoValidation'] = null;
$wgJsonConfigs['Test.NoValidation'] = [
	'name' => 'NoValidation',
	'isLocal' => true,
];
