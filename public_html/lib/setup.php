<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if (get_magic_quotes_gpc())
  {
    foreach ($_GET as $k => $v) { $_GET[$k] = stripslashes($v); }
    foreach ($_POST as $k => $v) { $_POST[$k] = stripslashes($v); }
    foreach ($_COOKIE as $k => $v) { $_COOKIE[$k] = stripslashes($v); }
  }

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
$gOut = array("site_title" => "GET-Evidence");

function go()
{
  include "lib/template.php";
}

if (isset($_COOKIE) && array_key_exists ("PHPSESSID", $_COOKIE))
  session_start();

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
