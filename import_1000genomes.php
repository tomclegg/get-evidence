#!/usr/bin/php
<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] < 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." XXX.hap.2009_04.gz YYY.hap.2009_04.gz ...\n");
    }

$rundir = getcwd();
chdir ('public_html');
require_once 'lib/setup.php';
chdir ($rundir);


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


theDb()->query ("DROP TABLE import_1000genomes");
theDb()->query ("CREATE TABLE import_1000genomes (
 chr CHAR(12) NOT NULL,
 chr_pos INT UNSIGNED NOT NULL,
 allele CHAR(1) NOT NULL,
 occur INT UNSIGNED NOT NULL,
 denom INT UNSIGNED NOT NULL,
 UNIQUE(chr,chr_pos,allele)
 )");


for ($i=1; $i<$_SERVER["argc"]; $i++) {
    $filename = $_SERVER["argv"][$i];
    print "Importing $filename...";
    $fifo = $rundir."/tmp/".$_SERVER["argv"][0].".fifo";
    @unlink ($fifo);
    system ("mkfifo ".escapeshellarg($fifo));
    if (($child = pcntl_fork()) === 0) {
	$ph = popen ("gzip -cdf ".escapeshellarg($filename), "r");
	$fh = fopen ($fifo, "w");
	$line_count = 0;
	$bytes_read = 0;
	printf ("%10s   ", "");
	while ($line = fgets ($ph)) {
	    $bytes_read += strlen ($line);
	    list ($chr, $pos, $alleles) = explode ("\t", trim ($line));
	    $alleles = strtoupper ($alleles);
	    $tot = strlen ($alleles);
	    foreach (array ("A", "C", "G", "T") as $base) {
		$n = substr_count ($alleles, $base);
		if ($n > 0)
		    fputs ($fh, "chr$chr\t$pos\t$base\t$n\t$tot\n");
	    }
	    if (++$line_count % 100000 == 0)
		printf ("\010\010\010\010\010\010\010\010\010\010\010\010\010%10d...", $line_count);
	    if (getenv("MAX_ROWS_PER_FILE") &&
		$line_count >= getenv("MAX_ROWS_PER_FILE"))
		exit;
	}
	fclose ($fh);

	pclose ($ph);
	printf ("\010\010\010\010\010\010\010\010\010\010\010\010\010%10d input rows...", $line_count);
	exit;
    }
    if (!($child > 0)) {
	die ("fork failed, giving up.\n");
    }
    reconnectDb();
    $q = theDb()->query ("CREATE TEMPORARY TABLE import_1000genomes_onefile LIKE import_1000genomes");
    $q = theDb()->query ("LOAD DATA LOCAL INFILE ?
	 INTO TABLE import_1000genomes_onefile
	 FIELDS TERMINATED BY '\t'
	 LINES TERMINATED BY '\n'",
			 array ($fifo));
    if (theDb()->isError($q)) print $q->getMessage();
    print theDb()->affectedRows();
    print "\n";

    print "Merging...";
    $q = theDb()->query ("INSERT INTO import_1000genomes
 SELECT import_1000genomes_onefile.*
 FROM import_1000genomes_onefile
 LEFT JOIN variant_occurs vo
	 ON import_1000genomes_onefile.chr=vo.chr
	 AND import_1000genomes_onefile.chr_pos=vo.chr_pos
	 AND import_1000genomes_onefile.allele=vo.allele
 WHERE vo.variant_id IS NOT NULL
 GROUP BY import_1000genomes_onefile.chr,
	import_1000genomes_onefile.chr_pos,
	import_1000genomes_onefile.allele
 ON DUPLICATE KEY UPDATE
	 import_1000genomes.occur=import_1000genomes.occur+VALUES(occur),
	 import_1000genomes.denom=import_1000genomes.denom+VALUES(denom)");
    if (theDb()->isError($q)) print $q->getMessage();
    print theDb()->affectedRows();
    print "\n";

    $q = theDb()->query ("DROP TEMPORARY TABLE import_1000genomes_onefile");
}


print "Copying data into allele_frequency table...";
theDb()->query ("REPLACE INTO allele_frequency
 (dbtag, chr, chr_pos, allele, num, denom)
 SELECT ?, chr, chr_pos, allele, occur, denom
 FROM import_1000genomes",
		array ("1000g"));
print theDb()->affectedRows();
print "\n";


theDb()->query ("DROP TEMPORARY TABLE variant_frequency_tmp");

?>
