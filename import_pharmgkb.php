#!/usr/bin/php
<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." variantAnnotations.tsv\n");
    }

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/genomes.php';
require_once 'lib/openid.php';
require_once 'lib/bp.php';

ini_set ("output_buffering", FALSE);
ini_set ("memory_limit", 67108864);


print "Creating/updating get-evidence tables...";
genomes_create_tables ();
print "\n";


openid_login_as_robot ("PharmGKB Importing Robot");

$fh = fopen ($_SERVER["argv"][1], "r");
$columns = fgets ($fh);
fclose ($fh);

if (preg_match ('/^Position on hg18\tRSID\t/', $columns)) {
    print "Creating temporary table...";

    $q = theDb()->query ("CREATE TEMPORARY TABLE pharmgkb (
  `chr_pos` VARCHAR(16),
  `rsid` VARCHAR(16),
  `gene_aa` VARCHAR(64),
  `genes` VARCHAR(32),
  `feature` VARCHAR(64),
  `evidence` VARCHAR(64),
  `annotation` TEXT,
  `drugs` VARCHAR(255),
  `drug_classes` VARCHAR(255),
  `diseases` VARCHAR(255),
  `curation_level` VARCHAR(255),
  `pharmgkb_acc_id` VARCHAR(32),
  INDEX(`rsid`)
)");
}
else {
    die ("Unrecognized input format");
}
if (theDb()->isError($q)) print $q->getMessage;
print "\n";


