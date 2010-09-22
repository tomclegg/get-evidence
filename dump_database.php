#!/usr/bin/php
<?php

  // Copyright 2010 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

if ($_SERVER["argc"] != 2 || !ereg ('\.gz$', $_SERVER["argv"][1]))
    {
	die ("Usage: ".$_SERVER["argv"][0]." dump_output_file.sql.gz\n");
    }

$rundir = getcwd();
chdir ('public_html');
require_once 'lib/setup.php';
chdir ($rundir);

$dumpfile = escapeshellarg ($_SERVER["argv"][1]);

if (!ereg ('mysql://([^:]*):([^@]*)@([^/]*)/(.*)', $gDsn, $regs))
    die ($_SERVER["argv"][0].": fatal: could not parse \$gDsn");

$user = escapeshellarg($regs[1]);
$pass = escapeshellarg($regs[2]);
$host = escapeshellarg($regs[3]);
$db = escapeshellarg($regs[4]);

# Explanation of command-line perl below that strips the email from eb_users:
# 

passthru ("mysqldump -e -u $user -p$pass -h $host $db allele_frequency articles datasets diseases eb_users edits flat_summary gene_disease genomes snap_latest variant_disease variant_external variant_frequency variant_locations variant_occurs variants | perl -ne '
if (/^INSERT INTO `eb_users` VALUES /) { 
  if (s/^INSERT INTO \`eb_users` VALUES \(([^,]*),([^,]*),([^,]*),([^,]*),([^,]*)\);/INSERT INTO \`eb_users` VALUES \($1,$2,$3,NULL,$5\);/) { 
    print; 
  } else {
    # skip output of lines that match first but not second regex--columns may have changed, do not want to accidentally fail to scrub emails 
  } 
} else { 
  print; }' | gzip -9v > $dumpfile.tmp && mv $dumpfile.tmp $dumpfile && ls -l $dumpfile");


