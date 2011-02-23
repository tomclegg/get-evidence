<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

function arrays_partially_equal (&$a, &$b, &$relevant_fields)
{
    foreach ($relevant_fields as &$key)
	if (!array_key_exists ($key, $a) ||
	    !array_key_exists ($key, $b) ||
	    "".$a[$key] != $b[$key])
	    return FALSE;
    return TRUE;
}

function humanreadable_size ($bytes)
{
    if ($bytes >= 2000000000) return floor($bytes / 1000000000) . " GB";
    if ($bytes >= 2000000) return floor($bytes / 1000000) . " MB";
    if ($bytes >= 2000) return floor($bytes / 1000) . " KB";
    return $bytes . " B";
}

?>
