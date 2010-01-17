#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." variant_locations.tsv\n");
    }

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/genomes.php';
require_once 'lib/openid.php';
require_once 'lib/bp.php';

ini_set ("output_buffering", FALSE);
ini_set ("memory_limit", 67108864);


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


print "Creating temporary table...";
$q = theDb()->query ("CREATE TEMPORARY TABLE var_loc (
 chr CHAR(6) NOT NULL,
 chr_pos INT UNSIGNED NOT NULL,
 allele CHAR(1) NOT NULL,
 rsid BIGINT UNSIGNED,
 gene_aa VARCHAR(32) NOT NULL,
 INDEX (chr,chr_pos,allele)
)");
if (theDb()->isError($q)) die ($q->getMessage());
print "\n";


print "Importing data...";
$q = theDb()->query ("LOAD DATA LOCAL INFILE ? INTO TABLE var_loc
 FIELDS TERMINATED BY '\t'
 LINES TERMINATED BY '\n'",
		array ($_SERVER["argv"][1]));
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";


print "Copying to variant_locations...";
$q = theDb()->query ("REPLACE INTO variant_locations
 (chr, chr_pos, allele, rsid, gene_aa)
 SELECT chr, chr_pos, allele, rsid, gene_aa
 FROM var_loc");
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";

?>
