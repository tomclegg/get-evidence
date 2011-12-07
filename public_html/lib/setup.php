<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

if (get_magic_quotes_gpc())
  {
    foreach ($_GET as $k => $v) { $_GET[$k] = stripslashes($v); }
    foreach ($_POST as $k => $v) { $_POST[$k] = stripslashes($v); }
    foreach ($_COOKIE as $k => $v) { $_COOKIE[$k] = stripslashes($v); }
  }

mb_regex_encoding ("UTF-8");
mb_http_output ("UTF-8");

require_once ("lib/config-default.php");
require_once ("config.php");
require_once ("lib/util.php");
require_once ("lib/mysql.php");
require_once ("lib/evidence.php");
require_once ("lib/editable.php");
require_once ("lib/aa.php");
require_once ("lib/user.php");
require_once ("lib/blosum.php");

global $gOut;
$gOut = array("site_title" => "GET-Evidence",
	      "nosidebar" => false,
	      "noheader" => false,
	      "nofooter" => false);

function go()
{
  if (isset($_REQUEST["want_json"])) {
    header ("Content-type: application/json");
    $GLOBALS["want_json"] = true;
    print "{";
    print_content();
    print "}\n";
  }
  else
    include "lib/template.php";
}

if (isset($_COOKIE) && array_key_exists ("PHPSESSID", $_COOKIE))
  session_start();

// Make sure is_admin flag is up-to-date (otherwise users have to
// logout+login in order to gain or lose admin privileges).

if (isset ($_SESSION) &&
    array_key_exists ("user", $_SESSION) &&
    array_key_exists ("oid", $_SESSION["user"])) {
    $is_admin = theDb()->getOne ("SELECT is_admin FROM eb_users WHERE oid=?",
				 array ($_SESSION["user"]["oid"]));
    if ($_SESSION["is_admin"] != $is_admin)
	$_SESSION["is_admin"] = $is_admin;
}

function getCurrentUser ($param=null)
{
  if (isset ($_SESSION) &&
    array_key_exists ("user", $_SESSION) &&
    array_key_exists ("oid", $_SESSION["user"]) &&
    $_SESSION["user"]["oid"])
    return $param ? $_SESSION["user"][$param] : $_SESSION["user"];
  return null;
}

global $gOpenidEasyProviders;
$gOpenidEasyProviders = array ("https://www.google.com/accounts/o8/id" => "Google",
			       "http://yahoo.com" => "Yahoo");

ini_set ("output_buffering", true);

?>
