<?php ; // -*- mode: java; c-basic-offset: 2; tab-width: 8; indent-tabs-mode: nil; -*-

// Copyright 2011 Clinical Future, Inc.
// Authors: see git-blame(1)

include "lib/setup.php";

// error_log(json_encode($_REQUEST));

$variant_name = evidence_get_variant_name($_REQUEST['variant_id'], '-', true);
$bionotate_key = $_REQUEST['article_pmid'] . '-' . $variant_name;
if (!preg_match ('{SNIPPET_XML = "(.*)";?\r?\n}',
                 $html = file_get_contents ('http://genome2.ugr.es/bionotate2/GET-Evidence/'.$bionotate_key.'?oid='.urlencode(getCurrentUser('oid'))),
                 $regs))
  exit ("No snippet found at .../$bionotate_key");
$xml = $regs[1];

$edit = theDb()->getRow ("SELECT * FROM edits WHERE variant_id=? AND article_pmid=? AND genome_id=0 AND disease_id=0 AND edit_oid=? ORDER BY edit_id DESC LIMIT 1",
			 array ($_REQUEST['variant_id'], $_REQUEST['article_pmid'], getCurrentUser('oid')));
if (theDb()->isError($edit))
  exit($edit->getMessage());

if ($edit && $edit['is_draft']) {
  theDb()->query ("UPDATE edits SET edit_timestamp=NOW(), summary_long=? WHERE edit_id=?",
                  array ($xml, $edit['edit_id']));
}
else {
  $edit = theDb()->getRow ("SELECT * FROM snap_latest WHERE variant_id=? AND article_pmid=? AND genome_id=0 AND disease_id=0 LIMIT 1",
                           array ($_REQUEST['variant_id'], $_REQUEST['article_pmid']));
  if (theDb()->isError($edit))
    exit($edit->getMessage());
  $edit['edit_oid'] = getCurrentUser('oid');
  $edit['previous_edit_id'] = $edit['edit_id'];
  $edit['is_draft'] = 1;
  $edit['is_delete'] = 0;
  unset($edit['edit_timestamp']);
  unset($edit['edit_id']);
  unset($edit['signoff_oid']);
  unset($edit['signoff_timestamp']);
  $edit['summary_long'] = $xml;
  $columnlist = "";
  $valuelist = array();
  foreach ($edit as $k => $v) {
    $columnlist .= ", $k=?";
    $valuelist[] = $v;
  }
  $q = theDb()->query (($sql="INSERT INTO edits SET edit_timestamp=NOW() $columnlist"),
                       $valuelist);
  if (theDb()->isError($q)) {
    exit($q->getMessage()); // . json_encode(array($sql, $valuelist, $q)));
  }
  $new_edit_id = theDb()->getOne ("SELECT LAST_INSERT_ID()");
  if ($new_edit_id < 1) {
    exit("edit_id $edit_id does not make sense");
  }
}
header("Location: /{$variant_name}");
