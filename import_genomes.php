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
require_once 'lib/bp.php';

ini_set ("output_buffering", FALSE);
ini_set ("memory_limit", 67108864);

genomes_create_tables ();
openid_login_as_robot ("Genome Importing Robot");

theDb()->query ("CREATE TEMPORARY TABLE import_genomes_tmp (
 variant_id BIGINT UNSIGNED NOT NULL,
 genome_id BIGINT UNSIGNED NOT NULL,
 chr CHAR(6) NOT NULL,
 chr_pos INT UNSIGNED NOT NULL,
 trait_allele CHAR(1),
 taf TEXT,
 rsid BIGINT UNSIGNED,
 dataset_id VARCHAR(16) NOT NULL,
 zygosity ENUM('heterozygous','homozygous') NOT NULL DEFAULT 'heterozygous',
 INDEX(variant_id,dataset_id),
 INDEX(dataset_id),
 INDEX(chr,chr_pos))");
theDb()->query ("CREATE TEMPORARY TABLE imported_datasets (
 dataset_id VARCHAR(16) NOT NULL,
 UNIQUE(dataset_id))");

// Dump current list of variants into import_genomes_tmp table

print "Importing ";
$ops = 0;
$job2genome = array();
$zygosity = array ('hom' => 'homozygous',
		   'het' => 'heterozygous');
while (($line = fgets ($fh)) !== FALSE)
    {
	++$ops;
	if ($ops % 10000 == 0)
	    print $ops;
	if ($ops % 1000 == 0)
	    print ".";

	list ($gene, $aa_change,
	      $chr, $chr_pos, $rsid,
	      $ref_allele, $alleles, $hom_or_het,
	      $job_id, $global_human_id, $human_name, $sex, $taf)
	    = explode ("\t", ereg_replace ("\r?\n$", "", $line));

	if (!$global_human_id)
	  continue;

	if (!ereg ("^{.+}$", $taf)) {
	  $taf = NULL;
	}
	$trait_allele = ereg_replace("$ref_allele|[^ACGT]", "", $alleles);
	if (strlen($trait_allele) != 1) {
	  // TODO: get trait-o-matic to specify which of these alleles
	  // (if not both) caused the stated AA change, and store
	  // evidence accordingly.  If ref A->C/G and C and G both
	  // result in the same AA change then maybe record it as a
	  // hom?  Maybe even if C/G result in different AA changes
	  // but both are nonsynonymous?  For now, just store C/G as
	  // "S" and let the users figure it out.
	  $trait_allele = bp_flatten ($trait_allele);

	  // TODO: figure out what taf means when given with compound
	  // het (ref A -> C/G)
	  $taf = NULL;
	}

	if ($gene && $aa_change)
	  $variant_name = "$gene $aa_change";
	else if ($rsid)
	  $variant_name = $rsid;
	else
	  continue;
	$variant_id = evidence_get_variant_id ($variant_name);

	if (!$variant_id) {
	    // create the variant, and an "initial edit/add" row in edits table
	    $variant_id = evidence_get_variant_id ($variant_name,
						   false, false, false,
						   true);
	    $edit_id = evidence_get_latest_edit ($variant_id,
						 0, 0, 0,
						 true);
	}
	if (ereg("^(rs?)([0-9]+)$", $rsid, $regs)) $rsid=$regs[2];
	else $rsid=null;

	if (isset($job2genome[$job_id]))
	  $genome_id = $job2genome[$job_id];
	else
	  $genome_id = evidence_get_genome_id ($global_human_id);

	theDb()->query ("INSERT INTO import_genomes_tmp SET variant_id=?, genome_id=?, chr=?, chr_pos=?, trait_allele=?, taf=?, rsid=?, dataset_id=?, zygosity='$zygosity[$hom_or_het]'",
			array ($variant_id, $genome_id, $chr, $chr_pos, $trait_allele, $taf, $rsid, "T/snp/$job_id"));
	if (!isset($job2genome[$job_id])) {
	    theDb()->query ("UPDATE genomes SET name=? WHERE genome_id=?",
			    array ($human_name, $genome_id));
	    theDb()->query ("REPLACE INTO datasets SET dataset_id=?, genome_id=?, sex=?, dataset_url=?",
			    array ("T/snp/$job_id", $genome_id, $sex,
				   "http://snp.med.harvard.edu/results/job/$job_id"));
	    theDb()->query ("INSERT INTO imported_datasets SET dataset_id=?",
			    array ("T/snp/$job_id"));
	    $job2genome[$job_id] = $genome_id;
	}

	// quick mode for testing
	if (getenv("MAX") && $ops >= getenv("MAX"))
	  break;

    }
print "$ops\n";


print "Adding new/updated rows to taf table...";
theDb()->query ("REPLACE INTO taf (chr, chr_pos, allele, taf) SELECT chr, chr_pos, trait_allele, taf FROM import_genomes_tmp WHERE taf IS NOT NULL GROUP BY chr, chr_pos, trait_allele");
print theDb()->affectedRows();
print "\n";


