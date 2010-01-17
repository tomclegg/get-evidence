#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." ns.json\n");
    }

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/genomes.php';
require_once 'lib/openid.php';
require_once 'lib/bp.php';

ini_set ("output_buffering", FALSE);
ini_set ("memory_limit", 67108864);


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


print "Inserting/replacing rows in allele_frequency...";
$json = file_get_contents ($_SERVER["argv"][1]);
$json = "[" . ereg_replace ("}\n{", "},\n{", $json) . "]";
$in = json_decode ($json);
$done = 0;
foreach ($in as $row) {
    if (!sizeof($row->taf))
	continue;
    if (isset($row->taf->all_n) &&
	$row->taf->all_d > 0 &&
	$row->taf->all_d >= $row->taf->all_n) {
	theDb()->query ("REPLACE INTO allele_frequency
		(chr, chr_pos, allele, dbtag, num, denom)
		VALUES (?, ?, ?, ?, ?, ?)",
			array ($row->chromosome,
			       $row->coordinates,
			       $row->trait_allele,
			       'HapMap',
			       $row->taf->all_n,
			       $row->taf->all_d));
	$done += theDb()->affectedRows();
	print ".";
    }
}
print "$done\n";

?>
