<?php

require_once ("lib/config-default.php");
require_once ("config.php");
require_once ("lib/mysql.php");
require_once ("lib/evidence.php");
require_once ("lib/editable.php");

global $gOut;
$gOut = array("site_title" => "Evidence Base");

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

?>
