<?php

  // Copyright 2009 Scalable Computing Experts
  // Author: Tom Clegg

require_once (dirname(dirname(dirname(__FILE__)))."/textile-2.0.0/classTextile.php");

$gTheTextile = new Textile;

function editable($id, $content, $title="")
{
  global $gTheTextile;
  $html = $gTheTextile->textileRestricted ($content);
  if (trim($html) == "") $html = "<P>&nbsp;</P>";

  if (!getCurrentUser()) return $html;

  return ("<SPAN id=\"$id\" class=\"editable\">" .
	  "<P id=\"toolbar_$id\" class=\"toolbar\">$title</P>" .
	  "<SPAN id=\"preview_$id\">".$html."</SPAN>" .
	  "<INPUT type=\"hidden\" id=\"orig_$id\" value=\"".htmlentities($content)."\"/>" .
	  "</SPAN>");
}

?>
