<?php

  // Copyright 2009 Scalable Computing Experts
  // Author: Tom Clegg

require_once (dirname(dirname(dirname(__FILE__)))."/textile-2.0.0/classTextile.php");

$gTheTextile = new Textile;

function editable($id, $content, $title="", $options=false)
{
  global $gTheTextile;
  $html = $gTheTextile->textileRestricted ($content);
  if (trim($html) == "") $html = "<P>&nbsp;</P>";

  if (!getCurrentUser()) return ("<P class=\"toolbar\">$title</P>" .
				 $html);

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
	  "<P id=\"toolbar_$id\" class=\"toolbar\">$title</P>" .
	  "<SPAN id=\"preview_$id\">".$html."</SPAN>" .
	  $selector .
	  "<INPUT type=\"hidden\" id=\"orig_$id\" value=\"".htmlentities($content)."\"/>" .
	  "</SPAN>");
}

?>
