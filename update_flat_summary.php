#!/usr/bin/php
<?php;

// Copyright 2010 Scalable Computing Experts, Inc.
// Author: Tom Clegg

chdir ('public_html');
include "lib/setup.php";


function sqlflush (&$sql, &$sqlparam)
{
    if (count ($sqlparam) == 0) {
	$sql = "";
	return;
    }
    $sql = ereg_replace (',$', '', $sql);
    theDb()->query ("REPLACE INTO flat_summary (variant_id, flat_summary) VALUES $sql", $sqlparam);
    $sql = "";
    $sqlparam = array();
}


print "Updating flat_summary...\n";
$snap = "latest";
$tot = theDb()->getOne ("SELECT COUNT(*) FROM variants");
$q = theDb()->query ("SELECT DISTINCT variant_id FROM variants");
$n = 0;
while ($row =& $q->fetchRow()) {
    ++$n;
    print "\r$n / $tot ";
    $flat = evidence_get_assoc_flat_summary ($snap, $row["variant_id"]);
    $sql .= "(?, ?),";
    $sqlparam[] = $row["variant_id"];
    $sqlparam[] = json_encode ($flat);
    if (count($sqlparam) > 100)
	sqlflush (&$sql, &$sqlparam);
}
flush (&$sql, &$sqlparam);

print "\n";
