#!/usr/bin/php
<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

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


$official = array();
$donottranslate = array();
print "Reading input...";
$in = 0;
while (($line = fgets ($fh)) !== FALSE) {
    if (ereg ("^HGNC ID", $line)) // header
	continue;
    ++$in;
    $f = explode ("\t", rtrim($line, "\n"));
    $canonical = $f[1];
    $donottranslate[$canonical] = 1;
    if ($f[4] == "")		// no aliases listed
	continue;
    foreach (explode (",", $f[4]) as $aka) {
	$aka = trim($aka);
	if ($aka == "")
	    continue;
	$official[$aka] = $canonical;
    }
}
print "$in inputs\n";

print "Removing mappings for {official name} > {another official name}...";
$did = 0;
foreach ($donottranslate as $gene => $x) {
    if (isset ($official[$gene])) {
	unset ($official[$gene]);
	$did++;
    }
}
print "$did\n";

$compressed = 1;
print "Compressing paths...";
while ($compressed > 0) {
    $compressed = 0;
    foreach ($official as $aka => &$canonical) {
	if ($canonical == $aka) {
	    unset ($official[$aka]);
	    $compressed++;
	}
	else if (isset ($official[$canonical])) {
	    if ($official[$canonical] == $aka) {
		if (strcmp($aka,$canonical) < 0) {
		    $a = $aka;
		    $b = $canonical;
		} else {
		    $a = $canonical;
		    $b = $aka;
		}
		print "\n$aka -> $canonical -> $aka -- choosing $a...";
		unset ($official[$canonical]);
		unset ($official[$aka]);
		$official[$b] = $a;
	    } else {
		$canonical = $official[$canonical];
		$compressed ++;
	    }
	}
    }
    if ($compressed > 0)
	print "removed $compressed\nCompressing paths...";
}
print "done\n";

$g_sql = "";
$g_sql_param = array();
$out = 0;
foreach ($official as $aka => $canonical) {
    $g_sql .= "(?, ?), ";
    array_push ($g_sql_param, $aka, $canonical);
    ++$out;
}
print "Strung together $out outputs\n";
if (!$out)
    exit;


print "Importing to database...";
theDb()->query ("LOCK TABLES gene_canonical_name WRITE");
theDb()->query ("DELETE FROM gene_canonical_name");
$q = theDb()->query ("INSERT IGNORE INTO gene_canonical_name (aka, official) VALUES "
		     .ereg_replace(', $', '', $g_sql),
		     $g_sql_param);
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "\n";

theDb()->query ("UNLOCK TABLES");

?>
