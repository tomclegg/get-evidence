#!/usr/bin/php
<?php
    ;

// Copyright 2010, 2011 Clinical Future, Inc.
// Authors: see git-blame(1)

if ($_SERVER["argc"] < 2 || $_SERVER["argc"] > 3)
    {
	die ("Usage: ".$_SERVER["argv"][0]." variantid-frequency.tsv [tag]\n");
    }

$rundir = getcwd();
chdir ('public_html');
require_once 'lib/setup.php';
chdir ($rundir);


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


theDb()->query ("CREATE TEMPORARY TABLE import_variant_f (
 variant_id VARCHAR(32),
 variant_name VARCHAR(64) NOT NULL,
 chr VARCHAR(12),
 start BIGINT UNSIGNED,
 end BIGINT UNSIGNED,
 variant_alleles VARCHAR(32),
 variant_count VARCHAR(32),
 covered_count BIGINT UNSIGNED NOT NULL
 )");


print "Loading ".$_SERVER["argv"][1]."...";
$q = theDb()->query ("LOAD DATA LOCAL INFILE ?
	 INTO TABLE import_variant_f
	 FIELDS TERMINATED BY '\t'
	 LINES TERMINATED BY '\n'",
		     array ($_SERVER["argv"][1]));
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Looking up missing variant ids...";
$q = theDb()->query ("UPDATE import_variant_f SET variant_id=NULL where variant_id='unknown'");
$q = theDb()->query ("ALTER TABLE import_variant_f CHANGE variant_id variant_id BIGINT UNSIGNED");
$q = theDb()->query ("ALTER TABLE import_variant_f ADD INDEX(variant_id)");
$q = theDb()->query ("ALTER TABLE import_variant_f ADD INDEX(variant_name)");
$q = theDb()->query ("UPDATE import_variant_f SET variant_name = UPPER(replace(variant_name,'*','X'))");
$q = theDb()->query ("CREATE TEMPORARY TABLE v_id_name AS SELECT variant_id, if(variant_gene is null,concat('RS',variant_rsid),upper(concat(variant_gene,'-',variant_aa_del,variant_aa_pos,variant_aa_ins))) variant_name FROM variants");
$q = theDb()->query ("ALTER TABLE v_id_name ADD INDEX(variant_name)");
$q = theDb()->query ("UPDATE import_variant_f i
 LEFT JOIN v_id_name v ON v.variant_name = i.variant_name
 SET i.variant_id = v.variant_id
 WHERE i.variant_id IS NULL");
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";


$tag = $_SERVER["argc"] == 3 ? $_SERVER["argv"][2] : "GET-Evidence";
print "Copying data into real variant_population_frequency table...";
theDb()->query ("START TRANSACTION");
theDb()->query ("DELETE FROM variant_population_frequency WHERE dbtag=?", array($tag));
theDb()->query ("REPLACE INTO variant_population_frequency
 (variant_id, dbtag, chr, start, end, genotype, num, denom)
 SELECT variant_id, ?, chr, start, end, variant_alleles, variant_count, covered_count
 FROM import_variant_f
 WHERE variant_id>0",
		array ($tag));
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";

theDb()->query ("COMMIT");
if (theDb()->isError($q)) die ($q->getMessage());

theDb()->query ("DROP TEMPORARY TABLE import_variant_f");
if (theDb()->isError($q)) die ($q->getMessage());

?>
