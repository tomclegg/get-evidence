<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

$bp_a2n = array ("A" => 1,
		 "C" => 2,
		 "G" => 4,
		 "T" => 8,
		 "M" => 3,	// A/C
		 "R" => 5,	// A/G
		 "W" => 9,	// A/T
		 "S" => 6,	// C/G
		 "Y" => 10,	// C/T
		 "K" => 12,	// G/T
		 "V" => 7,	// A/C/G
		 "H" => 11,	// A/C/T
		 "D" => 13,	// A/G/T
		 "B" => 14,	// C/G/T
		 "N" => 15);	// A/C/G/T
$bp_n2a = array_flip ($bp_a2n);

function bp_flatten ($bp)
{
    global $bp_n2a;
    global $bp_a2n;
    $r = 0;
    for ($i=0; $i < strlen($bp); $i++) {
	$x = substr($bp,$i,1);
	if (isset ($bp_a2n[$x]))
	    $r |= $bp_a2n[$x];
    }
    return $bp_n2a[$r];
}

?>
