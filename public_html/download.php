<?php

include "lib/setup.php";

$snap = false;
if ($_GET["version"] == "release" || ereg ("/release", $_SERVER["PATH_INFO"]))
  $snap = "release";
else if ($_GET["version"] == "latest" || ereg ("/latest", $_SERVER["PATH_INFO"]))
  $snap = "latest";

if ($snap) {

  $q = theDb()->query ("SELECT * FROM variants v
			LEFT JOIN snap_$snap s ON s.variant_id=v.variant_id
			WHERE s.variant_id IS NOT NULL
			AND NOT (article_pmid>0)
			AND NOT (genome_id>0)");
  if (theDb()->isError($q)) {
    header ("HTTP/1.1 500 Internal server error");
    die ("Database error: " . $q->getMessage());
  }

  header ("Content-type: text/tab-separated-values");

  $fieldlist = array ("variant_gene",
		      "variant_aa_change",
		      "variant_dominance",
		      "variant_impact",
		      "summary_short");
  print ereg_replace ("\tvariant_", "\t",
		      ereg_replace ("variant_dominance", "variant_inheritance",
				    implode ("\t", $fieldlist)));
  print "\n";

  ini_set ("output_buffering", true);
  while ($row =& $q->fetchRow()) {
    $out = "";
    $row["variant_aa_change"]
      = $row["variant_aa_from"].$row["variant_aa_pos"].$row["variant_aa_to"];
    foreach ($fieldlist as $field) {
      $v = $row[$field];
      if (strlen($out)) $out .= "\t";
      $out .= ereg_replace ("[\t\n]", " ", $v);
    }
    print $out;
    print "\n";
  }
  exit;

}
$gOut["content_textile"] = <<<EOF
h1. Download

You can download the *latest* snapshot of the database in TSV format.

* "latest-summary.tsv":/download/latest includes gene, AA change, dominance, impact, and short summary.

EOF
;
go();
?>
