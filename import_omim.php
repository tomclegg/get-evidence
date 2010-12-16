#!/usr/bin/php
<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

if ($_SERVER["argc"] != 3)
    {
	die ("Usage: ".$_SERVER["argv"][0]." OmimVarLocusIdSNP.bcp morbidmap\n");
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


print "Creating omim locus/id/snp table...";
theDb()->query ("CREATE TEMPORARY TABLE omim_a (
  `omim_id` BIGINT UNSIGNED,
  `x1` INTEGER,
  `x2` INTEGER,
  `gene` VARCHAR(16),
  `aa_from_short` VARCHAR(32),
  `aa_pos` INTEGER,
  `aa_to_short` VARCHAR(32),
  `x3` VARCHAR(32),
  `rsid` BIGINT UNSIGNED,
  INDEX(omim_id)
)");
print "\n";

print "Importing OmimVarLocusIdSNP...";
$e = theDb()->query ("LOAD DATA LOCAL INFILE ? INTO TABLE omim_a FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n'",
		array ($_SERVER["argv"][1]));
print theDb()->affectedRows();
print "\n";


print "Creating omim id -> annotation table...";
theDb()->query ("CREATE TEMPORARY TABLE omim_annotation (
  `annotation_text` VARCHAR(128),
  `x1` VARCHAR(32),
  `omim_id` BIGINT UNSIGNED,
  `x2` VARCHAR(32),
  INDEX(omim_id)
)");
print "\n";

print "Importing morbidmap...";
theDb()->query ("LOAD DATA LOCAL INFILE ? INTO TABLE omim_annotation FIELDS TERMINATED BY '|' LINES TERMINATED BY '\n'",
		array ($_SERVER["argv"][2]));
print theDb()->affectedRows();
print "\n";


print "Creating aa_short -> aa_long table...";
$q = theDb()->query ("CREATE TEMPORARY TABLE aa13 (
  `short` CHAR(1) PRIMARY KEY,
  `long` VARCHAR(4)
)");
if (theDb()->isError($q)) { die ($q->getMessage()."\n"); }
foreach ($aa_13 as $short => $long) {
    theDb()->query ("INSERT INTO aa13 VALUES (?, ?)", array ($short, $long));
}
print "\n";


print "Adding columns...";
theDb()->query ("ALTER TABLE omim_a ADD variant_id BIGINT UNSIGNED, ADD INDEX(variant_id)");
theDb()->query ("ALTER TABLE omim_a ADD aa_from VARCHAR(255)");
theDb()->query ("ALTER TABLE omim_a ADD aa_to VARCHAR(255)");
theDb()->query ("ALTER TABLE omim_a ADD url VARCHAR(255)");
theDb()->query ("ALTER TABLE omim_a ADD INDEX(gene,aa_from,aa_pos,aa_to)");
print "\n";


print "Filling in url field...";
theDb()->query ("
UPDATE omim_a
SET url = CONCAT('http://www.ncbi.nlm.nih.gov/omim/',omim_id)
");
print theDb()->affectedRows();
print "\n";


print "Converting short to long AA (from)...";
$q = theDb()->query ("
UPDATE omim_a
 LEFT JOIN aa13 ON aa13.short = omim_a.aa_from_short
SET gene = UPPER(TRIM(gene)),
 aa_from = aa13.long
");
if (theDb()->isError($q)) { die ($q->getMessage()."\n"); }
print theDb()->affectedRows();
print "\n";


print "Converting short to long AA (to)...";
$q = theDb()->query ("
UPDATE omim_a
 LEFT JOIN aa13 ON aa13.short = omim_a.aa_to_short
SET
 aa_to = aa13.long
");
if (theDb()->isError($q)) { die ($q->getMessage()."\n"); }
print theDb()->affectedRows();
print "\n";


print "Converting gene names to canonical gene names...";
$q = theDb()->query ("
UPDATE omim_a
LEFT JOIN gene_canonical_name ON aka = gene
SET gene = official
WHERE official IS NOT NULL
");
if (theDb()->isError($q)) { die ($q->getMessage()."\n"); }
print theDb()->affectedRows();
print "\n";


print "Looking up variant ids...";
theDb()->query ($update_variant_id_sql = "
UPDATE omim_a, variants
SET omim_a.variant_id=variants.variant_id
WHERE variants.variant_gene = omim_a.gene
 AND variants.variant_aa_from = omim_a.aa_from
 AND variants.variant_aa_to = omim_a.aa_to
 AND variants.variant_aa_pos = omim_a.aa_pos
");
print theDb()->affectedRows();
print "\n";


print "Looking up variant ids by rsid...";
theDb()->query ("
UPDATE omim_a, variants
SET omim_a.variant_id=variants.variant_id
WHERE omim_a.variant_id IS NULL
 AND variants.variant_rsid IS NOT NULL
 AND omim_a.rsid = variants.variant_rsid
");
print theDb()->affectedRows();
print "\n";


print "Deleting rows with no/invalid AA change...";
theDb()->query ("DELETE FROM omim_a WHERE variant_id IS NULL AND (aa_from IS NULL OR aa_to IS NULL OR aa_pos IS NULL OR aa_pos<=0)");
print theDb()->affectedRows();
print "\n";


print "Adding variants...";
$q = theDb()->query ("
SELECT concat(gene,' ',aa_from,aa_pos,aa_to) AS gene_aa_change
FROM omim_a
WHERE variant_id IS NULL
GROUP BY gene,aa_from,aa_pos,aa_to");
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
					     0, 0, 0,
					     true);
	$did[$row["gene_aa_change"]] = 1;
	print "\n".$row["gene_aa_change"]." -> $variant_id $edit_id ...";

	++$n;
	if ($n % 100 == 0)
	    print "$n...";
    }
print "$n\n";


print "Looking up variant ids again...";
theDb()->query ($update_variant_id_sql);
print theDb()->affectedRows();
print "\n";


print "Editing \"unknown\" variants to \"pathogenic\"...";
$timestamp = theDb()->getOne("SELECT NOW()");
$q = theDb()->query ("INSERT INTO edits
	(variant_id, genome_id, article_pmid, disease_id, is_draft,
	 previous_edit_id, variant_dominance, variant_quality, variant_quality_text,
	 summary_short, summary_long,
	 variant_impact, edit_oid, edit_timestamp)
	SELECT DISTINCT s.variant_id, 0, 0, 0, 0,
	 edit_id, variant_dominance, variant_quality, variant_quality_text,
	 summary_short, summary_long,
	 ?, ?, ?
	FROM omim_a
	LEFT JOIN snap_latest s ON omim_a.variant_id=s.variant_id AND s.article_pmid=0 AND s.genome_id=0 AND s.disease_id=0
	WHERE s.variant_impact='none'",
		     array ('pathogenic',
			    getCurrentUser("oid"), $timestamp));
if (theDb()->isError($q)) { print $q->getMessage(); print "..."; }
print theDb()->affectedRows();
print "\n";


print "Copying edits to snap_latest...";
theDb()->query ("REPLACE INTO snap_latest SELECT * FROM edits WHERE edit_oid=? AND edit_timestamp=?",
		array (getCurrentUser("oid"), $timestamp));
print theDb()->affectedRows();
print "\n";


print "Updating variant_external...";
theDb()->query ("LOCK TABLES variant_external WRITE");
theDb()->query ("DELETE FROM variant_external WHERE tag='OMIM'");
theDb()->query ("INSERT INTO variant_external
 (variant_id, tag, content, url, updated)
 SELECT variant_id, 'OMIM', annotation_text, url, NOW()
 FROM omim_a
 LEFT JOIN omim_annotation ON omim_a.omim_id = omim_annotation.omim_id
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
