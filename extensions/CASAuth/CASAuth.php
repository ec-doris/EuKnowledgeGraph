<?php
/*
 * CASification script for MediaWiki 1.27 with phpCAS 1.3.3
 *
 * Requires phpCAS: https://wiki.jasig.org/display/CASC/phpCAS
 * Install by adding this line to LocalSetting.php:
 *  require_once("$IP/extensions/CASAuth/CASAuth.php");
 *
 * *** Please keep all configuration in the CASAuthSettings.php file ***
 *
 * Revision History
 *   Original Revision: Ioannis Yessios
 *                      ioannis [dot] yessios [at] yale [dot] edu
 *   Single Sign-Out code and more: Hauke Pribnow
 *                      hauke [dot] pribnow [at] gmx [dot] de
 *   Worked with the code: Christophe Naslain
 *                      chris [dot] n [at] free [dot] fr
 *   Which was based on the original script using CAS Utils by Victor Chen
 *                      Yvchen [at] sfu [dot] ca
 *   Cleaned up and bugfixed: Stefan Sundin
 *                      recover89 [at] gmail [dot] com
 *   User filtering code, seperation of config and code cleanup: Aaron Russo
 *                      arusso [at] berkeley [dot] edu
 *   Email lookup hook added: Amir Tahvildaran
 *                      amirdt22 [at] gmail [dot] com
 *   MW 1.27 compatibility: Jeffrey Gill
 *                      jeffrey [dot] p [dot] gill [at] gmail [dot] com
 *   Security patch (user name trimming): Andrew Engelbrecht (contribution under GPLv2-or-later)
 *                      andrew [at] engelbrecht [dot] io
 */

$wgExtensionCredits["other"][] = array(
        "name"        => "CASAuth",
        "version"     => "2.0.4",
        "author"      => "Ioannis Yessios, Hauke Pribnow, Aaron Russo, Jeffrey Gill",
        "url"         => "https://github.com/CWRUChielLab/CASAuth",
        "description" => "Overrides MediaWiki's Authentication and implements Central Authentication Service (CAS) Authentication.  Original url: http://www.mediawiki.org/wiki/Extension:CASAuthentication"
);

//--------------------------------------------------------------------------
// Configuration Variable Defaults - See CASAuth.conf
//--------------------------------------------------------------------------

$CASAuth = array(
        "phpCAS"         => "$IP/extensions/CASAuth/CAS",
        "Server"         => "auth.example.com",
        "LogoutServers"  => false,
        "Port"           => 443,
        "Url"            => "/cas/",
        "Version"        => "2.0",
        "CreateAccounts" => false,     
        "PwdSecret"      => "Secret",

        "EmailDomain"    => "example.com",
        "RememberMe"     => true,
        "AllowedUsers"   => false,     
        "RestrictUsers"  => false,
);

# load our custom configuration
require_once(__DIR__ . "/CASAuthSettings.php");

//--------------------------------------------------------------------------
// CASAuth
//--------------------------------------------------------------------------

// Setup hooks
global $wgHooks;
$wgHooks["UserLoadAfterLoadFromSession"][] = "casLogin";
//$wgHooks["UserLogoutComplete"][] = "casLogout";
$wgHooks["GetPreferences"][] = "casPrefs";
/*$wgHooks['SkinBuildSidebar'][] = function( $skin, &$bar ) {
	$bar['navigation'][] = [
		'text'  => 'EU Login',
		'href'  => 'w/index.php?title=Special:UserLogin&returnto=Main+Page&eulogin=true',
		'title' => 'EU Login',
		'id'    => 'n-eulogin',
	];
};*/
$wgHooks['PersonalUrls'][] = function( array &$personal_urls, Title $title ) {
        $user = RequestContext::getMain()->getUser();
        if ( $user->isRegistered() ) {
            return true;
        }
    	$personal_urls['eulogin_login'] = array(
                'text' => 'EU Login',
                'active' => false,
                'href' => 'w/index.php?title=Special:UserLogin&returnto=Main+Page&eulogin=true'
        );
};

