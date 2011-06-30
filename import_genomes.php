#!/usr/bin/php
<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/genomes.php';
require_once 'lib/openid.php';
require_once 'lib/bp.php';

ini_set ("output_buffering", FALSE);

if ($_SERVER["argc"] == 2)
    $want_genome = $_SERVER["argv"][1];

genomes_create_tables ();
openid_login_as_robot ("Genome Importing Robot");

theDb()->query ("CREATE TEMPORARY TABLE import_genomes_tmp (
 variant_id BIGINT UNSIGNED NOT NULL,
 genome_id BIGINT UNSIGNED NOT NULL,
 chr CHAR(6) NOT NULL,
 chr_pos INT UNSIGNED NOT NULL,
 trait_allele CHAR(1),
 taf FLOAT,
 rsid BIGINT UNSIGNED,
 dataset_id VARCHAR(16) NOT NULL,
 zygosity ENUM('heterozygous','homozygous') NOT NULL DEFAULT 'heterozygous',
 UNIQUE(variant_id,dataset_id),
 INDEX(dataset_id),
 INDEX(chr,chr_pos))");
theDb()->query ("CREATE TEMPORARY TABLE imported_datasets (
 dataset_id VARCHAR(16) NOT NULL,
 UNIQUE(dataset_id))");

// Dump current list of variants into import_genomes_tmp table

$and = "";
$params = array ($pgp_data_user, $public_data_user);
if (isset ($want_genome)) {
    if (strlen($want_genome) == 40)
	$and = "AND shasum=?";
    else
	$and = "AND private_genome_id=?";
    $params[] = $want_genome;
}
$sql = "SELECT * FROM private_genomes WHERE oid IN (?,?) $and";
$public_genomes = theDb()->getAll ($sql, $params);

