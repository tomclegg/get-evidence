<?php ; // -*- mode: java; c-basic-offset: 2; tab-width: 8; indent-tabs-mode: nil; -*-

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";

$ok = strlen($gBioNotateSecret) && $_REQUEST['oidcookie'] == md5($gBioNotateSecret . $_REQUEST['oid']);

header('Content-type: application/json');
print json_encode(array('OK' => $ok));
