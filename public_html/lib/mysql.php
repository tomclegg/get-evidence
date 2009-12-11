<?php

global $gDb;

require_once "DB.php";
$gDsn = "mysql://$gDbUser:$gDbPassword@$gDbHost/$gDbDatabase";

$gDb = DB::connect ($gDsn);
if (DB::isError ($gDb)) die ($gDb->getMessage());

$gDb->setFetchMode (DB_FETCHMODE_ASSOC);

function theDb() { global $gDb; return $gDb; }

?>
