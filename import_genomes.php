#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] > 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." [allsnps.tsv]\n");
    }

$fh = 0;			// stdin
if ($_SERVER["argc"] == 2)
    $fh = fopen($_SERVER["argv"][1], "r");

if ($fh === FALSE)
    {
	die ("Can't open ".$_SERVER["argv"][1]."\n");
    }

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/genomes.php';
require_once 'lib/openid.php';

ini_set ("output_buffering", FALSE);

genomes_create_tables ();
openid_login_as_robot ("Genome Importing Robot");

theDb()->query ("CREATE TEMPORARY TABLE import_genomes_tmp (
 variant_id BIGINT UNSIGNED NOT NULL,
 genome_id VARCHAR(16) NOT NULL,
 INDEX(variant_id,genome_id),
 INDEX(genome_id))");

// Dump current list of variants into import_genomes_tmp table

print "Importing ";
$ops = 0;
while (($line = fgets ($fh)) !== FALSE)
    {
	if (++$ops % 1000 == 0)
	    print ".";

	list ($gene, $aa_change,
	      $chr, $chr_pos, $rsid,
	      $ref_allele, $alleles, $hom_or_het,
	      $human_id, $global_human_id, $human_name)
	    = explode ("\t", ereg_replace ("\r?\n$", "", $line));
	$variant_id = evidence_get_variant_id ("$gene $aa_change", false, false, false, true);
	$edit_id = evidence_get_latest_edit ($variant_id, 0, 0, true);
	theDb()->query ("INSERT INTO import_genomes_tmp SET variant_id=?, genome_id=?",
			array ($variant_id, $human_id));
	theDb()->query ("REPLACE INTO genomes SET genome_id=?, global_human_id=?, name=?",
			array ($human_id, $global_human_id, $human_name));

	// quick mode for testing
	//	if ($variant_id >= 1000)
	//	    break;

    }
print "$ops\n";

// Look for new variants in import_genomes_tmp that aren't in
// snap_latest, and add suitable edits

print "Looking for new {genome,variant} associations and adding them as edits";
$timestamp = theDb()->getOne ("SELECT NOW()");
theDb()->query ("INSERT INTO edits
	(variant_id, genome_id, article_pmid, is_draft, edit_oid, edit_timestamp)
	SELECT DISTINCT i.variant_id, i.genome_id, 0, 0, ?, ?
	FROM import_genomes_tmp i
	LEFT JOIN snap_latest s ON s.variant_id = i.variant_id AND s.article_pmid = '0' AND s.genome_id = i.genome_id
	WHERE s.variant_id IS NULL",
		array (getCurrentUser("oid"), $timestamp));
print "\n";

print "Pushing new associations to snap_latest";
theDb()->query ("INSERT IGNORE INTO snap_latest SELECT * FROM edits WHERE edit_oid=? and edit_timestamp=?",
		array (getCurrentUser("oid"), $timestamp));
print "\n";


// Look for variant+genome_id associations in snap_latest where the
// genome_id is listed in import_genomes_tmp, but that particular
// variant is not.  Add "remove" edits for those variant+genome_id
// associations and remove them from snap_latest.

// (TODO)


// Clean up

theDb()->query ("DROP TEMPORARY TABLE import_genomes_tmp");

?>
