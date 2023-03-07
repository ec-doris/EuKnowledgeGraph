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

# Increase the memory limit
ini_set('memory_limit', '1536M');

# Disallow anonymous editing
$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['user']['edit'] = false;
$wgGroupPermissions['editor']['edit'] = true;
$wgGroupPermissions['sysop']['edit'] = true;

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
$wgKartographerSrcsetScales = [1];
$wgKartographerStyles = [];
$wgKartographerUseMarkerStyle = true;
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

#CAS
$wgOAuth2Client['client']['id'] = $_ENV["CAS_CLIENT_ID"];
$wgOAuth2Client['client']['secret'] = $_ENV["CAS_CLIENT_SECRET"];
$wgOAuth2Client['configuration']['authorize_endpoint'] = 'https://ecas.ec.europa.eu/cas/oauth2/authorize'; // Authorization URL
$wgOAuth2Client['configuration']['access_token_endpoint'] = 'https://ecas.ec.europa.eu/cas/oauth2/token'; // Token URL
$wgOAuth2Client['configuration']['api_endpoint'] = 'https://ecas.ec.europa.eu/cas/oauth2'; // URL to fetch user JSON
$wgOAuth2Client['configuration']['redirect_uri'] = 'http://linkedopendata.eu/wiki/Special:OAuth2Client/callback'; // URL for OAuth2 server to redirect to
$wgOAuth2Client['configuration']['username'] = 'user.name'; // JSON path to username
$wgOAuth2Client['configuration']['email'] = 'user.email'; // JSON path to email
$wgOAuth2Client['configuration']['scopes'] = 'openid email profile';

#CAS Authentication
require_once "$IP/extensions/CASAuth/CASAuth.php";

#External Storage for Text/Blob table
$wgExternalStores = [ 'DB' ];
$wgExternalServers = [ 'externalStorage01' => [
  [ 'host' => $_ENV["EXTERNAL_STORAGE_SERVICE"], 'user' => 'admin',  'password' =>$_ENV["EXTERNAL_STORAGE_PASSWORD"],  'dbname' => 'my_wiki',  'type' => "mysql", 'load' => 1 ]
] ];
$wgDefaultExternalStore = [ 'DB://externalStorage01' ];
#Enable the compression
$wgCompressRevisions = true;

#Open external links in new tab
$wgExternalLinkTarget = '_blank';


# Activate batch ingestion plugin
wfLoadExtension( 'BatchIngestion' );
# plugins needed to render some templates related to SPARQL examples
#wfLoadExtension( 'TemplateData' );
#wfLoadExtension( 'TemplateStyles' );
#wfLoadExtension( 'SyntaxHighlight_GeSHi' );
#wfLoadExtension( 'ParserFunctions' );

# Enable wikimedia commons images
$wgUseInstantCommons = true;
$wgShowExceptionDetails = true;
