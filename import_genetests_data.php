#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." genetests-data.txt\n");
    }
$fh = fopen ($_SERVER["argv"][1], "r");


chdir ('public_html');
require_once 'lib/setup.php';


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


print "Reading input...";
$g_sql = "";
$g_sql_param = array();
$in = 0;
$out = 0;
while (($line = fgets ($fh)) !== FALSE) {
    if (ereg ("^#", $line))	// comment
	continue;
    ++$in;
    $f = explode ("\t", rtrim($line, "\n"));
    if ($f[4] == "na")		// no gene listed
	continue;
    $disease = $f[1];
    foreach (explode ("|", $f[4]) as $gene) {
	$g_sql .= "(?, ?), ";
	array_push ($g_sql_param, $gene, $disease);
	++$out;
    }
}
print "$in inputs, $out outputs\n";
if (!$out)
    exit;


print "Importing to database...";
theDb()->query ("CREATE TEMPORARY TABLE gt (gene VARCHAR(32) NOT NULL, disease VARCHAR(64) NOT NULL, UNIQUE `gene_disease` (gene,disease))");
$q = theDb()->query ("INSERT IGNORE INTO gt (gene, disease) VALUES "
		     .ereg_replace(', $', '', $g_sql),
		     $g_sql_param);
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "\n";


// TODO: create the gene_canonical_name table so this works

print "Looking up canonical gene symbols...";
theDb()->query ("UPDATE gt
 LEFT JOIN gene_canonical_name c
 ON gt.gene=c.aka
 SET gt.gene=c.official
 WHERE c.official IS NOT NULL");
print theDb()->affectedRows();
print "\n";


print "Copying to live table...";
theDb()->query ("LOCK TABLES genetests_gene_disease WRITE");
theDb()->query ("DELETE FROM genetests_gene_disease");
$q = theDb()->query ("INSERT INTO genetests_gene_disease (gene, disease) SELECT gene, disease FROM gt");
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "\n";

theDb()->query ("UNLOCK TABLES");

?>
