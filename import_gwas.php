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
  `pub_date` VARCHAR(32),
  `journal` varchar(64),
  `url` varchar(255),
  `study` varchar(255),
  `disease_trait` varchar(255),
  `initial_sample_size` VARCHAR(255),
  `replication_sample_size` VARCHAR(255),
  `region` VARCHAR(255),
  `genes` VARCHAR(32),
  `risk_allele` VARCHAR(32),
  `snps` VARCHAR(32),
  `risk_allele_frequency` VARCHAR(16),
  `p_value` VARCHAR(32),
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
if (theDb()->isError($q)) print $q->getMessage();
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


print "Fixing up numeric fields...";
theDb()->query ("
UPDATE gwas
SET risk_allele_frequency = IF(risk_allele_frequency IN ('NR','Pending'),NULL,risk_allele_frequency),
 p_value = IF(p_value IN ('NS'),NULL,p_value),
 p_value_text = REPLACE(p_value_text,char(255),'')
");
print theDb()->affectedRows();
theDb()->query ("
ALTER TABLE gwas
 CHANGE risk_allele_frequency risk_allele_frequency DECIMAL(3,2),
 CHANGE p_value p_value FLOAT
");
print "\n";


print "Looking up rsid,allele -> variant_id via existing evidence...";
theDb()->query ("ALTER TABLE gwas ADD variant_id BIGINT UNSIGNED");
theDb()->query ("ALTER TABLE gwas ADD INDEX(variant_id)");
$q = theDb()->query ("
UPDATE gwas g
LEFT JOIN variant_occurs o
 ON o.rsid = substr(g.snps,3,99)
SET g.variant_id=o.variant_id
");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Looking up rsid,allele -> gene_aa via variant_locations...";
theDb()->query ("ALTER TABLE gwas ADD gene_aa VARCHAR(32)");
theDb()->query ("ALTER TABLE gwas ADD INDEX(gene_aa)");
$q = theDb()->query ("
UPDATE gwas g
LEFT JOIN variant_locations l
 ON l.rsid = substr(g.snps,3,99)
 AND l.allele = g.allele
SET g.gene_aa=l.gene_aa
WHERE g.variant_id IS NULL
");
if (theDb()->isError($q)) print $q->getMessage();
print theDb()->affectedRows();
print "\n";


print "Adding variants...";
$q = theDb()->query ("
SELECT gene_aa
FROM gwas
WHERE variant_id IS NULL
AND gene_aa IS NOT NULL");
$n=0;
$did = array();
while ($row =& $q->fetchRow())
    {
	if (($variant_id = $did[$row["gene_aa"]])) {
	    theDb()->query ("UPDATE gwas SET variant_id=? WHERE gene_aa=?",
			    array ($variant_id, $row["gene_aa"]));
	    continue;
	}

	// create the variant, and an "initial edit/add" row in edits table
	$variant_id = evidence_get_variant_id ($row["gene_aa"],
					       false, false, false,
					       true);
	$edit_id = evidence_get_latest_edit ($variant_id,
					     0, 0,
					     true);
	$did[$row["gene_aa"]] = $variant_id;
	theDb()->query ("UPDATE gwas SET variant_id=? WHERE gene_aa=?",
			array ($variant_id, $row["gene_aa"]));
	theDb()->query ("UPDATE variant_locations SET variant_id=? WHERE gene_aa=?",
			array ($variant_id, $row["gene_aa"]));

	++$n;
	if ($n % 100 == 0)
	    print "$n...";
    }
print "$n\n";


print "Adding diseases...";
theDb()->query ("INSERT IGNORE INTO diseases (disease_name) SELECT disease_trait FROM gwas");
print theDb()->affectedRows();
print "\n";


print "Looking up disease IDs...";
theDb()->query ("ALTER TABLE gwas ADD disease_id BIGINT NOT NULL");
theDb()->query ("UPDATE gwas
 LEFT JOIN diseases d
 ON gwas.disease_trait = d.disease_name
 SET gwas.disease_id = d.disease_id");
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


print "Updating variant_disease...";
theDb()->query ("LOCK TABLES variant_disease WRITE");
theDb()->query ("DELETE FROM variant_disease WHERE dbtag='GWAS'");
theDb()->query ("INSERT IGNORE INTO variant_disease
 (variant_id, disease_id, dbtag)
 SELECT variant_id, disease_id, 'GWAS'
 FROM gwas
 WHERE variant_id > 0 AND disease_id > 0");
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


print "Updating variant_external...";
theDb()->query ("LOCK TABLES variant_external WRITE");
theDb()->query ("DELETE FROM variant_external WHERE tag='GWAS'");
theDb()->query ("INSERT INTO variant_external
 (variant_id, tag, content, url, updated)
 SELECT variant_id, 'GWAS', CONCAT(disease_trait,' (',risk_allele,')\n',first_author,' ',pub_date,' in ',journal,'\nOR or beta: ',or_or_beta,' ',ci_95_text,IF(risk_allele_frequency is null,'',CONCAT('\nRisk allele frequency: ',risk_allele_frequency)),IF(p_value is null,'',CONCAT('\np-value: ',p_value,' ',p_value_text)),'\nInitial sample: ',initial_sample_size,'\nReplication sample: ',replication_sample_size), url, NOW()
 FROM gwas
 WHERE variant_id > 0");
print theDb()->affectedRows();
print "\n";
theDb()->query ("UNLOCK TABLES");


print "Adding/updating gwas_or column in variants table...";
theDb()->query ("ALTER TABLE variants ADD gwas_max_or DECIMAL(6,3)");
theDb()->query ("CREATE TEMPORARY TABLE gwas_or_tmp
 AS SELECT variant_id, MAX(or_or_beta) or_or_beta
 FROM gwas
 WHERE variant_id IS NOT NULL
 AND or_or_beta IS NOT NULL
 AND or_or_beta <> 'NR'
 AND (ci_95_text LIKE '%]'
      OR ci_95_text LIKE '%] (%)')
 AND ci_95_text NOT LIKE '%]%]'
 GROUP BY variant_id");
theDb()->query ("UPDATE gwas_or_tmp
 LEFT JOIN variants
 ON variants.variant_id=gwas_or_tmp.variant_id
 SET variants.gwas_max_or=or_or_beta
 ");
print theDb()->affectedRows();
print "\n";


if (getenv("DEBUG"))
    {
	theDb()->query ("DROP TABLE IF EXISTS gwas_last");
	theDb()->query ("CREATE TABLE IF NOT EXISTS gwas_last LIKE gwas");
	theDb()->query ("INSERT INTO gwas_last SELECT * FROM gwas");
    }

?>
