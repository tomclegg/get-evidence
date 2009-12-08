<?php

require_once "lib/setup.php";
require_once "lib/openid.php";

session_start();
$_SESSION["auth_url"] = $_REQUEST["auth_url"];
openid_try($_REQUEST["auth_url"]);
?>