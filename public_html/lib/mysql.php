<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

require_once "DB.php";

function &theDb() { global $gDb; return $gDb; }
function reconnectDb() {
    global $gDb;
    global $gDsn;
    @$gDb->disconnect();
    $gDb = DB::connect ($gDsn);
    if (DB::isError($gDb))
	die ($gDb->getMessage());
}

$gDsn = "mysql://$gDbUser:$gDbPassword@$gDbHost/$gDbDatabase";
$gDb = DB::connect ($gDsn);
if (DB::isError ($gDb)) die (theDb()->getMessage());

theDb()->setFetchMode (DB_FETCHMODE_ASSOC);
theDb()->query ("SET CHARACTER SET 'utf8'");

?>
