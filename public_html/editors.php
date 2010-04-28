<?php

include "lib/setup.php";

$report_title = "Contributors";
$gOut["title"] = "GET-Evidence: $report_title";

function print_content($x)
{
  global $report_title;
  print "<h1>$report_title</h1>\n\n";

  $q = theDb()->query ("SELECT edit_oid, count(*) edit_count, UNIX_TIMESTAMP(MAX(edit_timestamp)) t, eb_users.* FROM edits
	LEFT JOIN eb_users ON edit_oid=oid
	WHERE is_draft=0
	GROUP BY edit_oid
	ORDER BY edit_timestamp DESC");
  if (theDb()->isError ($q)) die ($q->getMessage());

  print "<UL>";
  while ($row =& $q->fetchRow()) {
    print "<LI>";

    print ("<A href=\"edits?oid=".urlencode($row["edit_oid"])."\">".
	   (htmlspecialchars ($row["fullname"] ? $row["fullname"] : $row["nickname"])).
	   "</A>");

    print " -- ".$row["edit_count"]." edits";

    print strftime (" -- latest %b %e", $row["t"]);

    print "</LI>\n";
  }
  print "</UL>\n";
}

go();

?>
