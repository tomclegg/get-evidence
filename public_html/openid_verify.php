<?php

require_once "lib/setup.php";
require_once "lib/openid.php";

session_start();
openid_verify();
header ("Location: /");
?>