foreach ($public_genomes as $g) {
    $datadir = "$gBackendBaseDir/upload/{$g['shasum']}-out";
    print "Importing genome {$g['private_genome_id']} sha {$g['shasum']} nick \"{$g['nickname']}\" uploaded by {$g['oid']}\n";

    // FIXME: add global_human_id column to the private_genomes table
    if (isset ($g['global_human_id']) && 0 < strlen($g['global_human_id']))
	$global_human_id = $g['global_human_id'];
    else {
	print "No global_human_id.  Skipping.\n";
	continue;
    }

    $fh = fopen ("$datadir/get-evidence.json", "r");
    if (!$fh) { print "open($datadir/get-evidence.json) failed.\n"; continue; }
    if (file_exists ("$datadir/lock")) { print "Skipping because backend is still processing.\n"; continue; }

    $genome_id = evidence_get_genome_id ($global_human_id);
    $dataset_id = substr($g['shasum'], 0, 16);
    print "Using dataset_id $dataset_id\n";
    $sex = '';			// TODO: store this somewhere? figure it out from chrX/Y?

    theDb()->query ("UPDATE genomes SET name=? WHERE genome_id=?",
		    array ($g['nickname'], $genome_id));
    theDb()->query ("REPLACE INTO datasets SET dataset_id=?, genome_id=?, sex=?, dataset_url=?",
		    array ($dataset_id, $genome_id, $sex,
			   "/genomes?{$g['private_genome_id']}"));

    $ops = 0;
    $count_existing_variants = 0;
    $count_new_variants = 0;
    while (($line = fgets ($fh)) !== false) {
	++$ops;
	if ($ops % 10000 == 0)
	    print $ops;
	else if ($ops % 1000 == 0)
	    print ".";
	$jvariant = json_decode($line, true);
	if (!$jvariant) {
	    print "(skipping unparseable JSON at line $ops)\n";
	    continue;
	}
	if (isset($jvariant["gene"]) && isset($jvariant["amino_acid_change"]))
	    $variant_names = array($jvariant["gene"] . " " . $jvariant["amino_acid_change"]);
	else if (isset($jvariant["dbSNP"]))
	    $variant_names = explode(',', $jvariant["dbSNP"]);
	else {
	    if ($jvariant["GET-Evidence"])
		print "(Why is this here?) $line";
	    continue;
	}
	foreach ($variant_names as $variant_name) {
	    $rsid = null;
	    if (preg_match ('/^rs(\d+)$/', $variant_name, $regs) ||
		(isset ($jvariant["dbSNP"]) && preg_match ('/^(?:rs)?(\d+)/', $jvariant["dbSNP"], $regs)))
		$rsid = $regs[1];
	    $variant_id = evidence_get_variant_id ($variant_name);
	    if ($variant_id)
		++$count_existing_variants;
	    else {
		// Create the variant, and an "initial edit/add" row in edits table
		$variant_id = evidence_get_variant_id ($variant_name,
						       false, false, false,
						       true);
		if (!$variant_id) {
		    error_log ("failed to add variant: $variant_name");
		    continue;
		}
		++$count_new_variants;
		$edit_id = evidence_get_latest_edit ($variant_id,
						     0, 0, 0,
						     true);
	    }

	    $taf = null;
	    if (isset($jvariant["num"]) && $jvariant["denom"]>0)
		// FIXME: taf is no longer in JSON so downstream needs to be updated before it can be used
		$taf = $jvariant["num"] / $jvariant["denom"];

	    $allele = $jvariant["genotype"];
	    $zygosity = preg_match ('{^[ACGT]$}', $allele) ? "homozygous" : "heterozygous";
	    $trait_allele = preg_replace ('{[^ACGT]|'.$jvariant["ref_allele"].'}', '', $jvariant["genotype"]);
	    if (strlen($trait_allele) > 1) {
		$trait_allele = bp_flatten ($trait_allele); // compound het
		$taf = null;
	    }

	    $ok = theDb()->query ("INSERT IGNORE INTO import_genomes_tmp SET variant_id=?, genome_id=?, chr=?, chr_pos=?, trait_allele=?, taf=?, rsid=?, dataset_id=?, zygosity=?",
				  array ($variant_id,
					 $genome_id,
					 $jvariant["chromosome"],
					 $jvariant["coordinates"],
					 $trait_allele,
					 $taf,
					 $rsid,
					 $dataset_id,
					 $zygosity));
	    if (theDb()->isError($ok)) die ($line."\n".$ok->getMessage()."\n");
	}
    }
    print "\n$count_existing_variants existing and $count_new_variants new variants.\n";
    fclose ($fh);

    print "\nReading ns.gff[.gz].\n";
    if (file_exists ("$datadir/ns.gff.gz"))
	$fh = gzopen ($nsfile = "$datadir/ns.gff.gz", "r");
    else
	$fh = fopen ($nsfile = "$datadir/ns.gff", "r");
    if (!$fh) { print "open($nsfile) failed.\n"; continue; }
    if (file_exists ("$datadir/lock")) { print "Skipping because backend is still processing.\n"; continue; }
    $ops = 0;
    $count_existing_variants = 0;
    $count_new_variants = 0;
    while (($line = fgets ($fh)) !== false) {
	if ($line[0] == "#")
	    continue;
	++$ops;
	if ($ops % 100000 == 0)
	    print $ops;
	else if ($ops % 10000 == 0)
	    print ".";
	$gff = explode ("\t", $line);

	$rsid = null;
	if (preg_match ('{db_xref dbsnp(?:\.\d+)?:rs(\d+)}', $gff[8], $regs))
	    $rsid = $regs[1];

	if (preg_match ('{amino_acid ([^;\n]+)}', $gff[8], $regs))
	    $variant_names = explode ("/", $regs[1]);
	else if ($rsid)
	    // If we wanted to add all dbsnp variants, we would do...
	    // $variant_names = array ("rs$rsid");
	    // ...but we don't.
	    continue;
	else
	    continue;

	if (preg_match ('{alleles ([A-Z,/]+)}', $gff[8], $regs))
	    $allele = $regs[1];
	else continue;

	if (preg_match ('{ref_allele ([ACGT])}', $gff[8], $regs))
	    $ref_allele = $regs[1];
	else continue;

	$chromosome = $gff[0];
	$position = $gff[3];

	$zygosity = preg_match ('{^[ACGT]$}', $allele) ? "homozygous" : "heterozygous";
	$trait_allele = preg_replace ('{[^ACGT]|'.$ref_allele.'}', '', $allele);
	if (strlen($trait_allele) > 1) {
	    $trait_allele = bp_flatten ($trait_allele); // compound het
	}

	foreach ($variant_names as $variant_name) {
	    $variant_id = evidence_get_variant_id ($variant_name);
	    if ($variant_id)
		++$count_existing_variants;
	    else if (!preg_match ('{^rs\d+$}', $variant_name)) {
		++$count_new_variants;

		// Create the variant, and an "initial edit/add" row in edits table
		$variant_id = evidence_get_variant_id ($variant_name,
						       false, false, false,
						       true);
		if (!$variant_id) {
		    error_log ("failed to add variant: $variant_name");
		    continue;
		}
		$edit_id = evidence_get_latest_edit ($variant_id,
						     0, 0, 0,
						     true);
	    }
	    $ok = theDb()->query ("INSERT IGNORE INTO import_genomes_tmp SET variant_id=?, genome_id=?, chr=?, chr_pos=?, trait_allele=?, taf=?, rsid=?, dataset_id=?, zygosity=?",
				  array ($variant_id,
					 $genome_id,
					 $chromosome,
					 $position,
					 $trait_allele,
					 $taf,
					 $rsid,
					 $dataset_id,
					 $zygosity));
	    if (theDb()->isError($ok)) die ($line."\n".$ok->getMessage());
	}
    }
    print "\n$count_existing_variants existing and $count_new_variants new variants.\n";
    fclose ($fh);

    theDb()->query ("INSERT INTO imported_datasets SET dataset_id=?",
		    array ($dataset_id));
}

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

print "Making list of {genome,variant} pairs to check...";
$q = theDb()->query ("
CREATE TEMPORARY TABLE check_genome_variant
SELECT DISTINCT genome_id, variant_id
FROM variant_occurs_not v
LEFT JOIN datasets d
 ON d.dataset_id=v.dataset_id");
if (theDb()->isError($q)) print $q->getMessage();
print ($count_check_pairs = theDb()->affectedRows());
print "\n";

print "Entering \"delete\" edits for \"genome\" comment which have no supporting evidence after deleting the disputed rows and have not been edited by users...";
$q = theDb()->query ("
INSERT IGNORE INTO edits
(variant_id, genome_id, article_pmid, previous_edit_id, is_draft, is_delete,
 edit_oid, edit_timestamp)
-- find all variants deleted from these datasets...
SELECT old.variant_id, old.genome_id, 0, old.edit_id, 0, 1, ?, ?
FROM check_genome_variant del
-- ...find 'genome X added by robot' edits in snap_latest...
LEFT JOIN snap_latest old
 ON old.variant_id=del.variant_id
 AND old.article_pmid=0
 AND old.genome_id=del.genome_id
 AND old.disease_id=0
 AND old.edit_oid=?
-- ...find any remaining rows linking this variant to this genome...
LEFT JOIN variant_occurs v
 ON v.variant_id=old.variant_id
 AND v.dataset_id IN (SELECT dataset_id FROM datasets WHERE genome_id = del.genome_id)
-- Submit a 'delete' entry if the entry hasn't been touched since it was added by the robot...
WHERE old.edit_id IS NOT NULL
-- ...and none of the remaining datasets mention the variant
 AND v.variant_id IS NULL
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
