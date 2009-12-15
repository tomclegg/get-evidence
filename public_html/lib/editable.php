<?php

  // Copyright 2009 President and Fellows of Harvard College
  // Author: Tom Clegg

require_once (dirname(dirname(dirname(__FILE__)))."/textile-2.0.0/classTextile.php");

$gTheTextile = new Textile;

function editable($id, $content)
{
  global $gTheTextile;
  $html = $gTheTextile->textileThis ($content);
  if (trim($html) == "") $html = "<P>&nbsp;</P>";

  if (!getCurrentUser()) return $html;

  return ("<SPAN id=\"$id\" class=\"editable\">" .
	  "<SPAN id=\"preview_$id\">".$html."</SPAN>" .
	  "<INPUT type=\"hidden\" id=\"orig_$id\" value=\"".htmlentities($content)."\"/>" .
	  "</SPAN>");
}

?>
