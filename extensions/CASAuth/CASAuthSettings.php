<?php
# This file controls the configuration of the CASAuth extension.  The defaults
# for these options are defined in CASAuth.php, and are only defined here as
# well for the sake of documentation.  It is highly suggested you should not
# modify the CASAuth defaults unless you know what you are doing.

# Location of the phpCAS library, which is required for this Extension.  For
# more information, see https://wiki.jasig.org/display/CASC/phpCAS for more
# information.
#
# Default: $CASAuth["phpCAS"]="$IP/extensions/CASAuth/CAS";
$CASAuth["phpCAS"]="$IP/extensions/CASAuth/CAS/source";

# Location of the CAS authentication server.  You must set this to the location
# of your CAS server.  You have a CAS server right?
#
# Default: $CASAuth["Server"]="auth.example.com";
#$CASAuth["Server"]="ecas.acceptance.ec.europa.eu";
$CASAuth["Server"]="webgate.ec.europa.eu";

# An array of servers that are allowed to make use of the Single Sign Out
# feature.  Leave to false if you do not support this feature, of if you dont
# want to use it.  Otherwise, add servers on individual lines.
#  Example:
#    $CASAuth["LogoutServers"][]='cas-logout-01.example.com';
#    $CASAuth["LogoutServers"][]='cas-logout-02.example.com';
#
# Default: $CASAuth["LogoutServers"]=false;
$CASAuth["LogoutServers"]=false;

# Server port for communicating with the CAS server.
#
# Default: $CASAuth["Port"]=443;
$CASAuth["Port"]=443;

# URI Path to the CAS authentication service
#
# Default: $CASAuth["Url"]="/cas/";
$CASAuth["Url"]="/cas/"; 

# CAS Version.  Available versions are "1.0" and "2.0".
#
# Default: $CASAuth["Version"]="2.0";
$CASAuth["Version"]="2.0";

# Enable auto-creation of users when signing in via CASAuth. This is required
# if the users do not already exist in the MediaWiki use database.  If accounts
# are not regularly being creating, it is recommended that this be set to false
#
# Default: $CASAuth["CreateAccounts"]=false
$CASAuth["CreateAccounts"]=true;

# If the "CreateAccounts" option is set "true", the string below is used as a
# salt for generating passwords for the users.  This salt is not used by
# the normal Mediawiki authentication and is only in place to prevent someone
# from cracking passwords in the database.  This should be changed to something
# long and horrendous to remember.
#
# Default: $CASAuth["PwdSecret"]="Secret";
$CASAuth["PwdSecret"]="Secret";

# The email domain is appended to the end of the username when the user logs
# in.  This does not affect their email address, and is for aesthetic purposes
# only.
#
# Default: $CASAuth["EmailDomain"]="example.com";
$CASAuth["EmailDomain"]="example.com";

# Restrict which users can login to the wiki?  If set to true, only the users
# in the $CASAuth["AllowedUsers"] array can login.
#
# Default: $CASAuth["RestrictUsers"]=false
$CASAuth["RestrictUsers"]=false;

# Should CAS users be logged in with the "Remember Me" option?
#
# Default: $CASAuth["RememberMe"]=true;
$CASAuth["RememberMe"]=true;

# If $CASAuth["RestrictUsers"] is set to true, the list of users below are the
# users that are allowed to login to the wiki.
#
# Default: $CASAuth["AllowedUsers"] = false;
$CASAuth["AllowedUsers"] = false;

# If a user is not allowed to login, where should we redirect them to?
#
# Default: $CASAuth["RestrictRedirect"]="http://www.example.com";
$CASAuth["RestrictRedirect"]="http://localhost:8181";

# If you dont like the uid that CAS returns (ie. it returns a number) you can
# modify the routine below to return a customized username instead.
#
# Default: Returns the username, untouched, unless there are characters that
# might be stripped by MediaWiki.
function casNameLookup($username) {

  # Some special characters are automatically trimmed from user names when
  # logging into MediaWiki. For instance if a user authenticates with the CAS
  # account name "__admin_ user ", they would normally be logged into MediaWiki
  # as "Admin user". "admin_" also maps to "Admin". If users are allowed to
  # choose such names in your CAS account signup system, they may be able to
  # log in as one of your wiki's bureaucrat users, allowing them to deface your
  # wiki. The following code blocks certain combinations of underscores,
  # spaces, and other characters normally converted or trimmed from user names.
  #
  # You may find that your CAS server allows user names to contain other
  # special characters that are stripped out by MediaWiki, so you may wish to
  # experiment with your login system, and potentially add more regexes to the
  # `$collisions` array below. Please submit an issue upstream on the plugin
  # project page if you find any more characters that meet this criteria.
  #
  # A good place to look is `splitTitleString` in MediaWiki's
  # `includes/title/MediaWikiTitleCodec.php`. The folllowing code covers the
  # most obvious combinations from there.
  #
  # If you are adding this code to your previously existing CASAuth
  # installation for the first time, please also make sure that you are using
  # the latest patched version of CASAuth.
  #
  # <https://www.mediawiki.org/wiki/Extension:CASAuthentication>

  # Normally, both "admin_user" (with a single underscore) and "admin user"
  # (with a single space) both log in as "Admin user". This Boolean value
  # allows isolated underscores rather than spaces:
  $preferUnderscore = true;

  $collisions = [ "/^_/", "/_$/", "/^ /", "/ $/", "/  /", "/__/", "/_ /", "/ _/", '/[\xA0\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u', '/\xE2\x80[\x8E\x8F\xAA-\xAE]/' ];
  $collisions[] = $preferUnderscore ? "/ /" : "/_/";

  foreach ($collisions as $collision) {
    if(preg_match($collision, $username)) {
      # reject user name
      return null;
    }
  }

  # test for invalid Unicode characters. ('/u' strips them out in PHP 5.2.x).
  $cleaned = preg_replace( '/_/u', '_', $username );
  if ($cleaned !== $username) { return null; }

  # user name checks out
  return $username;
}

# If your users aren't all on the same email domain you can
# modify the routine below to return their email address
#
# Default: Returns $username@EmailDomain
function casEmailLookup($username) {
  global $CASAuth;
  return $username."@".$CASAuth["EmailDomain"];
}

# If you dont like the uid that CAS returns (ie. it returns a number) you can
# modify the routine below to return a customized real name instead.
#
# Default: Returns the username, untouched
function casRealNameLookup($username) {
  return $username;
}

/*
# If you would like to use ldap to retrieve real names, you can use these
# functions instead. Remember to fill in appropriate parameters for ldap.
function casRealNameLookup($username) {
  return @casRealNameLookupFromLDAP($username);
}

function casRealNameLookupFromLDAP($username) {
  try {
    # login to the LDAP server
    $ldap = ldap_connect("host");
    $bind = ldap_bind($ldap, "bind_rdn", "bind_password");

    # look up the user's name by user id
    $result = ldap_search($ldap, "base_dn", "(uid=$username)");
    $info = ldap_get_entries($ldap, $result);

    $first_name = $info[0]["givenname"][0];
    $last_name  = $info[0]["sn"][0];

    # log out of the server
    ldap_unbind($ldap);

    $realname = $first_name . " " . $last_name;
  } catch (Exception $e) {}

  if ($realname == " " || $realname == "" || $realname == NULL) {
    $realname = $username;
  }

  return $realname;
}
*/
