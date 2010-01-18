#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." genenames.txt\n");
    }
$fh = fopen ($_SERVER["argv"][1], "r");


chdir ('public_html');
require_once 'lib/setup.php';


ini_set ("memory_limit", pow(2,27));


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


print "Reading input...";
$g_sql = "";
$g_sql_param = array();
$in = 0;
$out = 0;
while (($line = fgets ($fh)) !== FALSE) {
    if (ereg ("^HGNC ID", $line)) // header
	continue;
    ++$in;
    $f = explode ("\t", rtrim($line, "\n"));
    if ($f[4] == "")		// no aliases listed
	continue;
    $canonical = $f[1];
    foreach (explode (",", $f[4]) as $aka) {
	$aka = trim($aka);
	if ($aka == "")
	    continue;
	$g_sql .= "(?, ?), ";
	array_push ($g_sql_param, $aka, $canonical);
	++$out;
    }
}
print "$in inputs, $out outputs\n";
if (!$out)
    exit;


print "Importing to database...";
theDb()->query ("DELETE FROM gene_canonical_name");
$q = theDb()->query ("INSERT IGNORE INTO gene_canonical_name (aka, official) VALUES "
		     .ereg_replace(', $', '', $g_sql),
		     $g_sql_param);
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "\n";

?>
