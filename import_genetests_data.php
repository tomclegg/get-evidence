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


print "Adding diseases...";
theDb()->query ("INSERT IGNORE INTO diseases (disease_name) SELECT disease FROM gt");
print theDb()->affectedRows();
print "\n";


print "Looking up disease IDs...";
theDb()->query ("ALTER TABLE gt ADD disease_id BIGINT NOT NULL");
theDb()->query ("UPDATE gt
 LEFT JOIN diseases d
 ON gt.disease = d.disease_name
 SET gt.disease_id = d.disease_id");
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


print "Copying to live table...";
theDb()->query ("LOCK TABLES gene_disease WRITE");
theDb()->query ("DELETE FROM gene_disease WHERE dbtag = ?",
		array ("GeneTests"));
$q = theDb()->query ("INSERT INTO gene_disease
 (gene, disease_id, dbtag)
 SELECT gene, disease_id, ? FROM gt",
		     array ("GeneTests"));
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "\n";

theDb()->query ("UNLOCK TABLES");

?>
