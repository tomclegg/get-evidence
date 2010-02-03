<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

function arrays_partially_equal (&$a, &$b, &$relevant_fields)
{
    foreach ($relevant_fields as &$key)
	if (!array_key_exists ($key, $a) ||
	    !array_key_exists ($key, $b) ||
	    "".$a[$key] != $b[$key])
	    return FALSE;
    return TRUE;
}

?>
