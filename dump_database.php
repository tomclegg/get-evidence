#!/usr/bin/php
<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

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
passthru ("mysqldump -e -u $user -p$pass -h $host $db allele_frequency articles datasets diseases editor_summary edits flat_summary gene_canonical_name gene_disease genetests_genes genomes snap_latest snap_release variant_disease variant_external variant_frequency variant_population_frequency variant_locations variant_occurs variants web_vote web_vote_history web_vote_latest | gzip -9v > $dumpfile.tmp && mv $dumpfile.tmp $dumpfile && ls -l $dumpfile");

