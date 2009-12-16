<?php

include "lib/setup.php";

if ($_GET["oid"]) {
  $report_title = "Edit history";
  $where_sql = "edit_oid=?";
  $where_param = array($_GET["oid"]);
}
else if ($_GET["variant_id"]) {
  $report_title = "Edit history";
  $where_sql = "variant_id=?";
  $where_param = array($_GET["variant_id"] + 0);
}
else {
  $report_title = "Recent changes";
  $where_sql = "1=1";
  $where_param = array();
}

$gOut["title"] = "Evidence Base: $report_title";

function print_content($x)
{
  global $report_title, $where_sql, $where_param;
  print "<h1>$report_title</h1>\n\n";

  $q = theDb()->query ("SELECT *, UNIX_TIMESTAMP(edit_timestamp) t FROM edits
	LEFT JOIN eb_users ON edit_oid=oid
	LEFT JOIN variants ON variants.variant_id=edits.variant_id
	WHERE $where_sql AND is_draft=0
	ORDER BY edit_timestamp DESC LIMIT 30",
		       $where_param);
  if (theDb()->isError ($q)) die ($q->getMessage());

  print "<UL>";
  while ($row =& $q->fetchRow()) {
    print "<LI>";

    print strftime ("%b %e ", $row["t"]);

    print "<A href=\"$row[variant_gene]-$row[variant_aa_from]$row[variant_aa_pos]$row[variant_aa_to]\">$row[variant_gene] $row[variant_aa_from]$row[variant_aa_pos]$row[variant_aa_to]</A>";

    if (!$row["previous_edit_id"] && !$row["article_pmid"] && !$row["genome_id"])
      print " added by ";
    else
      print " edited by ";

    print ("<A href=\"edits?oid=".urlencode($row["edit_oid"])."\">".
	   (htmlspecialchars ($row["fullname"] ? $row["fullname"] : $row["nickname"])).
	   "</A>");

    if ($row["article_pmid"] && !$row["previous_edit_id"])
      print " (PMID $row[article_pmid] added)";
    else if ($row["genome_id"] && !$row["previous_edit_id"])
      print " (genome $row[genome_id] added)";
    print "</LI>\n";
  }
  print "</UL>\n";
}

go();

?>
