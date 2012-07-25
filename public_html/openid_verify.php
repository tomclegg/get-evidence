<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

require_once "lib/setup.php";
require_once "lib/openid.php";

session_start();
openid_verify();
if (ereg ("/[^:]*$", $_REQUEST["return_url"], $regs))
  header ("Location: $regs[0]");
else
  header ("Location: /");
?>