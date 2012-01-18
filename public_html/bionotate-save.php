<?php ; // -*- mode: java; c-basic-offset: 2; tab-width: 8; indent-tabs-mode: nil; -*-

// Copyright 2011 Clinical Future, Inc.
// Authors: see git-blame(1)

include "lib/setup.php";

$variant_name = evidence_get_variant_name($_REQUEST['variant_id'], '-', true);
$bionotate_key = $_REQUEST['article_pmid'] . '-' . $variant_name;
$html_or_xml = file_get_contents ('http://bionotate.biotektools.org/GET-Evidence/retrieve/'.$bionotate_key.'?oid='.urlencode(getCurrentUser('oid')));
if (!preg_match ('{SNIPPET_XML = "(.*)";?\r?\n}', $html_or_xml, $regs) &&
    !preg_match ('{^(<\?xml .*)}is', $html_or_xml, $regs))
  exit ("No snippet found at .../$bionotate_key");
$xml = preg_replace('{>\\r?\\n\\s*}', '>', $regs[1]);

$snap_row = theDb()->getRow ("SELECT * FROM snap_latest WHERE variant_id=? AND article_pmid=? AND genome_id=0 AND disease_id=0 LIMIT 1",
                         array ($_REQUEST['variant_id'], $_REQUEST['article_pmid']));
if (theDb()->isError($snap_row))
  exit($edit->getMessage());

$edit = theDb()->getRow ("SELECT * FROM edits WHERE variant_id=? AND article_pmid=? AND genome_id=0 AND disease_id=0 AND edit_oid=? AND previous_edit_id=? ORDER BY edit_id DESC LIMIT 1",
			 array ($_REQUEST['variant_id'], $_REQUEST['article_pmid'], getCurrentUser('oid'), $snap_row['edit_id']));

if (theDb()->isError($edit))
  exit($edit->getMessage());

if ($edit && $edit['is_draft']) {
  theDb()->query ("UPDATE edits SET edit_timestamp=NOW(), summary_long=? WHERE edit_id=?",
                  array ($xml, $edit['edit_id']));
}
else {
  $edit = $snap_row;
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
header("Location: /{$variant_name}#a".$_REQUEST['article_pmid']);
