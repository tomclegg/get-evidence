<?php

include "lib/setup.php";

define ("ROWSPERPAGE", 10);
define ("MAXPAGES", 40);

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
  $sql_having .= " AND d_dataset_id IS NOT NULL";
}
else if ($want_report_type == "need-summary") {
  $report_title = "Summaries Needed";
  $sql_where = "s.variant_impact IN ('putative pathogenic','pathogenic') AND (s.summary_short IS NULL OR s.summary_short='')";
}
else if ($want_report_type == "web-search") {
  $report_title = "Web Results";
  $sql_where = "hitcount > 0";
  $sql_having .= " AND g.genome_id IS NOT NULL";
  $sql_orderby = "ORDER BY hitcount DESC";
  if ($_GET["rare"]) {
      $report_title .= ", rare";
      $sql_having .= " AND allele_frequency < 0.05";
  }
  if ($_GET["noomim"]) {
      $report_title .= ", no OMIM";
      $sql_having .= " AND MIN(omim.variant_id) IS NULL";
  }
  if ($_GET["nodbsnp"]) {
      $report_title .= ", no dbSNP";
      $sql_having .= " AND NOT (MAX(o.rsid) > 0)";
  }
}
else {
  $gOut["title"] = "GET-Evidence: Reports";
  $gOut["content"] = $gTheTextile->textileThis (<<<EOF
h1. Available reports

* "Population Actions":report?type=population-actions -- pathogenic and putative pathogenic variants that appear in data sets (or, same report "omitting het SNPs for recessive variants":report?type=population-actions&domorhom=1)
* "Summaries Needed":report?type=need-summary -- pathogenic and putative pathogenic variants with no summary available (or, same report "omitting het SNPs for recessive variants":report?type=need-summary&domorhom=1)
* Variants with genome evidence and web search results, sorted by #hits:
** "All":report?type=web-search
** "Without OMIM entries":report?type=web-search&noomim=1
** "Without dbSNP entries":report?type=web-search&nodbsnp=1
EOF
);
  go();
  exit;
}


$gOut["title"] = "GET-Evidence: $report_title";
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
 y.hitcount AS hitcount,
 SUM(af.num)/SUM(af.denom) AS allele_frequency
FROM snap_$snap s
LEFT JOIN variants v ON s.variant_id=v.variant_id
LEFT JOIN variant_occurs o ON v.variant_id=o.variant_id AND $sql_occur_filter
LEFT JOIN allele_frequency af ON af.chr=o.chr AND af.chr_pos=o.chr_pos AND af.allele=o.allele
LEFT JOIN datasets d ON o.dataset_id=d.dataset_id
LEFT JOIN genomes g ON d.genome_id=g.genome_id
LEFT JOIN yahoo_boss_cache y ON s.variant_id=y.variant_id
LEFT JOIN variant_external omim ON omim.variant_id=s.variant_id AND omim.tag='OMIM'
-- LEFT JOIN snap_$snap gs ON gs.variant_id=s.variant_id AND gs.article_pmid=0 AND gs.genome_id=g.genome_id
WHERE s.article_pmid=0 AND s.genome_id=0 AND $sql_where
GROUP BY v.variant_id,g.genome_id
HAVING $sql_having
$sql_orderby
");
  if (theDb()->isError($q)) die ("DB Error: ".$q->getMessage() . "<br>" . $sql);
  print "<TABLE class=\"report_table\" style=\"width: 100%\">\n";
  print "<TR><TD colspan=\"5\" id=\"reportpage_turner_copy\" style=\"text-align: right;\">&nbsp;</TD></TR>\n";
  print "<TR><TH>" . join ("</TH><TH>",
			   array ("Variant",
				  "Impact",
				  "Inheritance pattern",
				  "Summary",
				  "Genomes"
				  )) . "</TH></TR>\n";
  $output_row = 0;
  $output_page = 0;
  $output_cut_off_after = 0;
  $tr_attrs = "";
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

    ++$output_row;
    if ($output_row % ROWSPERPAGE == 1) {
	++$output_page;
	$tr_attrs = " class=\"reportpage reportpage_$output_page\"";
	if ($output_page > 1) {
	    $tr_attrs = " class=\"reportpage reportpage_$output_page csshide\"";
	}
    }
    if ($output_page > MAXPAGES) {
	if (!$output_cut_off_after)
	    $output_cut_off_after = $output_row - 1;
	continue;
    }

    $gene = $row["variant_gene"];
    $aa = aa_short_form($row["variant_aa_from"] . $row["variant_aa_pos"] . $row["variant_aa_to"]);

    $rowspan = count($genome_rows);
    if ($rowspan < 1) $rowspan = 1;
    $rowspan = "rowspan=\"$rowspan\"";

    $impact = ereg_replace ("^putative ", "p.", $row["variant_impact"]);
    if (strlen($row["allele_frequency"]))
	$impact .= sprintf (" f=%.3f", $row["allele_frequency"]);

    $summary_short = $gTheTextile->textileRestricted ($row["summary_short"]);
    if ($row["hitcount"] > 0) {
	$s = $row["hitcount"] == 1 ? "" : "s";
	$summary_short .= "<P>($row[hitcount] web hit$s)</P>";
    }

    printf ("<TR$tr_attrs><TD $rowspan>%s</TD><TD $rowspan>%s</TD><TD $rowspan>%s</TD><TD $rowspan>%s</TD>",
	    "<A href=\"$gene-$aa\">$gene&nbsp;$aa</A>",
	    $impact,
	    $row["variant_dominance"],
	    $summary_short
	    );
    $rownum = 0;
    foreach ($genome_rows as $id => $row) {
      if (++$rownum > 1) print "</TR>\n<TR$tr_attrs>";
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

  if ($output_page > 1) {
      print "<TR><TD colspan=\"5\" id=\"reportpage_turner\" style=\"text-align: right;\">Page: ";
      for ($p=1; $p<=$output_page && $p<=MAXPAGES; $p++)
	  print "<A class=\"reportpage_turnbutton\" href=\"#\" onclick=\"reportpage_goto($p);\">$p</A> ";
      print "<BR /><STRONG>Total results: $output_row</STRONG>";
      if ($output_cut_off_after)
	  print "<BR />(Only displaying first $output_cut_off_after results)";
      print "</TD></TR>\n";
      print "<SCRIPT type=\"text/javascript\"><!--\n";
      print "reportpage_init();\n";
      print "\n// -->\n</SCRIPT>";
  }
  else {
      print "<TR><TD colspan=\"5\" style=\"text-align: right;\"><STRONG>Total results: $output_row</STRONG></TD></TR>";
  }

  print "</TABLE>\n";
}

go();

?>
