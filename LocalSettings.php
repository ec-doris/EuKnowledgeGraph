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
#wfLoadExtension( 'Kartographer' );
#$wgKartographerMapServer = 'https://a.tile.openstreetmap.org';
#$wgKartographerDfltStyle = '';
#$wgKartographerStyles = [];
#$wgWBRepoSettings['useKartographerGlobeCoordinateFormatter'] = true;

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


# Activate S3 extension
wfLoadExtension( 'AWS' );

// Configure AWS credentials.
$wgAWSCredentials = [
	'key' => $_ENV["AWS_ACCESS_KEY_ID"],
	'secret' => $_ENV["AWS_SECRET_ACCESS_KEY"],
	'token' => false
];

$wgAWSRegion = $_ENV["AWS_REGION"]; # Northern Virginia

// Replace <something> with the name of your S3 bucket, e.g. wonderfulbali234.
$wgAWSBucketName = $_ENV["S3_BUCKET_NAME"];

// If you anticipate using several hundred buckets, one per wiki, then it's probably better to use one bucket
// with the top level subdirectory as the wiki's name, and permissions properly configured of course.
// While there are no more performance losses by using such a scheme, it might make things messy. Hence, it's
// still a good idea to use one bucket per wiki unless you are approaching your 1,000 bucket per account limit.
// $wgAWSBucketTopSubdirectory = "/"; # leading slash is required

$wgFileBackends['s3']['privateWiki'] = true;

$wgShowExceptionDetails = true;

$wgAWSRepoHashLevels = '2'; # Default 0
$wgAWSRepoDeletedHashLevels = '3'; # Default 0