global $wgExtensionFunctions;
$wgExtensionFunctions[] = 'casLogoutCheck';

//$casIsSetUp;
$casIsSetUp = false;

// Check if there was a valid single sign-out message that terminated this session
function casLogoutCheck() {
        global $CASAuth;

        if(isset($_SESSION['wsCASLoggedOut']) && $_SESSION['wsCASLoggedOut']) {
                RequestContext::getMain()->getUser()->logout();

                unset($_SESSION['wsCASLoggedOut']);
                unset($_SESSION['phpCAS']);
        }
}

// Login
function casLogin($user) {
        global $CASAuth;
        global $casIsSetUp;
        global $wgRequest, $wgOut;

        /*if (isset($_REQUEST["form"]) || $_SERVER['REQUEST_METHOD'] === 'POST') {
                return true;
	}*/

        if (isset($_REQUEST["title"]) && isset($_REQUEST["eulogin"])) {

                if ($_REQUEST["title"] == SpecialPage::getTitleFor("Userlogin")->getPrefixedDBkey()) {
                        
                        $cas_is_setup = $casIsSetUp ? 'true' : 'false';
                        
                        // Load phpCAS
                        if(!$casIsSetUp)
                                casSetup();
							

                        //Will redirect to CAS server if not logged in
                        phpCAS::forceAuthentication();

                        // Get username
                        $username = casNameLookup(phpCAS::getUser());
						
                        error_log("USERNAME=".$username);

                        // casNameLookup() says name is invalid
                        if (is_null($username)) {
                          // redirect user to the RestrictRedirect page
                          $wgOut->redirect($CASAuth["RestrictRedirect"]);
                          return true;
                        }

                        // If we are restricting users AND the user is not in
                        // the allowed users list, lets block the login
                        if($CASAuth["RestrictUsers"]==true
                           && !in_array($username,$CASAuth["AllowedUsers"]))
                          {
                            // redirect user to the RestrictRedirect page
                            $wgOut->redirect($CASAuth["RestrictRedirect"]);
                            return true;
                          }

                        // Get MediaWiki user
                        $u = User::newFromName($username);

                        //error_log("USER ID=".$u->getID());

                        // MediaWiki says name is invalid
                        if ($u === false) {
                          // redirect user to the RestrictRedirect page
                          $wgOut->redirect($CASAuth["RestrictRedirect"]);
                          return true;
			}

                        //$attributes = phpCAS::getAttributes();

                        error_log("USER ID=".$u->getId());

                        // Create a new account if the user does not exists
                        if (isset($u) && $u->getID() == 0 && $CASAuth["CreateAccounts"]) {
                          //$u = User::createNew($username);
                          //error_log("CREATE_ACCOUNT");
                          //Get email and realname
                          $realname = casRealNameLookup(phpCAS::getUser());
                          $email    = casEmailLookup(phpCAS::getUser());
                          // Create the user
                          $u->addToDatabase();
                          $u->setRealName($realname);
                          $u->setEmail($email);
                          // PwdSecret is used to salt the username, which is
                          // then used to create an md5 hash which becomes the
                          // password
                          /*$u->setPassword(
                                          md5($username.$CASAuth["PwdSecret"])
                                          );*/
 
                          $u->setToken();
                          $u->saveSettings();
 
                          // Update user count
                          $ssUpdate = new SiteStatsUpdate(0,0,0,0,1);
                          $ssUpdate->doUpdate();
                        }

                        // Login successful
                        /*if ($CASAuth["RememberMe"]) {
                          $u->setOption("rememberpassword", 1);
                        }*/
                        $u->setCookies(null, null, $CASAuth["RememberMe"]);
                        $user = $u;

                        // Redirect if a returnto parameter exists
                        $returnto = $wgRequest->getVal("returnto");
                        if ($returnto) {
                          $target = Title::newFromText($returnto);
                          if ($target) {
                            //action=purge is used to purge the cache
                            $wgOut->redirect($target->getFullUrl());
                          }
                        }
                }
        }

        // Back to MediaWiki home after login
        return true;
}

