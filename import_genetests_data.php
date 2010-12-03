#!/usr/bin/php
<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." genetests-data.txt\n");
    }
$fh = fopen ($_SERVER["argv"][1], "r");


chdir ('public_html');
require_once 'lib/setup.php';


if (ini_get ("memory_limit") < 134217728)
    ini_set ("memory_limit", 134217728);


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
    $testable = eregi ("clinical", $f[5]) ? 1 : 0;
    $reviewed = $f[6] && $f[6] != "na" ? 1 : 0;
    foreach (explode ("|", $f[1]) as $disease) {
	foreach (explode ("|", $f[4]) as $gene) {
	    $g_sql .= "(?, ?, ?, ?), ";
	    array_push ($g_sql_param, $gene, $disease, $testable, $reviewed);
	    ++$out;
	}
    }
}
print "$in inputs, $out outputs\n";
if (!$out)
    exit;


print "Importing to database...";
theDb()->query ("CREATE TEMPORARY TABLE gt (
 gene VARCHAR(32) NOT NULL,
 disease VARCHAR(64) NOT NULL,
 testable TINYINT NOT NULL,
 reviewed TINYINT NOT NULL,
 UNIQUE `gene_disease` (gene,disease))");
$q = theDb()->query ("INSERT IGNORE INTO gt (gene, disease, testable, reviewed) VALUES "
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


print "Copying to live gene>disease table...";
theDb()->query ("LOCK TABLES gene_disease,gene_canonical_name WRITE");
theDb()->query ("DELETE FROM gene_disease WHERE dbtag = ?",
		array ("GeneTests"));
$q = theDb()->query ("INSERT INTO gene_disease
 (gene, disease_id, dbtag)
 SELECT DISTINCT IF(official is null,gene,official), disease_id, ? FROM gt
 LEFT JOIN gene_canonical_name ON aka=gene",
		     array ("GeneTests"));
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


print "Merging genes using gene_canonical_name...";
theDb()->query ("CREATE TEMPORARY TABLE gt2 (
 gene VARCHAR(32) NOT NULL,
 testable TINYINT NOT NULL,
 reviewed TINYINT NOT NULL,
 INDEX(gene))");
$q = theDb()->query ("INSERT INTO gt2
 (gene, testable, reviewed)
 SELECT gene, testable, reviewed
  FROM gt
  LEFT JOIN gene_canonical_name ON gene=aka
  WHERE aka IS NULL");
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "...";
$q = theDb()->query ("INSERT INTO gt2
 (gene, testable, reviewed)
 SELECT official, testable, reviewed
  FROM gt
  LEFT JOIN gene_canonical_name ON gene=aka
  WHERE official IS NOT NULL");
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "\n";


print "Copying to live genetests table...";
theDb()->query ("LOCK TABLES genetests WRITE");
theDb()->query ("DELETE FROM genetests");
$q = theDb()->query ("INSERT INTO genetests
 (gene, testable, reviewed)
 SELECT gene, max(testable), max(reviewed) FROM gt2 GROUP BY gene");
if (theDb()->isError($q)) die($q->getMessage());
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");

?>
