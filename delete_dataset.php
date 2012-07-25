#!/usr/bin/php
<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

if ($_SERVER["argc"] != 2) {
    die ("usage: {$_SERVER['argv'][0]} dataset_id\n");
}
$delete_dataset_id = $_SERVER["argv"][1];

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/genomes.php';
require_once 'lib/openid.php';
require_once 'lib/bp.php';

ini_set ("output_buffering", FALSE);

genomes_create_tables ();
openid_login_as_robot ("Genome Importing Robot");

$timestamp = theDb()->getOne ("SELECT NOW()");

print "Finding variant_occurs rows for this dataset...";
theDb()->query ("
CREATE TEMPORARY TABLE variant_occurs_not
SELECT v.variant_id, v.rsid, v.dataset_id FROM variant_occurs v
WHERE dataset_id=?
", array($delete_dataset_id));
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

print "Entering \"delete\" edits for \"genome\" comment which have no supporting evidence after deleting this dataset and have not been edited by users...";
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

print "Forgetting dataset ever existed...";
theDb()->query ("
DELETE FROM datasets
WHERE dataset_id=?", array ($delete_dataset_id));
print theDb()->affectedRows();
print "\n";
