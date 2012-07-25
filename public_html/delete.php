<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";

$response = array();

if (!getCurrentUser()) {
  $response["errors"][] = "Not logged in";
}
else if (($item_id = evidence_get_latest_edit ($_POST[v], $_POST[a], $_POST[g], $_POST[d]))) {
  $q = theDb()->query ("INSERT INTO edits SET
			edit_oid=?, edit_timestamp=NOW(),
			is_draft=1, is_delete=1,
			previous_edit_id=?,
			variant_id=?,
			article_pmid=?, genome_id=?, disease_id=?",
		  array (getCurrentUser("oid"),
			 $item_id,
			 $_POST[v], $_POST[a], $_POST[g], $_POST[d]));
  if (theDb()->isError($q))
    $response["errors"][] = $q->getMessage();
  else if (($delete_id = theDb()->getOne ("SELECT LAST_INSERT_ID()"))) {
    evidence_submit ($delete_id);
    $response["deleted"] = true;
    if ($_POST[v] && ($_POST[a] || $_POST[g]) && !$_POST[d]) {
      // Delete per-disease entries for an article/genome entry
      // TODO: evidence_submit() should take care of this
      // TODO: snap_release will need special handling for this case too
      theDb()->query ("DELETE FROM snap_latest WHERE variant_id=? AND article_pmid=? AND genome_id=?", array ($_POST[v], $_POST[a], $_POST[g]));
    }
  }
}
else {
  $response["errors"][] = "Nothing to delete";
}

header ("Content-type: application/json");
print json_encode ($response);

?>

