#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] != 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." gwas.csv\n");
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


openid_login_as_robot ("GWAS Importing Robot");

// Date Added to Catalog (since 11/25/08),PubMedID,First Author,Date,Journal,Link,Study,Disease/Trait,Initial Sample Size,Replication Sample Size,Region,Reported Gene(s),Strongest SNP-Risk Allele,SNPs,Risk Allele Frequency,p-Value,p-Value (text),OR or beta,95% CI (text),Platform [SNPs passing QC],CNV
print "Creating temporary table...";
$q = theDb()->query ("CREATE TEMPORARY TABLE gwas (
  `date_added` date,
  `pmid` int unsigned,
  `first_author` varchar(64),
  `pub_date` date,
  `journal` varchar(64),
  `url` varchar(255),
  `study` varchar(255),
  `disease_trait` varchar(255),
  `initial_sample_size` INT UNSIGNED,
  `replication_sample_size` int unsigned,
  `region` VARCHAR(255),
  `genes` VARCHAR(32),
  `risk_allele` VARCHAR(32),
  `snps` VARCHAR(32),
  `risk_allele_frequency` DECIMAL,
  `p_value` DECIMAL,
  `p_value_text` VARCHAR(32),
  `or_or_beta` VARCHAR(32),
  `ci_95_text` VARCHAR(32),
  `platform_SNPs_passing_QC` VARCHAR(32),
  `cnv` CHAR(1),
  INDEX(`snps`)
)");
if (theDb()->isError($q)) print $q->getMessage;
print "\n";


print "Importing data...";
$q = theDb()->query ("LOAD DATA LOCAL INFILE ? INTO TABLE gwas
 FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '\"'
 LINES TERMINATED BY '\n'
 IGNORE 1 LINES",
		array ($_SERVER["argv"][1]));
if (theDb()->isError($q)) print $q->getMessage;
print theDb()->affectedRows();
print "\n";


print "Discarding multi-SNP records...";
theDb()->query ("DELETE FROM gwas WHERE snps LIKE '%,%'");
print theDb()->affectedRows();
print "\n";


print "Discarding records without rsid...";
theDb()->query ("DELETE FROM gwas WHERE snps NOT LIKE 'rs%'");
print theDb()->affectedRows();
print "\n";


print "Splitting risk-allele into allele field...";
theDb()->query ("ALTER TABLE gwas ADD allele CHAR(1)");
theDb()->query ("
UPDATE gwas
SET allele = UPPER(SUBSTRING(risk_allele,LOCATE('-',risk_allele)+1,1))
");
print theDb()->affectedRows();
print "\n";


print "Looking up rsid,allele -> variant_id via existing evidence...";
theDb()->query ("ALTER TABLE gwas ADD variant_id BIGINT UNSIGNED");
$q = theDb()->query ("
UPDATE gwas g
LEFT JOIN variant_occurs o
 ON o.rsid = substr(g.snps,3,99)
SET g.variant_id=o.variant_id
");
if (theDb()->isError($q)) print $q->getMessage;
print theDb()->affectedRows();
print "\n";


print "Updating variant_external...";
theDb()->query ("LOCK TABLES variant_external WRITE");
theDb()->query ("DELETE FROM variant_external WHERE tag='GWAS'");
theDb()->query ("INSERT INTO variant_external
 (variant_id, tag, content, url, updated)
 SELECT variant_id, 'GWAS', CONCAT(disease_trait,' (',risk_allele,')'), url, NOW()
 FROM gwas
 WHERE variant_id > 0");
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


if (getenv("DEBUG"))
    {
	theDb()->query ("DROP TABLE IF EXISTS gwas_last");
	theDb()->query ("CREATE TABLE IF NOT EXISTS gwas_last LIKE gwas");
	theDb()->query ("INSERT INTO gwas_last SELECT * FROM gwas");
    }

?>
