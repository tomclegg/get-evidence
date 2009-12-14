<?php

include "lib/setup.php";

foreach (array ("variant_impact", "variant_dominance",
		"summary_short", "summary_long", "talk_text",
		"article_pmid") as $k) {
  $fields_allowed[$k] = 1;
}

$response = array();
$edits_to_submit = array();
foreach ($_POST as $param => $newvalue)
{
  if (ereg ("^edit_id__", $param)) {
    $response[$param] = $newvalue;
    continue;
  }

  if (!ereg ("^edited_", $param)) continue;

  if (!ereg ("_v_([0-9]+)_", $param, $regs)) continue;
  $variant_id = $regs[1];

  if (!ereg ("_p_([0-9]+)_", $param, $regs)) continue;
  $previous_edit_id = $regs[1];

  if (!preg_match ("/_f_([a-z_0-9]+?)__/", $param, $regs)) continue;
  $field_id = $regs[1];
  if (!array_key_exists ($field_id, $fields_allowed)) continue;

  if (!($edit_id = $response["edit_id__$previous_edit_id"]) &&
      !($client_sent_edit_id = $_POST["edit_id__$previous_edit_id"])) {
    $oldrow = theDb()->getRow ("SELECT * FROM edits WHERE edit_id=? AND is_draft=0",
			       array($previous_edit_id));
    if (theDb()->isError($oldrow) || !$oldrow) {
      // TODO: convey error on client side
      $response["errors"][] = "item you're editing doesn't exist";
      continue;
    }
    $newrow = $oldrow;
    $newrow["previous_edit_id"] = $oldrow["edit_id"];
    $newrow["edit_oid"] = getCurrentUser("oid");
    $newrow["is_draft"] = 1;
    unset($newrow["edit_timestamp"]);
    unset($newrow["edit_id"]);
    $columnlist = "";
    $valuelist = array();
    foreach ($newrow as $k => $v) {
      $columnlist .= ", $k=?";
      $valuelist[] = $v;
    }
    $q = theDb()->query ("INSERT INTO edits SET edit_timestamp=NOW() $columnlist",
			 $valuelist);
    if (theDb()->isError($q)) {
      // TODO: convey error on client side
      $response["errors"][] = $q->getMessage();
      continue;
    }
    $new_edit_id = theDb()->getOne ("SELECT LAST_INSERT_ID()");
    if ($new_edit_id < 1) {
      // TODO: convey error on client side
      $response["errors"][] = "edit_id $edit_id does not make sense";
      continue;
    }
  }
  if (!$edit_id)
    $edit_id = theDb()->getOne ("SELECT MIN(edit_id) FROM edits
				WHERE previous_edit_id=? AND edit_oid=? AND is_draft=1",
				array ($previous_edit_id, getCurrentUser("oid")));
  if ($new_edit_id && $new_edit_id != $edit_id) {
    // I just created a new row but this user already has edits in another row
    theDb()->query ("DELETE FROM edits WHERE edit_id=?", $new_edit_id);
  }

  // Tell the client to provide this edit id next time
  $response["edit_id__$previous_edit_id"] = $edit_id;

  $q = theDb()->query ("UPDATE edits SET $field_id=?, edit_timestamp=NOW()
			WHERE edit_id=? AND edit_oid=? AND is_draft=1",
		       array($newvalue, $edit_id, getCurrentUser("oid")));
  if (!theDb()->isError($q))
    $response[ereg_replace ("^edited", "saved", $param)] = $newvalue;
  else
    $response["errors"][] = $q->getMessage();

  if ($_POST["submit_flag"])
    $edits_to_submit[$edit_id] = 1;
}

if (!$response["errors"]) {
  foreach ($edits_to_submit as $edit_id => $x) {
    evidence_submit ($edit_id);
    $response["please_reload"] = true;
  }
}

header ("Content-type: application/json");
print json_encode ($response);

?>
