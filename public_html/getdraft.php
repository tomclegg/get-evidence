<?php

include "lib/setup.php";

foreach (array ("variant_impact", "variant_dominance",
		"summary_short", "summary_long", "talk_text",
		"article_pmid") as $k) {
  $fields_allowed[$k] = 1;
}

foreach (explode ("-", $_GET["edit_ids"]) as $edit_id) {
  if (!ereg ("^[0-9]+$", $edit_id))
    continue;

  // Look for drafts ("d") already saved by this user based on the
  // given edit and newer ("n") submissions from other users (danger
  // of conflict)

  $q =& theDb()->query ("SELECT d.*, n.edit_id newer_edit_id, (d.variant_impact <> a.variant_impact OR d.variant_dominance <> a.variant_dominance OR d.summary_short <> a.summary_short OR d.summary_long <> a.summary_long OR d.talk_text <> a.talk_text OR d.article_pmid <> a.article_pmid) draft_differs
			FROM edits a
			LEFT JOIN edits d ON d.previous_edit_id=a.edit_id AND d.edit_oid=? AND d.is_draft
			LEFT JOIN snap_latest n ON n.variant_id=a.variant_id AND n.article_pmid=a.article_pmid AND n.genome_id=a.genome_id
			WHERE a.edit_id=?",
			array(getCurrentUser("oid"), $edit_id));
  while ($row =& $q->fetchRow()) {
    if ($row["edit_id"]) {
      if (!$row[draft_differs]) {
	// draft saved, but content is identical -- just delete it
	theDb()->query ("DELETE FROM edits WHERE edit_id=? AND edit_oid=?",
			array($row["edit_id"], getCurrentUser("oid")));
	continue;
      }
      // Existing draft
      foreach (array_keys ($fields_allowed) as $field) {
	$response["saved__${edit_id}__${field}"]
	  = isset($row[$field]) ? $row[$field] : $row["d.$field"];
	$response["preview__${edit_id}__${field}"]
	  = $gTheTextile->textileRestricted($response["saved__${edit_id}__${field}"]);
      }
    }
  }
}

header ("Content-type: application/json");
print json_encode ($response);

?>

