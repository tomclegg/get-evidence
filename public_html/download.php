<?php

include "lib/setup.php";

$snap = false;
if ($_GET["version"] == "release" || ereg ("/release", $_SERVER["PATH_INFO"]))
  $snap = "release";
else if ($_GET["version"] == "latest" || ereg ("/latest", $_SERVER["PATH_INFO"]))
  $snap = "latest";

if ($snap &&
    ($_GET["type"] == "flat" || ereg ("/flat", $_SERVER["PATH_INFO"]))) {
    ini_set ("memory_limit", 33554432);
    $q = theDb()->query ("SELECT s.variant_id, flat_summary FROM snap_$snap s LEFT JOIN flat_summary fs ON fs.variant_id=s.variant_id GROUP BY s.variant_id");
    $n = 0;
    header ("Content-type: text/tab-separated-values");
    while ($row =& $q->fetchRow()) {
	if ($flat = $row["flat_summary"]) {
	    $flat = json_decode ($flat, true);
	}
	else {
	    $flat = evidence_get_assoc_flat_summary ($snap, $row["variant_id"]);
	    $x = theDb()->query ("REPLACE INTO flat_summary SET variant_id=?, updated=NOW(), flat_summary=?",
				 array ($row["variant_id"], json_encode($flat)));
	}
	if ($n == 0) {
	    print implode ("\t", array_keys ($flat));
	    print "\n";
	}
	++$n;
	print implode ("\t", array_values ($flat));
	print "\n";
    }
}

if ($snap) {

  $q = theDb()->query ("SELECT v.*, s.*,
			if(vo.rsid,concat('rs',vo.rsid),null) dbsnp_id,
			COUNT(vo.dataset_id) genome_hits,
			y.hitcount web_hits,
			vf.num overall_frequency_n,
			vf.denom overall_frequency_d,
			vf.f overall_frequency
			FROM variants v
			LEFT JOIN snap_$snap s ON s.variant_id=v.variant_id
			LEFT JOIN variant_frequency vf ON v.variant_id=vf.variant_id
			LEFT JOIN variant_occurs vo ON v.variant_id=vo.variant_id
			LEFT JOIN yahoo_boss_cache y ON v.variant_id=y.variant_id
			WHERE s.variant_id IS NOT NULL
			AND s.article_pmid=0
			AND s.genome_id=0
			AND s.disease_id=0
			GROUP BY v.variant_id");
  if (theDb()->isError($q)) {
    header ("HTTP/1.1 500 Internal server error");
    die ("Database error: " . $q->getMessage());
  }

  header ("Content-type: text/tab-separated-values");

  $fieldlist = array ("variant_gene",
		      "variant_aa_change",
		      "variant_dominance",
		      "variant_impact",
		      "dbsnp_id",
		      "overall_frequency_n",
		      "overall_frequency_d",
		      "overall_frequency",
		      "gwas_max_or",
		      "genome_hits",
		      "web_hits",
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

* "latest-flat.tsv":/download/latest/flat/latest-flat.tsv includes gene, AA change, dominance, impact, and short summary.

EOF
;
go();
?>
