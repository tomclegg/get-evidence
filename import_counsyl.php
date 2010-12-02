#!/usr/bin/php
<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." counsyl.csv\n");
    }

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/genomes.php';
require_once 'lib/openid.php';
require_once 'lib/bp.php';
chdir ('..');

ini_set ("output_buffering", FALSE);
ini_set ("memory_limit", 67108864);

genomes_create_tables ();
openid_login_as_robot ("Counsyl Test Importing Robot");

print "Creating temporary table...";
theDb()->query ("CREATE TEMPORARY TABLE counsyl_a (
  `gene` VARCHAR(16) NOT NULL,
  `aa_change` VARCHAR(12) NOT NULL,
  `type` VARCHAR(12) NOT NULL
)");
print "\n";


print "Importing data...";
$q = theDb()->query ("LOAD DATA LOCAL INFILE ?
 INTO TABLE counsyl_a
 FIELDS TERMINATED BY ','
 LINES TERMINATED BY '\n'
 IGNORE 1 LINES",
		array ($_SERVER["argv"][1]));
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Splitting AA field...";
$q = theDb()->query ("ALTER TABLE counsyl_a
 ADD aa_pos INT,
 ADD aa_from CHAR(4),
 ADD aa_to CHAR(4)");
if (theDb()->isError($q)) print $q->getMessage();
$q = theDb()->query ("UPDATE counsyl_a
 SET aa_pos=SUBSTR(aa_change,2,LENGTH(aa_change)-2),
 aa_from=SUBSTR(aa_change,1,1),
 aa_to=SUBSTR(aa_change,LENGTH(aa_change),1)");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Translating AA to 3-letter symbols...";
foreach (array_keys ($aa_13) as $aa) {
    theDb()->query ("UPDATE counsyl_a SET aa_from=? WHERE aa_from=?",
		    array (aa_long_form($aa), $aa));
    print theDb()->affectedRows();
    print ",";
    theDb()->query ("UPDATE counsyl_a SET aa_to=? WHERE aa_to=?",
		    array (aa_long_form($aa), $aa));
    print theDb()->affectedRows();
    print ",";
}
print "\n";


print "Deleting bogus rows...";
$q = theDb()->query ("DELETE FROM counsyl_a WHERE aa_pos=0 OR LENGTH(aa_from)<3 OR LENGTH(aa_to)<3");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Looking up gene,aa -> variant_id...";
theDb()->query ("ALTER TABLE counsyl_a ADD variant_id BIGINT UNSIGNED, ADD INDEX(variant_id)");
$q = theDb()->query ("UPDATE counsyl_a c
 LEFT JOIN variants v
  ON v.variant_gene=c.gene
  AND v.variant_aa_from=c.aa_from
  AND v.variant_aa_pos=c.aa_pos
  AND v.variant_aa_to=c.aa_to
 set c.variant_id=v.variant_id");
print theDb()->affectedRows();
print "\n";


print "Adding new variants...";
theDb()->query ("ALTER TABLE counsyl_a ADD rownumber SERIAL");
$q = theDb()->query ("SELECT DISTINCT * FROM counsyl_a WHERE variant_id IS NULL");
$added = 0;
while ($row = $q->fetchRow()) {
    ++$added;
    if ($added % 10 == 0) print "$added...";
    $variant_id = evidence_get_variant_id ($row["gene"],
					   $row["aa_pos"],
					   $row["aa_from"],
					   $row["aa_to"],
					   true);
    theDb()->query ("UPDATE counsyl_a SET variant_id=? WHERE rownumber=?",
		    array ($variant_id, $row["rownumber"]));
    $edit_id = evidence_get_latest_edit ($variant_id,
					 0, 0, 0,
					 true);
}
print $added;
print "\n";


print "Creating counsyl_autoscore table...";
$q = theDb()->query ("DROP TABLE IF EXISTS counsyl_autoscore");
$q = theDb()->query ("CREATE TABLE counsyl_autoscore AS
 SELECT gene, aa_from, aa_pos, aa_to, counsyl_a.variant_id, IF(0<LOCATE('\"autoscore\":',flat_summary),substr(flat_summary,locate('\"autoscore\":',flat_summary)+12,1),NULL) autoscore
 FROM counsyl_a
 LEFT JOIN flat_summary ON counsyl_a.variant_id=flat_summary.variant_id");
if (theDb()->isError($q)) print $q->userinfo;
print theDb()->affectedRows();
print "\n";


