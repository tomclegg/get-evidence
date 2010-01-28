<?php

  // Copyright 2009 Scalable Computing Experts
  // Author: Tom Clegg

require_once (dirname(dirname(dirname(__FILE__)))."/textile-2.0.0/classTextile.php");
require_once ("lib/oddsratio.php");

$gTheTextile = new Textile;
$gDisableEditing = FALSE;

function editable($id, $content, $title="", $options=false)
{
  global $gDisableEditing;
  global $gTheTextile;

  if (ereg ('__oddsratio$', $id))
    return editable_oddsratio ($id, $content, $title, $options);
  else {
    $html = $gTheTextile->textileRestricted ($content);
    if (trim($html) == "") $html = "<P>&nbsp;</P>";

    if (strlen($title) <  60 && !preg_match ('{<(b|strong)\b}i', $title))
      $title = "<strong>$title</strong>";
  }

  $html = "<DIV id=\"toolbar_$id\" class=\"toolbar\">"
    . "<P class=\"toolbar_title\">$title</P></DIV>"
    . "<SPAN id=\"preview_$id\">"
    . $html
    . "</SPAN>";

  if ($gDisableEditing || !getCurrentUser())
    return $html;

  $selector = "";
  if ($options && is_array($options["select_options"])) {
    $selector = "<P style=\"display:none;\"><SELECT id=\"edited_$id\" name=\"edited_$id\" onchange=\"editable_check_unsaved(this); editable_save();\">\n";
    foreach ($options["select_options"] as $k => $v) {
      $selected = ($content == $v) ? " selected" : "";
      $selector .= "<OPTION value=\"".htmlentities($k)."\"$selected>".htmlspecialchars($v)."</OPTION>\n";
    }
    $selector .= "</SELECT></P>";
  }
  return ("<SPAN id=\"$id\" class=\"editable\">" .
	  (strlen($options["tip"]) ? "<P class=\"csshide\" id=\"tip_$id\">$options[tip]</P>" : "") .
	  $html .
	  $selector .
	  "<INPUT type=\"hidden\" id=\"orig_$id\" value=\"".htmlentities($content)."\"/>" .
	  "</SPAN>");
}

function editable_oddsratio ($id, $content, $title, $options)
{
  global $gDisableEditing;
  $editable = !$gDisableEditing && getCurrentUser();

  $html = "";
  if ($content == "")
    $figs = array();
  else
    $figs = json_decode ($content, true);
  $trclass = ($options["rownumber"] % 4 < 2) ? " class=\"altcolor\"" : "";
  $html .= "<TR$trclass>";
  $html .= "<TD class=\"rowlabel\">$title</TD>";
  $empty = 1;
  foreach (array ("case_pos", "case_neg", "control_pos", "control_neg") as $x) {
    $cellid = "{$id}__o_{$x}__";
    if (!isset ($figs[$x]) || !strlen ($figs[$x])) {
      if (!$editable)
	$figs[$x] = "-";
    }
    else {
      $empty = 0;
      $figs[$x] = $figs[$x] + 0;
    }

    $cell = $figs[$x];
    if ($editable) {
      $html .= "<TD id=\"$cellid\" class=\"editable clicktoedit\"><SPAN id=\"preview_$cellid\">{$cell}</SPAN><INPUT type=\"hidden\" id=\"orig_$cellid\" name=\"orig_$cellid\" value=\"".htmlentities($figs[$x])."\"/><SPAN id=\"clicktoedit_$cellid\"></SPAN></TD>\n";
    }
    else {
      $html .= "<TD>{$cell}</TD>\n";
    }
  }
  $OR = oddsratio_compute($figs, true);
  if ($OR != "-") $OR = "<STRONG>$OR</STRONG>";
  $html .= "<TD>$OR</TD>\n";
  $html .= "</TR>\n";
  if ($empty && !$editable) return "";
  return $html;
}

?>