print "Importing data...";
$q = theDb()->query ("LOAD DATA LOCAL INFILE ? INTO TABLE pharmgkb
 FIELDS TERMINATED BY '\t' OPTIONALLY ENCLOSED BY '\"'
 LINES TERMINATED BY '\n'
 IGNORE 1 LINES",
		array ($_SERVER["argv"][1]));
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Changing rsid to a numeric field...";
theDb()->query ("
UPDATE pharmgkb
SET rsid = substr(rsid,3,99)
WHERE rsid like 'rs%'");
print theDb()->affectedRows();
theDb()->query ("
ALTER TABLE pharmgkb
 CHANGE rsid rsid INT UNSIGNED
");
print "\n";


$notdone = 0;
while ($notdone > 0) {
    print "Splitting multiple gene:aa into multiple rows...";
    $q = theDb()->query ("
CREATE TEMPORARY TABLE pharmgkb2 AS
SELECT * FROM pharmgkb
WHERE gene_aa like '%;%'");
    if (theDb()->isError($q)) print $q->getMessage();
    print ($notdone = theDb()->affectedRows());
    print "...";

    $q = theDb()->query ("
UPDATE pharmgkb2
SET gene_aa = substring_index(gene_aa,';',1)");
    if (theDb()->isError($q)) print $q->getMessage();
    print theDb()->affectedRows();
    print "...";

    $q = theDb()->query ("
INSERT INTO pharmgkb SELECT * FROM pharmgkb2");
    if (theDb()->isError($q)) print $q->getMessage();
    print theDb()->affectedRows();

    print "...";
    $q = theDb()->query ("
UPDATE pharmgkb
SET gene_aa=substr(gene_aa,locate(';',gene_aa)+1)
WHERE gene_aa like '%;%'");
    if (theDb()->isError($q)) print $q->getMessage();
    print theDb()->affectedRows();
    print "\n";

    $q = theDb()->query ("DROP TABLE pharmgkb2");
}


print "Prepending 'drugs' field to annotation field...";
$q = theDb()->query ("UPDATE pharmgkb
SET annotation=CONCAT('[',drugs,']\n',annotation)
WHERE length(drugs)>0");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Prepending 'diseases' field to annotation field...";
$q = theDb()->query ("UPDATE pharmgkb
SET annotation=CONCAT('[',diseases,']\n',annotation)
WHERE length(diseases)>0");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Adding 'url' field...";
$q = theDb()->query ("ALTER TABLE pharmgkb ADD url VARCHAR(255)");
$q = theDb()->query ("UPDATE pharmgkb
SET url=substr(evidence,14)
WHERE evidence like 'Web Resource:%'");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "...";

$q = theDb()->query ("ALTER TABLE pharmgkb ADD pmid VARCHAR(16)");
$q = theDb()->query ("UPDATE pharmgkb
SET url=CONCAT('http://www.ncbi.nlm.nih.gov/pubmed/',substr(evidence,11)),
 pmid=substr(evidence,11)
WHERE evidence like 'PubMed ID:%'");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "...";

$q = theDb()->query ("UPDATE pharmgkb
SET url=CONCAT('http://www.ncbi.nlm.nih.gov/projects/SNP/snp_ref.cgi?searchType=adhoc_search&type=rs&rs=rs',rsid) WHERE url IS NULL AND LENGTH(rsid)>0");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Adding variant_id field...";
$q = theDb()->query ("ALTER TABLE pharmgkb ADD variant_id BIGINT");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Looking up rsid -> variant_id...";
$q = theDb()->query ("
UPDATE pharmgkb p
LEFT JOIN variants v
 ON p.rsid = v.variant_rsid
SET p.variant_id = v.variant_id
WHERE p.variant_id IS NULL
 AND v.variant_id IS NOT NULL
");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Adding serial number...";
theDb()->query ("ALTER TABLE pharmgkb ADD pharmgkb_id SERIAL");
print theDb()->affectedRows();
print "\n";


print "Adding variants...";
$q = theDb()->query ("
SELECT gene_aa, rsid, pharmgkb_id, pmid
FROM pharmgkb");
if (theDb()->isError($q)) die ($q->getMessage());
$n=0;
$n_marked_pharma=0;
$did = array();
while ($row =& $q->fetchRow())
    {
	// create the variant, and an "initial edit/add" row in edits table
	$gene_aa = $row["gene_aa"];
	if (eregi ('([a-z0-9]+):([0-9]+) ?([a-z][a-z][a-z])>([a-z][a-z][a-z])', $gene_aa, $r))
	    $gene_aa = "$r[1] $r[3]$r[2]$r[4]";
	else if (eregi ('([a-z0-9]+):([a-z]+)([0-9]+)([a-z]+)',$gene_aa, $r))
	    $gene_aa = "$r[1] $r[2]$r[3]$r[4]";

	$variant_id = evidence_get_variant_id
	    ($gene_aa,
	     false, false, false,
	     true);
	if (!$variant_id && $row["rsid"])
	    $variant_id = evidence_get_variant_id
		("rs".$row["rsid"],
		 false, false, false,
		 true);
	$edit_id = evidence_get_latest_edit
	    ($variant_id,
	     0, 0, 0,
	     true, array ("variant_impact" => "pharmacogenetic"));
	$latestrow = evidence_get_edit ($edit_id);
	if (in_array ($latestrow["variant_impact"],
		      array ("not reviewed", "none", "unknown"))) {
	    $newrow = evidence_generate_edit (null, null, $latestrow);
	    $newrow["variant_impact"] = "pharmacogenetic";
	    $new_edit_id = evidence_save_draft (null, $newrow);
	    evidence_submit ($new_edit_id);
	    ++$n_marked_pharma;
	}
	if (ereg ('^[0-9]+$', $row["pmid"]))
	    evidence_get_latest_edit
		($variant_id,
		 $row["pmid"], 0, 0,
		 true);
	$did[$variant_name] = $variant_id;
	theDb()->query ("UPDATE pharmgkb SET variant_id=? WHERE pharmgkb_id=?",
			array ($variant_id, $row["pharmgkb_id"]));
	++$n;
	if ($n % 100 == 0)
	    print "$n...";
    }
print "$n ($n_marked_pharma existing variants changed to pharmacogenetic)\n";



print "Updating variant_external...";
theDb()->query ("LOCK TABLES variant_external WRITE");
theDb()->query ("DELETE FROM variant_external WHERE tag='PharmGKB'");
$q = theDb()->query ("INSERT INTO variant_external
 (variant_id, tag, content, url, updated)
 SELECT variant_id, 'PharmGKB', annotation, url, NOW()
 FROM pharmgkb
 WHERE variant_id > 0");
if (theDb()->isError($q)) print "[".$q->getMessage()."]";
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


if (getenv("DEBUG"))
    {
	theDb()->query ("DROP TABLE IF EXISTS pharmgkb_last");
	theDb()->query ("CREATE TABLE IF NOT EXISTS pharmgkb_last LIKE pharmgkb");
	theDb()->query ("INSERT INTO pharmgkb_last SELECT * FROM pharmgkb");
    }

?>
