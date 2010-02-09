#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." omim.tsv\n");
    }

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/genomes.php';
require_once 'lib/openid.php';
require_once 'lib/bp.php';

ini_set ("output_buffering", FALSE);
ini_set ("memory_limit", 67108864);

genomes_create_tables ();
openid_login_as_robot ("OMIM Importing Robot");

print "Creating temporary table...";
theDb()->query ("CREATE TEMPORARY TABLE omim_a (
  `phenotype` VARCHAR(255) NOT NULL,
  `gene` VARCHAR(12) NOT NULL,
  `amino_acid` VARCHAR(8) NOT NULL,
  `codon` INT NOT NULL,
  `word_count` INT,
  `allelic_variant_id` VARCHAR(24)
)");
print "\n";


print "Importing data...";
theDb()->query ("LOAD DATA LOCAL INFILE ? INTO TABLE omim_a FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'",
		array ($_SERVER["argv"][1]));

theDb()->query ("ALTER TABLE omim_a ADD variant_id BIGINT UNSIGNED");
theDb()->query ("ALTER TABLE omim_a ADD aa_from CHAR(4)");
theDb()->query ("ALTER TABLE omim_a ADD aa_to CHAR(4)");
theDb()->query ("ALTER TABLE omim_a ADD url VARCHAR(255)");
theDb()->query ("ALTER TABLE omim_a ADD INDEX(gene,aa_from,codon,aa_to)");
print "\n";


print "Cleaning up gene/aa encoding...";
theDb()->query ("
UPDATE omim_a
SET gene = UPPER(gene),
 aa_from = SUBSTRING(amino_acid,1,3),
 aa_to = IF(SUBSTRING(amino_acid,5,4)='TERM','Stop',SUBSTRING(amino_acid,5,3))
");
print theDb()->affectedRows();
print "\n";


print "Looking up variant ids...";
theDb()->query ($update_variant_id_sql = "
UPDATE omim_a, variants
SET omim_a.variant_id=variants.variant_id
WHERE variants.variant_gene = omim_a.gene
 AND variants.variant_aa_from = omim_a.aa_from
 AND variants.variant_aa_to = omim_a.aa_to
 AND variants.variant_aa_pos = omim_a.codon
");
print theDb()->affectedRows();
print "\n";


print "Adding variants...";
$q = theDb()->query ("
SELECT concat(gene,' ',aa_from,codon,aa_to) AS gene_aa_change
FROM omim_a
WHERE variant_id IS NULL
GROUP BY gene,aa_from,codon,aa_to");
$n=0;
$did = array();
while ($row =& $q->fetchRow())
    {
	if (isset ($did[$row["gene_aa_change"]]))
	    continue;

	// create the variant, and an "initial edit/add" row in edits table
	$variant_id = evidence_get_variant_id ($row["gene_aa_change"],
					       false, false, false,
					       true);
	$edit_id = evidence_get_latest_edit ($variant_id,
					     0, 0,
					     true);
	$did[$row["gene_aa_change"]] = 1;

	++$n;
	if ($n % 100 == 0)
	    print "$n...";
    }
print "$n\n";


print "Looking up variant ids again...";
theDb()->query ($update_variant_id_sql);
print theDb()->affectedRows();
print "\n";


print "Editing \"unknown\" variants to \"likely pathogenic\"...";
$timestamp = theDb()->getOne("SELECT NOW()");
$q = theDb()->query ("INSERT INTO edits
	(variant_id, genome_id, article_pmid, is_draft,
	 previous_edit_id, variant_dominance, variant_impact,
	 summary_short, summary_long,
	 edit_oid, edit_timestamp)
	SELECT DISTINCT s.variant_id, 0, 0, 0,
	 edit_id, variant_dominance, ?,
	 summary_short, summary_long,
	 ?, ?
	FROM omim_a
	LEFT JOIN snap_latest s ON omim_a.variant_id=s.variant_id AND s.article_pmid=0 AND s.genome_id=0
	WHERE s.variant_impact='none'",
		     array ('likely pathogenic',
			    getCurrentUser("oid"), $timestamp));
if (theDb()->isError($q)) { print $q->getMessage(); print "..."; }
print theDb()->affectedRows();
print "\n";


print "Copying edits to snap_latest...";
theDb()->query ("REPLACE INTO snap_latest SELECT * FROM edits WHERE edit_oid=? AND edit_timestamp=?",
		array (getCurrentUser("oid"), $timestamp));
print theDb()->affectedRows();
print "\n";


print "Filling in url field...";
theDb()->query ("
UPDATE omim_a
SET url = CONCAT('http://www.ncbi.nlm.nih.gov/entrez/dispomim.cgi?id=',SUBSTRING_INDEX(SUBSTRING_INDEX(allelic_variant_id,'omim:',-1),'.',1))
WHERE allelic_variant_id LIKE 'omim:%.%'
");
print theDb()->affectedRows();
print "\n";


print "Updating variant_external...";
theDb()->query ("LOCK TABLES variant_external WRITE");
theDb()->query ("DELETE FROM variant_external WHERE tag='OMIM'");
theDb()->query ("INSERT INTO variant_external
 (variant_id, tag, content, url, updated)
 SELECT variant_id, 'OMIM', phenotype, url, NOW()
 FROM omim_a
 WHERE variant_id > 0");
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


if (getenv("DEBUG"))
    {
	theDb()->query ("DROP TABLE IF EXISTS omim_last");
	theDb()->query ("CREATE TABLE IF NOT EXISTS omim_last LIKE omim_a");
	theDb()->query ("INSERT INTO omim_last SELECT * FROM omim_a");
    }

?>
