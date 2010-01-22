<?php

include "lib/setup.php";

$response = array();

$item_id = evidence_get_latest_edit ($_POST[v], $_POST[a], $_POST[g], $_POST[d]);
if ($item_id) {
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
  }
}
else {
  $response["errors"][] = "Nothing to delete";
}

header ("Content-type: application/json");
print json_encode ($response);

?>

