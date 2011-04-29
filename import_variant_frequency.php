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
 chr VARCHAR(12),
 start BIGINT UNSIGNED,
 end BIGINT UNSIGNED,
 ref_allele VARCHAR(12),
 ref_count BIGINT UNSIGNED NOT NULL,
 variant_alleles VARCHAR(32),
 variant_counts VARCHAR(32),
 variant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY
 )");


$q = theDb()->query ("LOAD DATA LOCAL INFILE ?
	 INTO TABLE import_variant_f
	 FIELDS TERMINATED BY '\t'
	 LINES TERMINATED BY '\n'",
		     array ($_SERVER["argv"][1]));
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";

print "merging (adding) variant allele/sequence counts (e.g., '1,1,2' -> '4') ... ";
$all = theDb()->getAll("SELECT variant_id, variant_counts FROM import_variant_f WHERE variant_counts LIKE '%,%'");
$did = 0;
foreach ($all as $row) {
    $tot = 0;
    foreach (explode(',', $row['variant_counts']) as $c) {
	$tot += $c;
    }
    theDb()->query ("UPDATE import_variant_f SET variant_counts=? WHERE variant_id=?",
		    array ($tot, $row['variant_id']));
    ++$did;
}
print "$did\n";

$tag = $_SERVER["argc"] == 3 ? $_SERVER["argv"][2] : "GET-Evidence";
print "Copying data into real variant_population_frequency table...";
theDb()->query ("LOCK TABLES variant_population_frequency WRITE");
theDb()->query ("DELETE FROM variant_population_frequency WHERE dbtag=?", array($tag));
theDb()->query ("REPLACE INTO variant_population_frequency
 (variant_id, dbtag, chr, start, end, genotype, num, denom)
 SELECT variant_id, ?, chr, start, end, variant_alleles, variant_counts, variant_counts+ref_count
 FROM import_variant_f
 WHERE variant_id>0",
		array ($tag));
print theDb()->affectedRows();
print "\n";

theDb()->query ("UNLOCK TABLES");

theDb()->query ("DROP TEMPORARY TABLE import_variant_f");

?>
