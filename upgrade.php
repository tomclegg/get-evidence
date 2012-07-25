#!/usr/bin/php
<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

chdir ('public_html');
require_once 'lib/setup.php';
require_once 'lib/aa.php';


print "Creating/updating get-evidence tables...";
evidence_create_tables ();
print "\n";

print "Checking for variant rows with aa_from/aa_to but no aa_del/aa_ins...";
if (theDb()->getOne ("select 1 from variants where variant_aa_del is null or variant_aa_ins is null limit 1")) {
    print "\nFilling in missing variant_aa_ins, variant_aa_del...";
    foreach ($aa_31 as $long => $short) {
	theDb()->query ("update variants set variant_aa_del=? where variant_aa_from=? and variant_aa_del is null", array ($short, $long));
	theDb()->query ("update variants set variant_aa_ins=? where variant_aa_to=? and variant_aa_ins is null", array ($short, $long));
    }
    print "\n";
    $n = theDb()->getOne ("select count(*) from variants where variant_rsid is null and (variant_aa_del is null or variant_aa_ins is null)");
    if ($n > 0) {
	print "WARNING: you have $n rows in your variants table with no rsid and NULL in either variant_aa_del or variant_aa_ins.\n";
    }
}
else {
    print " none found, all good.\n";
}
