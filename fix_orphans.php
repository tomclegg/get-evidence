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
ini_set ("memory_limit", 67108864);

genomes_create_tables ();

print "Finding snap_latest rows with no \"variant added\" row... ";
theDb()->query ("create temporary table no_variant_add as select distinct variant_id from snap_latest");
print theDb()->affectedRows();
print " variants total\n";

theDb()->query ("delete nva.* from no_variant_add nva
 LEFT JOIN snap_latest s
  ON nva.variant_id=s.variant_id
  AND s.article_pmid=0
  AND s.genome_id=0
  AND s.disease_id=0
 WHERE s.variant_id IS NOT NULL");
print theDb()->affectedRows();
print " alrady have \"add\" entries\n";

theDb()->query ("CREATE TEMPORARY TABLE edits_to_add LIKE edits");
theDb()->query ("ALTER TABLE edits_to_add ADD UNIQUE(variant_id)");
theDb()->query ("INSERT IGNORE INTO edits_to_add
 SELECT e.* FROM no_variant_add nva
 LEFT JOIN edits e ON e.variant_id=nva.variant_id
 ORDER BY edit_timestamp");
print theDb()->affectedRows();
print " inserted\n";

theDb()->query ("ALTER TABLE edits_to_add CHANGE edit_id edit_id BIGINT, DROP KEY `edit_id`");
theDb()->query ("UPDATE edits_to_add SET article_pmid=0,genome_id=0,disease_id=0,summary_short='',summary_long='',talk_text='',variant_quality='',variant_quality_text='',variant_impact='not reviewed',variant_dominance='unknown',previous_edit_id=null,edit_id=NULL");
print theDb()->affectedRows();
print " modified to become \"add\" entries\n";

theDb()->query ("INSERT INTO edits SELECT * FROM edits_to_add");
print theDb()->affectedRows();
print " added to \"edits\"\n";

theDb()->query ("INSERT IGNORE INTO snap_latest
 SELECT e.* FROM edits_to_add eta
 LEFT JOIN edits e ON eta.variant_id=e.variant_id AND e.article_pmid=0 AND e.genome_id=0 AND e.disease_id=0 WHERE e.edit_id IS NOT NULL
 ORDER BY edit_id DESC");
print theDb()->affectedRows();
print " added to \"snap_latest\"\n";

theDb()->query ("DELETE fs.* FROM edits_to_add eta
 LEFT JOIN flat_summary fs ON fs.variant_id=eta.variant_id");
print theDb()->affectedRows();
print " deleted from \"flat_summary\"\n";
