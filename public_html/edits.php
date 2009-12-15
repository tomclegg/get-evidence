<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base";

function print_content($x)
{
  $q = theDb()->query ("SELECT * FROM edits
	LEFT JOIN eb_users ON edit_oid=oid
	LEFT JOIN variants ON variants.variant_id=edits.variant_id
	WHERE is_draft=0
	ORDER BY edit_timestamp DESC LIMIT 30");
  if (theDb()->isError ($q)) die ($q->getMessage());

  print "<UL>";
  while ($row =& $q->fetchRow()) {
    print "<LI>";

    print "<A href=\"$row[variant_gene]-$row[variant_aa_from]$row[variant_pos]$row[variant_to]\">$row[variant_gene]-$row[variant_aa_from]$row[variant_pos]$row[variant_to]</A>";

    if (!$row["previous_edit_id"] && !$row["article_pmid"] && !$row["genome_id"])
      print " added by ";
    else
      print " edited by ";

    print htmlspecialchars ($row["fullname"]);

    if ($row["article_pmid"])
      print " (PMID $row[article_pmid])";
    else if ($row["genome_id"])
      print " (genome $row[genome_id])";
    print "</LI>\n";
  }
  print "</UL>\n";
}

go();

?>
