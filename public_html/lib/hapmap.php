<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

$hapmap_label = FALSE;

function hapmap_expand_label ($label)
{
    global $hapmap_label;
    if ($hapmap_label === FALSE) {
	$hapmap_label = array ("afs" => "African",
			       "asc" => "Gujarati",
			       "ase" => "Southeast Asian",
			       "asw" => "African ancestry in Southwest USA",
			       "ceu" => "Utah residents with Northern and Western European ancestry from the CEPH collection",
			       "chb" => "Han Chinese in Beijing, China",
			       "chd" => "Chinese in Metropolitan Denver, Colorado",
			       "eur" => "European",
			       "gih" => "Gujarati Indians in Houston, Texas",
			       "jpt" => "Japanese in Tokyo, Japan",
			       "lwk" => "Luhya in Webuye, Kenya",
			       "mex" => "Mexican ancestry in Los Angeles, California",
			       "mex" => "Mexican",
			       "mkk" => "Maasai in Kinyawa, Kenya",
			       "tsi" => "Toscans in Italy",
			       "yri" => "Yoruba in Ibadan, Nigeria");
    }
    if (isset ($hapmap_label[$label]))
	return $hapmap_label[$label];
    else
	return FALSE;
}

function hapmap_add_tips_callback ($regs)
{
    $expanded = hapmap_expand_label (strtolower ($regs[1]));
    if (strlen($expanded) > 20)
	return "<SPAN onmouseover=\"Tip('".htmlspecialchars($expanded,ENT_QUOTES)."');\" onmouseout=\"UnTip();\">$regs[1]</SPAN>";
    else if ($expanded !== FALSE)
	return $expanded;
    else
	return $regs[1];
}

function hapmap_add_tips ($string)
{
    return preg_replace_callback ('/\b([a-z]{3})\b/i', 'hapmap_add_tips_callback', $string);
}

?>