<?php

require_once ("lib/config-default.php");
require_once ("config.php");
require_once ("lib/mysql.php");

global $gOut;
$gOut = array("site_title" => "Evidence Base");

function go()
{
  include "lib/template.php";
}

if (isset($_COOKIE) && array_key_exists ("PHPSESSID", $_COOKIE))
  session_start();

?>