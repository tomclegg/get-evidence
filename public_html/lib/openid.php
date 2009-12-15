<?php

ini_set ('include_path',
	 dirname(dirname(dirname(__FILE__))) . "/php-openid-2.1.3"
	 . PATH_SEPARATOR . ini_get('include_path'));

require_once "Auth/OpenID/Consumer.php";
require_once "Auth/OpenID/MySQLStore.php";
require_once "Auth/OpenID/SReg.php";
require_once "Auth/OpenID/AX.php";

function getScheme() {
    $scheme = 'http';
    if (isset($_SERVER['HTTPS']) and $_SERVER['HTTPS'] == 'on') {
        $scheme .= 's';
    }
    return $scheme;
}

function getReturnTo() {
    return sprintf("%s://%s:%s/openid_verify.php",
                   getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT']);
}

function getTrustRoot() {
    return sprintf("%s://%s:%s/",
                   getScheme(), $_SERVER['SERVER_NAME'],
                   $_SERVER['SERVER_PORT']);
}

function openid_try ($url)
{
  $store = new Auth_OpenID_MySQLStore(theDb());
  $store->createTables();
  $consumer = new Auth_OpenID_Consumer ($store);
  $auth_request = $consumer->begin ($url);
  if (!$auth_request)
    {
      $_SESSION["auth_error"] = "Error: not a valid OpenID.";
      header ("Location: ./");
    }
  $sreg_request = Auth_OpenID_SRegRequest::build(array('email'),
						 array('nickname', 'fullname'));
  if ($sreg_request) {
    $auth_request->addExtension($sreg_request);
  }

  // Attribute Exchange (Google ignores Simple Registration)
  // See http://code.google.com/apis/accounts/docs/OpenID.html#Parameters for parameters

  $ax = new Auth_OpenID_AX_FetchRequest;
  $ax->add (Auth_OpenID_AX_AttrInfo::make('http://axschema.org/contact/email',2,1, 'email'));
  $ax->add (Auth_OpenID_AX_AttrInfo::make('http://axschema.org/namePerson/first',1,1, 'firstname'));
  $ax->add (Auth_OpenID_AX_AttrInfo::make('http://axschema.org/namePerson/last',1,1, 'lastname'));
  $auth_request->addExtension($ax);

  if ($auth_request->shouldSendRedirect()) {
    $redirect_url = $auth_request->redirectURL(getTrustRoot(),
					       getReturnTo());

    // If the redirect URL can't be built, display an error
    // message.
    if (Auth_OpenID::isFailure($redirect_url)) {
      die("Could not redirect to server: " . $redirect_url->message);
    } else {
      // Send redirect.
      header("Location: ".$redirect_url);
    }
  } else {
    // Generate form markup and render it.
    $form_id = 'openid_message';
    $form_html = $auth_request->htmlMarkup(getTrustRoot(), getReturnTo(),
					   false, array('id' => $form_id));

    // Display an error if the form markup couldn't be generated;
    // otherwise, render the HTML.
    if (Auth_OpenID::isFailure($form_html)) {
      displayError("Could not redirect to server: " . $form_html->message);
    } else {
      print $form_html;
    }
  }
}

function openid_verify() {
  $consumer = new Auth_OpenID_Consumer (new Auth_OpenID_MySQLStore(theDb()));

  // Complete the authentication process using the server's
  // response.
  $return_to = getReturnTo();
  $response = $consumer->complete($return_to);

  // Check the response status.
  if ($response->status == Auth_OpenID_CANCEL) {
    // This means the authentication was cancelled.
    $msg = 'Verification cancelled.';
  } else if ($response->status == Auth_OpenID_FAILURE) {
    // Authentication failed; display the error message.
    $msg = "OpenID authentication failed: " . $response->message;
  } else if ($response->status == Auth_OpenID_SUCCESS) {
    // This means the authentication succeeded; extract the
    // identity URL and Simple Registration data (if it was
    // returned).
    $openid = $response->getDisplayIdentifier();
    $esc_identity = htmlentities($openid);

    $success = sprintf('You have successfully verified ' .
		       '<a href="%s">%s</a> as your identity.',
		       $esc_identity, $esc_identity);

    if ($response->endpoint->canonicalID) {
      $escaped_canonicalID = htmlentities($response->endpoint->canonicalID);
      $success .= '  (XRI CanonicalID: '.$escaped_canonicalID.') ';
    }

    $sreg_resp = Auth_OpenID_SRegResponse::fromSuccessResponse($response);
    $sreg = $sreg_resp->contents();

    $ax = new Auth_OpenID_AX_FetchResponse();
    $obj = $ax->fromSuccessResponse($response);
    if ($obj) {
      function ax_get ($obj, $url) {
	if (!$obj) return "";
	$x = $obj->get ($url);
	if (is_array ($x) && is_string($x[0])) return $x[0];
	return "";
      }
      if ($x = ax_get($obj, 'http://axschema.org/contact/email')) $sreg["email"] = $x;
      if ($x = ax_get($obj, 'http://axschema.org/namePerson/first'))
	$sreg["fullname"] = $x . " " . ax_get ($obj, 'http://axschema.org/namePerson/last');
    }

    openid_user_update ($openid, $sreg);
    unset ($_SESSION["auth_error"]);
    return true;
  }

  $_SESSION["auth_error"] = $msg;
  return false;
}

function openid_user_update ($openid, $sreg)
{
  openid_create_tables ();
  theDb()->query ('INSERT IGNORE INTO eb_users (oid) values (?)', array ($openid));
  foreach (array ('nickname', 'fullname', 'email') as $key)
    {
      if (array_key_exists ($key, $sreg))
	theDb()->query ("UPDATE eb_users SET $key=? WHERE oid=?",
		     array ($sreg[$key], $openid));
    }
  $user =& theDb()->getRow ('SELECT * FROM eb_users WHERE oid=?', array($openid));
  if (!strlen ($user["nickname"])) $user["nickname"] = $user["fullname"];
  if (!strlen ($user["nickname"])) $user["nickname"] = $user["email"];
  if (!strlen ($user["nickname"])) $user["nickname"] = substr(md5($openid),0,8);
  $_SESSION["user"] = $user;
}

function openid_create_tables ()
{
  theDb()->query ('
CREATE TABLE IF NOT EXISTS eb_users (
  oid VARCHAR(255) NOT NULL PRIMARY KEY,
  nickname VARCHAR(64),
  fullname VARCHAR(128),
  email VARCHAR(128),
  is_admin TINYINT NOT NULL DEFAULT 0
)');
  theDb()->query ('ALTER TABLE eb_users ADD is_admin TINYINT NOT NULL DEFAULT 0');
}
?>
