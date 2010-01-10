#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if (!getenv ("APIKEY"))
    die ("Please set environment variable APIKEY to your Yahoo BOSS API key.");

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/openid.php';
require_once 'lib/yahoo_boss.php';

ini_set ("output_buffering", FALSE);
ini_set ("memory_limit", 67108864);


print "Creating/updating yahoo_boss tables...";
yahoo_boss_create_tables ();
print "\n";


openid_login_as_robot ("Yahoo! Search Robot");


print "Updating variant_external for existing searches...";
$q = theDb()->query ("SELECT DISTINCT variant_id FROM yahoo_boss_cache");
while ($row =& $q->fetchRow()) {
    yahoo_boss_update_external ($row["variant_id"]);
    print ".";
}
print "\n";


print "Building queue...";
theDb()->query ("CREATE TEMPORARY TABLE yahoo_boss_queue (
  variant_id BIGINT UNSIGNED NOT NULL
) AS
 SELECT v.variant_id
 FROM variants v
 LEFT JOIN genetests_genes
  ON gene=v.variant_gene
 LEFT JOIN yahoo_boss_cache c
  ON c.variant_id=v.variant_id
 WHERE gene IS NOT NULL
  AND c.xml IS NULL");
print theDb()->affectedRows();
print "\n";


$q = theDb()->query ("SELECT q.variant_id variant_id, v.*
 FROM yahoo_boss_queue q
 LEFT JOIN variants v
  ON v.variant_id=q.variant_id");
while ($row =& $q->fetchRow()) {
    $r = yahoo_boss_lookup ($row["variant_id"]);
    yahoo_boss_update_external ($row["variant_id"]);
    printf ("%8d %s %s%d%s (%d)\n",
	    $r["hitcount"],
	    $row["variant_gene"], $row["variant_aa_from"], $row["variant_aa_pos"], $row["variant_aa_to"],
	    $row["variant_id"]);
    sleep (1);
}


?>
