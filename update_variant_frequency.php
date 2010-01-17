#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

chdir ('public_html');
require_once 'lib/setup.php';


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


print "Parsing hapmap data in taf table...";
$q=theDb()->query ("CREATE TEMPORARY TABLE hapmap_tmp
 AS SELECT chr, chr_pos, allele,
 CONVERT(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING(taf,LOCATE('\"all_n\": ',taf)+9),',',1),'}',1),UNSIGNED) num,
 CONVERT(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING(taf,LOCATE('\"all_d\": ',taf)+9),',',1),'}',1),UNSIGNED) denom
 FROM taf
 WHERE taf LIKE '%\"all_n\": %'
 ");
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";


print "Copying to allele_frequency...";
theDb()->query ("REPLACE INTO allele_frequency
 (chr,chr_pos,allele,dbtag,num,denom)
 SELECT chr,chr_pos,allele,?,num,denom
 FROM hapmap_tmp",
		array ('HapMap'));
print theDb()->affectedRows();
print "\n";


theDb()->query ("DROP TEMPORARY TABLE hapmap_tmp");


print "Merging frequencies from multiple databases...";
theDb()->query ("CREATE TEMPORARY TABLE allele_frequency_merge AS SELECT chr,chr_pos,allele,num,denom FROM allele_frequency LIMIT 0");
theDb()->query ("ALTER TABLE allele_frequency_merge ADD UNIQUE(chr,chr_pos,allele)");
theDb()->query ("ALTER TABLE allele_frequency_merge ADD variant_id BIGINT UNSIGNED");
theDb()->query ("ALTER TABLE allele_frequency_merge ADD INDEX(variant_id)");
theDb()->query ("INSERT IGNORE INTO allele_frequency_merge
 (chr,chr_pos,allele,num,denom)
 SELECT
 chr,chr_pos,allele,SUM(num),SUM(denom)
 FROM allele_frequency
 GROUP BY chr,chr_pos,allele");
print theDb()->affectedRows();
print "\n";


print "Attaching variant IDs using variant_occurs...";
theDb()->query ("UPDATE allele_frequency_merge afm
 LEFT JOIN variant_occurs o
  ON o.chr=afm.chr AND o.chr_pos=afm.chr_pos AND o.allele=afm.allele
 SET afm.variant_id=o.variant_id");
print theDb()->affectedRows();
print "\n";


print "Attaching variant IDs using variant_locations...";
theDb()->query ("UPDATE allele_frequency_merge afm
 LEFT JOIN variant_locations l
  ON l.chr=afm.chr AND l.chr_pos=afm.chr_pos AND l.allele=afm.allele
 SET afm.variant_id=l.variant_id
 WHERE afm.variant_id IS NULL");
print theDb()->affectedRows();
print "\n";


print "Updating variant_frequency...";
theDb()->query ("LOCK TABLES variant_frequency WRITE");
theDb()->query ("DELETE FROM variant_frequency WHERE 1=1");
$q=theDb()->query ("INSERT INTO variant_frequency
 (variant_id, num, denom, f)
 SELECT variant_id, num, denom, num/denom
 FROM allele_frequency_merge
 GROUP BY variant_id");
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");

?>
