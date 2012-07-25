<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";

$report_title = "Contributors";
$gOut["title"] = "GET-Evidence: $report_title";

function print_content($x)
{
  global $want_json;
  global $report_title;
  if (!$want_json) print "<h1>$report_title</h1>\n\n";

  $q = theDb()->query ("SELECT editor_summary.oid oid, total_edits,
	UNIX_TIMESTAMP(latest_edit) t, webvotes, eb_users.*
	FROM editor_summary
	LEFT JOIN eb_users ON editor_summary.oid=eb_users.oid
	WHERE total_edits > 0 OR webvotes > 0
	ORDER BY latest_edit DESC");
  if (theDb()->isError ($q)) die ($q->getMessage());

  if ($want_json) print "\"editors\":[";
  else print "<UL>";

  while ($row =& $q->fetchRow()) {
    if ($want_json) {
      print json_encode (array("fullname" => $row["fullname"],
			       "total_edits" => $row["total_edits"],
			       "voted_urls" => $row["webvotes"],
			       "latest_edit_timestamp" => $row["latest_edit"],
			       "oid" => $row["oid"]));
      continue;
    }
    print "<LI>".htmlspecialchars ($row["fullname"] ? $row["fullname"] : $row["nickname"], ENT_QUOTES, "UTF-8");

    if ($row["total_edits"] > 0) {
      print (" -- <A href=\"edits?oid=".urlencode($row["oid"])."\">"
	     .$row["total_edits"]." edit".($row["total_edits"]==1?"":"s")
	     ."</A>");
      print strftime (" (latest %b %e)", $row["t"]);
    }

    if ($row["webvotes"] > 0) {
      print " -- ".$row["webvotes"]." web vote".($row["webvotes"]==1?"":"s");
    }

    print "</LI>\n";
  }
  if ($want_json) print "]";
  else print "</UL>\n";
}

go();

?>
