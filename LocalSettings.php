<?php

/*******************************/
/* Enable Federated properties */
/*******************************/
#$wgWBRepoSettings['federatedPropertiesEnabled'] = true;

/*******************************/
/* Enables ConfirmEdit Captcha */
/*******************************/
#wfLoadExtension( 'ConfirmEdit/QuestyCaptcha' );
#$wgCaptchaQuestions = [
#  'What animal' => 'dog',
#];

#$wgCaptchaTriggers['edit']          = true;
#$wgCaptchaTriggers['create']        = true;
#$wgCaptchaTriggers['createtalk']    = true;
#$wgCaptchaTriggers['addurl']        = true;
#$wgCaptchaTriggers['createaccount'] = true;
#$wgCaptchaTriggers['badlogin']      = true;

/*******************************/
/* Disable UI error-reporting  */
/*******************************/
#ini_set( 'display_errors', 0 );


# Disallow anonymous editing
$wgGroupPermissions['*']['edit'] = false;

# Disallow anonymous viewing as well!
#${DOLLAR}wgGroupPermissions['*']['read'] = false;
#${DOLLAR}wgGroupPermissions['user']['read'] = true;

# And don't let users create their own accounts!
$wgGroupPermissions['*']['createaccount'] = false;
$wgGroupPermissions['sysop']['createaccount'] = true;

$wgLogos = [
	'1x' => 'https://linkedopendata.eu/w/images/9/98/1x-Logo_EU_knowledgegraph.png',
	'1.5x' => 'https://linkedopendata.eu/w/images/b/bc/1.5x-Logo_EU_knowledgegraph.png',
	'2x' => 'https://linkedopendata.eu/w/images/5/5b/2x-Logo_EU_knowledgegraph.png',
	'icon' => 'https://linkedopendata.eu/w/images/4/4e/Icon-Logo_EU_knowledgegraph.png'
];

# set the name of the site
$wgSitename = "EU Knowledge Graph";

# enable file upload
$wgEnableUploads = true;

# Add this to separate the identifiers in a separate section
$wgWBRepoSettings['statementSections'] = array(
        'item' => array(
                'statements' => null,
                'identifiers' => array(
                        'type' => 'dataType',
                        'dataTypes' => array( 'external-id' ),
                ),
        ),
);

#Enable Kartographer
wfLoadExtension( 'JsonConfig' );
wfLoadExtension( 'Kartographer' );
$wgKartographerMapServer = 'https://a.tile.openstreetmap.org';
$wgKartographerDfltStyle = '';
$wgKartographerStyles = [];
$wgWBRepoSettings['useKartographerGlobeCoordinateFormatter'] = true;

# Allow longer strings
$wgWBRepoSettings['string-limits'] = array (
	'multilang' => array (
		'length' => 400, // length constraint
	),
	'VT:monolingualtext'  => array (
		'length' => 5000, // length constraint
	),
	'VT:string'  => array (
		'length' => 5000, // length constraint
	),
	'PT:url' => array (
		'length' => 500,
	),
);

# Setting formatter url
$wgWBRepoSettings['formatterUrlProperty'] = 'P877';

# Activate constrain plugin
wfLoadExtension( 'WikibaseQualityConstraints' );
