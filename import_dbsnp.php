#!/usr/bin/php
<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

if ($_SERVER["argc"] < 2)
    {
	die ("Usage: ".$_SERVER["argv"][0]." b130.bcp.gz\n");
    }

$rundir = getcwd();

$fifo = $rundir."/tmp/".$_SERVER["argv"][0].".fifo";
@unlink ($fifo);
system ("mkfifo ".escapeshellarg($fifo));

if (($child = pcntl_fork()) === 0) {
    $filename = $_SERVER["argv"][1];
    $ph = popen ("gzip -cdf ".escapeshellarg($filename), "r");
    $fh = fopen ($fifo, "w");
    $line_count = 0;
    $bytes_read = 0;
    while ($line = fgets ($ph)) {
	fputs ($fh, $line);
	if (++$line_count % 100000 == 0) {
	    if ($line_count > 100000)
		print "\010\010\010\010\010\010\010\010\010\010\010\010\010";
	    printf ("%10d...", $line_count);
	}
    }
    fclose ($fh);
    pclose ($ph);
    printf ("\010\010\010\010\010\010\010\010\010\010\010\010\010%10d input rows...", $line_count);
    exit;
}
if (!($child > 0)) {
    die ("fork failed, giving up.\n");
}

chdir ('public_html');
require_once 'lib/setup.php';
chdir ($rundir);


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";


print "Creating temporary table...";
theDb()->query ("CREATE TEMPORARY TABLE dbsnp_tmp (
 id INT UNSIGNED NOT NULL PRIMARY KEY,
 chr CHAR(7) NOT NULL,
 chr_pos INT UNSIGNED NOT NULL,
 orient TINYINT UNSIGNED NOT NULL
 )");
print "\n";


print "Importing...";
$q = theDb()->query ("LOAD DATA LOCAL INFILE ?
	 INTO TABLE dbsnp_tmp
	 FIELDS TERMINATED BY '\t'
	 LINES TERMINATED BY '\n'",
		     array ($fifo));
if (theDb()->isError($q)) die ($q->getMessage());
print theDb()->affectedRows();
print "\n";


print "Removing chr=\"Multi\" rows...";
theDb()->query ("DELETE FROM dbsnp_tmp WHERE chr=?", array("Multi"));
print theDb()->affectedRows();
print "\n";


print "Removing chr_pos=0 rows...";
theDb()->query ("DELETE FROM dbsnp_tmp WHERE chr_pos=0");
print theDb()->affectedRows();
print "\n";


print "Adding \"chr\" prefix to chr column...";
theDb()->query ("UPDATE dbsnp_tmp SET chr=CONCAT('chr',chr)");
print theDb()->affectedRows();
print "\n";


print "Adding 1 to chr_pos column to get 1-based coordinates...";
theDb()->query ("UPDATE dbsnp_tmp SET chr_pos=chr_pos+1");
print theDb()->affectedRows();
print "\n";


print "Copying data to real dbsnp table...";
theDb()->query ("LOCK TABLES dbsnp WRITE");
theDb()->query ("DELETE FROM dbsnp");
theDb()->query ("INSERT INTO dbsnp (id,chr,chr_pos,orient) SELECT * FROM dbsnp_tmp");
print theDb()->affectedRows();
theDb()->query ("UNLOCK TABLES");
print "\n";


theDb()->query ("DROP TEMPORARY TABLE dbsnp_tmp");

?>
