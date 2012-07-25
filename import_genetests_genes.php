#!/usr/bin/php
<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." genetests_genes.txt\n");
    }

chdir ('public_html');
require_once 'lib/setup.php';


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


print "Importing...";
$q = theDb()->query ("LOAD DATA LOCAL INFILE ? INTO TABLE genetests_genes
 FIELDS TERMINATED BY ','
 LINES TERMINATED BY '\n'",
		     array ($_SERVER["argv"][1]));
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Changing to upper-case...";
theDb()->query ("UPDATE genetests_genes SET gene=UPPER(gene)");
print theDb()->affectedRows();
print "\n";

?>
