#!/usr/bin/php
<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." pph2_getev_scores.tsv [tag]\n");
    }

$rundir = getcwd();
chdir ('public_html');
require_once 'lib/setup.php';
chdir ($rundir);


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


theDb()->query ("CREATE TEMPORARY TABLE import_pph2 (
 variant_name VARCHAR(32) NOT NULL,
 variant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 polyphen2_score FLOAT
 )");


print "Importing {$_SERVER['argv'][1]} ... ";
$q = theDb()->query ("LOAD DATA LOCAL INFILE ?
	 INTO TABLE import_pph2
	 FIELDS TERMINATED BY '\t'
	 LINES TERMINATED BY '\n'",
		     array ($_SERVER["argv"][1]));
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";

print "Attaching annotations for score ranges ... ";
theDb()->query ("ALTER TABLE import_pph2 ADD annotation VARCHAR(64)");
theDb()->query ("UPDATE import_pph2 SET annotation=' (benign)'");
print theDb()->affectedRows() . " total, ";
theDb()->query ("UPDATE import_pph2 SET annotation=' (possibly damaging)' where polyphen2_score >= 0.2");
print theDb()->affectedRows() . " >= 0.2, ";
theDb()->query ("UPDATE import_pph2 SET annotation=' (probably damaging)' where polyphen2_score >= 0.85");
print theDb()->affectedRows() . " >= 0.85\n";

print "Copying data into variant_external ...";
theDb()->query ("LOCK TABLES variant_external WRITE");
theDb()->query ("DELETE FROM variant_external WHERE tag='PolyPhen-2'");
$q = theDb()->query ("REPLACE INTO variant_external
 (variant_id, tag, content, updated)
 SELECT variant_id, ?, concat('Score: ',if(polyphen2_score=1,'1.0',polyphen2_score),annotation), now()
 FROM import_pph2",
		array ('PolyPhen-2'));
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


theDb()->query ("DROP TEMPORARY TABLE import_pph2");

?>