// Logout
function casLogout() {
        global $CASAuth;
        global $casIsSetUp;
        global $wgRequest;

        // Get returnto value
        $returnto = $wgRequest->getVal("returnto");
        if ($returnto) {
                $target = Title::newFromText($returnto);
                if ($target && $target->getPrefixedDBkey() != SpecialPage::getTitleFor("Userlogout")->getPrefixedDBkey()) {
                        $redirecturl = $target->getFullUrl();
                }
        }

        if(!$casIsSetUp)
                casSetup();

        // Logout from CAS (will redirect user to CAS server)

        //if (isset($redirecturl)) {
                //phpCAS::logoutWithRedirectService($redirecturl);
        //}
        //else {
        //phpCAS::logout();
        //}

        return true; // We won't get here
}

// Remove reset password link and remember password checkbox from preferences page
function casPrefs($user, &$preferences) {
        unset($preferences["password"]);
        unset($preferences["rememberpassword"]);

        return true;
}

// Store the session name and id in a new logout ticket session to be able
// to find it again when single signing-out
function casPostAuth($ticket2logout) {

        // remember the current session name and id
        $old_session_name=session_name();
        $old_session_id=session_id();

        // close the current session for now
        session_write_close();
        session_unset();
        //session_destroy();

        // create a new session where we'll store the old session data
        session_name("casauthssoutticket");
        session_id(preg_replace('/[^a-zA-Z0-9\-]/','',$ticket2logout));
        session_start();

        $_SESSION["old_session_name"] = $old_session_name;
        $_SESSION["old_session_id"] = $old_session_id;

        // close the ssout session again
        session_write_close();
        session_unset();
       //session_destroy();

        // and open the old session again
        session_name($old_session_name);
        session_id($old_session_id);
        session_start();
}

// The CAS server sent a single sign-out command... let's process it
function casSingleSignOut($ticket2logout) {
        global $CASAuth;

        $session_id = preg_replace('/[^a-zA-Z0-9\-]/','',$ticket2logout);

        // destroy a possible application session created before phpcas
        if(session_id() !== ""){
                session_unset();
                session_destroy();
        }

        // load the ssout session
        session_name("casauthssoutticket");
        session_id($session_id);
        session_start();

        // extract the user session data
        $old_session_name = $_SESSION["old_session_name"];
        $old_session_id = $_SESSION["old_session_id"];

        // close the ssout session again
        session_unset();
        session_destroy();

        // load the user session
        session_name($old_session_name);
        session_id($old_session_id);
        session_start();

        // set the flag that the user session is to be closed
        $_SESSION['wsCASLoggedOut'] = true;

        // close the user session again
        session_write_close();
        session_unset();
        session_destroy();
}

function casSetup() {
        global $CASAuth;
        global $casIsSetUp;

        // Make the session persistent so that phpCAS doesn't change the session id
        $globalSession = MediaWiki\Session\SessionManager::getGlobalSession();
        $globalSession->persist();
		
        require_once($CASAuth["phpCAS"]."/CAS.php");
        phpCAS::client($CASAuth["Version"], $CASAuth["Server"], $CASAuth["Port"], $CASAuth["Url"], false);
        phpCAS::setSingleSignoutCallback('casSingleSignOut');
        phpCAS::setPostAuthenticateCallback('casPostAuth');
        phpCAS::handleLogoutRequests(true,isset($CASAuth["LogoutServers"])?$CASAuth["LogoutServers"]:false);
        phpCAS::setNoCasServerValidation();
	phpCAS::setNoClearTicketsFromUrl();
	phpCAS::setServerServiceValidateURL("https://".$CASAuth["Server"]."/cas/laxValidate?assuranceLevel=LOW");

        $casIsSetUp = true;
}
