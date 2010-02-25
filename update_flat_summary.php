#!/usr/bin/php
<?php;

// Copyright 2010 Scalable Computing Experts, Inc.
// Author: Tom Clegg

chdir ('public_html');
include "lib/setup.php";

print "Updating flat_summary...\n";
$snap = "latest";
$tot = theDb()->getOne ("SELECT COUNT(*) FROM snap_$snap");
$q = theDb()->query ("SELECT variant_id FROM snap_$snap");
$n = 0;
while ($row =& $q->fetchRow()) {
    ++$n;
    print "\r$n / $tot ";
    $flat = evidence_get_assoc_flat_summary ($snap, $row["variant_id"]);
    theDb()->query ("REPLACE INTO flat_summary SET variant_id=?, flat_summary=?",
		    array ($row["variant_id"], json_encode($flat)));
}
print "\n";
