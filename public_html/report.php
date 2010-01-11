<?php

include "lib/setup.php";

$snap = "latest";
$sql_where = "1=1";
$sql_having = "1=1";
$sql_occur_filter = "1=1";


if ($_GET["snap"] == "release")
  $snap = $_GET["snap"];
$want_report_type = $_GET["type"];


if ($_GET["domorhom"])
  $sql_occur_filter = "(s.variant_dominance <> 'recessive' OR o.zygosity = 'homozygous')";


$sql_orderby = "";


if ($want_report_type == "population-actions") {
  $report_title = "Population Actions";
  $sql_where = "s.variant_impact IN ('putative pathogenic','pathogenic')";
  $sql_having = "d_dataset_id IS NOT NULL";
}
else if ($want_report_type == "need-summary") {
  $report_title = "Summaries Needed";
  $sql_where = "s.variant_impact IN ('putative pathogenic','pathogenic') AND (s.summary_short IS NULL OR s.summary_short='')";
}
else if ($want_report_type == "web-search") {
  $report_title = "Web Results, No Summary";
  $sql_where = "hitcount IS NOT NULL AND (s.summary_short IS NULL OR s.summary_short='')";
  $sql_orderby = "ORDER BY hitcount DESC";
}
else {
  $gOut["title"] = "Evidence Base: Reports";
  $gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Available reports

* "Population Actions":report?type=population-actions -- pathogenic and putative pathogenic variants that appear in data sets (or, same report "omitting het SNPs for recessive variants":report?type=population-actions&domorhom=1)
* "Summaries Needed":report?type=need-summary -- pathogenic and putative pathogenic variants with no summary available (or, same report "omitting het SNPs for recessive variants":report?type=need-summary&domorhom=1)
* "Web Search":report?type=web-search -- variants with no summary available but plenty of web search hits (or, same report "omitting het SNPs for recessive variants":report?type=web-search&domorhom=1)
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
  global $sql_occur_filter;
  global $sql_orderby;
  global $snap;
  global $gTheTextile;
  $q = theDb()->query ($sql = "
SELECT s.*, v.*, g.*,
-- gs.summary_short AS g_summary_short,
 MAX(o.zygosity) AS max_zygosity,
 d.dataset_id AS d_dataset_id,
 g.genome_id AS g_genome_id,
 y.hitcount AS hitcount
FROM snap_$snap s
LEFT JOIN variants v ON s.variant_id=v.variant_id
LEFT JOIN variant_occurs o ON v.variant_id=o.variant_id AND $sql_occur_filter
LEFT JOIN datasets d ON o.dataset_id=d.dataset_id
LEFT JOIN genomes g ON d.genome_id=g.genome_id
LEFT JOIN yahoo_boss_cache y ON s.variant_id=y.variant_id
-- LEFT JOIN snap_$snap gs ON gs.variant_id=s.variant_id AND gs.article_pmid=0 AND gs.genome_id=g.genome_id
WHERE s.article_pmid=0 AND s.genome_id=0 AND $sql_where
GROUP BY v.variant_id,g.genome_id
HAVING $sql_having
$sql_orderby
LIMIT 100
");
  if (theDb()->isError($q)) die ("DB Error: ".$q->getMessage() . "<br>" . $sql);
  print "<TABLE class=\"report_table\">\n";
  print "<TR><TH>" . join ("</TH><TH>",
			   array ("Variant",
				  "Impact",
				  "Inheritance pattern",
				  "Summary",
				  "Genomes"
				  )) . "</TH></TR>\n";
  $genome_rows = array();
  for ($row =& $q->fetchRow();
       $row;
       $row =& $nextrow) {
    $row["name"] = $row["name"] ? $row["name"] : "[".$row["global_human_id"]."]";
    $genome_rows[$row["genome_id"]] = $row;
    $nextrow =& $q->fetchRow();
    if ($nextrow && $row["variant_id"] == $nextrow["variant_id"]) {
      continue;
    }
    $gene = $row["variant_gene"];
    $aa = aa_short_form($row["variant_aa_from"] . $row["variant_aa_pos"] . $row["variant_aa_to"]);

    $rowspan = count($genome_rows);
    if ($rowspan < 1) $rowspan = 1;
    $rowspan = "rowspan=\"$rowspan\"";

    printf ("<TR><TD $rowspan>%s</TD><TD $rowspan>%s</TD><TD $rowspan>%s</TD><TD $rowspan>%s</TD>",
	    "<A href=\"$gene-$aa\">$gene&nbsp;$aa</A>",
	    ereg_replace ("^putative ", "p.", $row["variant_impact"]),
	    $row["variant_dominance"],
	    $row["summary_short"]
	    );
    $rownum = 0;
    foreach ($genome_rows as $id => $row) {
      if (++$rownum > 1) print "</TR>\n<TR>";
      if (!$row["g_genome_id"]) {
	print "<TD></TD>";
	continue;
      }
      print "<TD width=\"15%\"><A href=\"$gene-$aa#g$id\">".htmlspecialchars($row["name"])."</A>";
      if ($row["max_zygosity"] == 'homozygous')
	print " (hom)";
      print "</TD>";
    }
    print "</TR>\n";
    $genome_rows = array();
  }
  print "</TABLE>\n";
}

go();

?>
