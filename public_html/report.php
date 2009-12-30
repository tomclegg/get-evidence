<?php

include "lib/setup.php";

$snap = "latest";
$sql_where = "1=1";
$sql_having = "1=1";


if ($_GET["snap"] == "release")
  $snap = $_GET["snap"];
$want_report_type = $_GET["type"];


if ($want_report_type == "population-actions") {
  $report_title = "Population Actions";
  $sql_where = "s.variant_impact IN ('putative pathogenic','pathogenic')";
  $sql_having = "dataset_count > 0";
}
else if ($want_report_type == "need-summary") {
  $report_title = "Summaries Needed";
  $sql_where = "s.variant_impact IN ('putative pathogenic','pathogenic') AND (s.summary_short IS NULL OR s.summary_short='')";
}
else {
  $gOut["title"] = "Evidence Base: Reports";
  $gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Available reports

* "Population Actions":report?type=population-actions -- pathogenic and putative pathogenic variants that appear in data sets
* "Summaries Needed":report?type=need-summary -- pathogenic and putative pathogenic variants with no summary available
EOF
);
  go();
  exit;
}

$gOut["title"] = "Evidence Base: $report_title";
function print_content ()
{
  global $sql_where;
  global $sql_having;
  global $snap;
  $q = theDb()->query ($sql = "SELECT s.*, v.*, g.*, COUNT(ocount.dataset_id) dataset_count
FROM snap_$snap s
LEFT JOIN variants v ON s.variant_id=v.variant_id
LEFT JOIN variant_occurs o ON v.variant_id=o.variant_id
LEFT JOIN variant_occurs ocount ON v.variant_id=ocount.variant_id
LEFT JOIN datasets d ON o.dataset_id=d.dataset_id
LEFT JOIN genomes g ON d.genome_id=g.genome_id
WHERE $sql_where
GROUP BY v.variant_id,o.dataset_id
HAVING $sql_having
");
  if (theDb()->isError($q)) die ("DB Error: ".$q->getMessage());
  print "<TABLE class=\"report_table\">\n";
  print "<TR><TH>" . join ("</TH><TH>",
			   array ("Genomes",
				  "Variant",
				  "Impact",
				  "Inheritance pattern",
				  "Summary")) . "</TH></TR>\n";
  $name_list = array();
  for ($row =& $q->fetchRow();
       $row;
       $row =& $nextrow) {
    $name_list[] = $row["name"] ? $row["name"] : "[".$row["global_human_id"]."]";
    $nextrow =& $q->fetchRow();
    if ($nextrow && $row["variant_id"] == $nextrow["variant_id"]) {
      continue;
    }
    $gene = $row["variant_gene"];
    $aa = aa_short_form($row["variant_aa_from"] . $row["variant_aa_pos"] . $row["variant_aa_to"]);
    printf ("<TR><TD>%s</TD><TD>%s</TD><TD>%s</TD><TD>%s</TD><TD>%s</TD></TR>\n",
	    nl2br(htmlspecialchars(implode(",\n",$name_list))),
	    "<A href=\"$gene-$aa\">$gene&nbsp;$aa</A>",
	    ereg_replace ("^putative ", "p.", $row["variant_impact"]),
	    $row["variant_dominance"],
	    $row["summary_short"]
	    );
    $name_list = array();
  }
  print "</TABLE>\n";
}

go();

?>
