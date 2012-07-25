#!/usr/bin/php
<?php ;

// Copyright: see COPYING
// Authors: see git-blame(1)

chdir ('public_html');
include "lib/setup.php";

evidence_create_tables();

$q = theDb()->query ("INSERT IGNORE INTO editor_summary (oid) SELECT oid FROM eb_users");
if (theDb()->isError($q)) die ($q->getMessage());

$q = theDb()->query ("SELECT DISTINCT oid FROM eb_users");
if (theDb()->isError($q)) die ($q->getMessage());

while ($editor =& $q->fetchRow()) {
    $votes = theDb()->getOne
	("SELECT COUNT(*) FROM web_vote_latest
	WHERE vote_oid=?",
	 array($editor["oid"]));
    $u = theDb()->query
	("REPLACE INTO editor_summary (oid, total_edits, latest_edit, webvotes)
	SELECT edit_oid,
	 COUNT(*) new_total_edits,
	 MAX(edit_timestamp) new_latest_edit,
	 ?
	FROM edits
	WHERE edit_oid=? AND is_draft=0
	GROUP BY edit_oid",
	 array($votes, $editor["oid"]));
    if (theDb()->isError($u)) die ($u->getMessage());

    $r = theDb()->getRow ("SELECT * FROM editor_summary LEFT JOIN eb_users ON eb_users.oid=editor_summary.oid WHERE editor_summary.oid=?",
			  array($editor["oid"]));
    if (theDb()->isError($r)) die ($r->getMessage());
}
