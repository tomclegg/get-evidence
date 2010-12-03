#!/usr/bin/php
<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

chdir ('public_html');
include "lib/setup.php";

evidence_create_tables();


function sqlflush (&$sql, &$sqlparam)
{
    if (count ($sqlparam) == 0) {
	$sql = "";
	return;
    }
    $sql = ereg_replace (',$', '', $sql);
    $q = theDb()->query ("REPLACE INTO flat_summary (variant_id, flat_summary, autoscore, webscore, n_genomes) VALUES $sql", $sqlparam);
    if (theDb()->isError ($q)) die ($q->getMessage());
    $sql = "";
    $sqlparam = array();
}


print "Updating flat_summary...\n";
$snap = "latest";
// If any are missing, just generate those
$join = "LEFT JOIN flat_summary fs ON v.variant_id=fs.variant_id WHERE fs.variant_id IS NULL";
$tot = theDb()->getOne ("SELECT COUNT(*) FROM variants v $join");
if ($tot == 0) {
    // If none are missing, refresh all
    $join = "";
    $tot = theDb()->getOne ("SELECT COUNT(*) FROM variants v");
}
$q = theDb()->query ("SELECT DISTINCT v.variant_id FROM variants v $join");
$n = 0;
while ($row =& $q->fetchRow()) {
    ++$n;
    print "\r$n / $tot ";
    $flat = evidence_get_assoc_flat_summary ($snap, $row["variant_id"]);
    $sql .= "(?, ?, ?, ?, ?),";
    $sqlparam[] = $row["variant_id"];
    $sqlparam[] = json_encode ($flat);
    $sqlparam[] = $flat["autoscore"];
    $sqlparam[] = $flat["webscore"];
    $sqlparam[] = $flat["n_genomes"];
    if (count($sqlparam) > 100)
	sqlflush (&$sql, &$sqlparam);
}
sqlflush (&$sql, &$sqlparam);

print "\n";