print "Adding {dataset,variant} associations to variant_occurs table...";
theDb()->query ("REPLACE INTO variant_occurs (variant_id, rsid, dataset_id, zygosity, chr, chr_pos, allele) SELECT variant_id, rsid, dataset_id, zygosity, chr, chr_pos, trait_allele FROM import_genomes_tmp");
print theDb()->affectedRows();
print "\n";


// Look for new variants in import_genomes_tmp that aren't in
// snap_latest, and add suitable edits

print "Looking for new {genome,variant} associations and adding them as edits...";
$timestamp = theDb()->getOne ("SELECT NOW()");
theDb()->query ("INSERT INTO edits
	(variant_id, genome_id, article_pmid, disease_id, is_draft, edit_oid, edit_timestamp)
	SELECT DISTINCT i.variant_id, i.genome_id, 0, 0, 0, ?, ?
	FROM import_genomes_tmp i
	LEFT JOIN snap_latest s ON s.variant_id = i.variant_id AND s.article_pmid = '0' AND s.disease_id = '0' AND s.genome_id = i.genome_id
	WHERE s.variant_id IS NULL",
		array (getCurrentUser("oid"), $timestamp));
print theDb()->affectedRows();
print "\n";

print "Pushing new associations to snap_latest...";
theDb()->query ("INSERT IGNORE INTO snap_latest SELECT * FROM edits WHERE edit_oid=? and edit_timestamp=?",
		array (getCurrentUser("oid"), $timestamp));
print theDb()->affectedRows();
print "\n";


// Look for variant+dataset associations in variant_occurs that are no
// longer supported by the latest imported data in import_genomes_tmp

print "Finding variant_occurs rows disputed by this import...";
theDb()->query ("
CREATE TEMPORARY TABLE variant_occurs_not
SELECT v.variant_id, v.rsid, v.dataset_id FROM variant_occurs v
LEFT JOIN imported_datasets
 ON v.dataset_id=imported_datasets.dataset_id
LEFT JOIN datasets d
 ON v.dataset_id=d.dataset_id
LEFT JOIN import_genomes_tmp i
 ON v.variant_id=i.variant_id
 AND d.dataset_id=i.dataset_id
WHERE imported_datasets.dataset_id IS NOT NULL
 AND i.variant_id IS NULL
");
print theDb()->affectedRows();
print "\n";


// Delete the no-longer-occurring variants from variant_occurs

print "Deleting disputed rows from variant_occurs...";
theDb()->query ("DELETE v.*
FROM variant_occurs_not n, variant_occurs v
WHERE n.variant_id=v.variant_id
 AND n.dataset_id=v.dataset_id");
print theDb()->affectedRows();
print "\n";


// For each deleted variant+genome_id assoc, if the latest edit was
// made by this program (i.e., nobody has written any comments about
// this variant+genome_id association), and there is no longer any
// evidence in variant_occurs supporting it, add a "delete" edit and
// remove the entry from snap_latest.

print "Entering \"delete\" edits for \"genome\" comment which have no supporting evidence after deleting those disputed rows and have not been edited by users...";
$q = theDb()->query ("
INSERT IGNORE INTO edits
(variant_id, genome_id, article_pmid, previous_edit_id, is_draft, is_delete,
 edit_oid, edit_timestamp)
SELECT old.variant_id, old.genome_id, 0, old.edit_id, 0, 1, ?, ?
FROM variant_occurs_not del
LEFT JOIN datasets deld
 ON deld.dataset_id=del.dataset_id
LEFT JOIN snap_latest old
 ON old.variant_id=del.variant_id
 AND old.article_pmid=0
 AND old.genome_id=deld.genome_id
 AND old.edit_oid=?
LEFT JOIN variant_occurs v
 ON old.variant_id=v.variant_id
LEFT JOIN datasets d
 ON v.dataset_id=d.dataset_id
 AND d.genome_id=deld.genome_id
WHERE old.edit_id IS NOT NULL
 AND d.dataset_id IS NULL
GROUP BY del.variant_id, deld.genome_id
", array (getCurrentUser("oid"), $timestamp,
	  getCurrentUser("oid")));
if (theDb()->isError($q)) print $q->getMessage();
print ($count_removals = theDb()->affectedRows());
print "\n";

if ($count_removals > 0)
{
  print "Really removing them from snap_latest...";
  theDb()->query ("
DELETE FROM snap_latest
WHERE edit_id IN (SELECT previous_edit_id FROM edits WHERE edit_oid=? AND edit_timestamp=? AND is_delete=1)
", array (getCurrentUser("oid"), $timestamp));
  print theDb()->affectedRows();
  print "\n";
}

// Clean up

if (getenv("DEBUG")) {
  theDb()->query ("DROP TABLE IF EXISTS import_genomes_last");
  theDb()->query ("CREATE TABLE import_genomes_last LIKE import_genomes_tmp");
  theDb()->query ("INSERT INTO import_genomes_last SELECT * FROM import_genomes_tmp");
}

theDb()->query ("DROP TEMPORARY TABLE variant_occurs_not");
theDb()->query ("DROP TEMPORARY TABLE import_genomes_tmp");

?>
