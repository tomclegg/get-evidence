<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

function oddsratio_compute ($figs, $htmlformat=FALSE)
{
    $control_neg = $figs["control_neg"];
    $control_pos = $figs["control_pos"];
    $case_neg = $figs["case_neg"];
    $case_pos = $figs["case_pos"];
    if ($case_neg + $case_pos == 0 || $control_neg == 0) 
	return "-";
    if ($case_neg == 0 || $control_pos == 0)
	return ($htmlformat ? "&#x221e;" : "INF");
    $or = sprintf ("%.3f", (($case_pos / $case_neg) /
			    ($control_pos / $control_neg)));
    if ($or > 1000) {
	$or = round($or);
	if ($htmlformat)
	    $or .= "<SPAN class=\"invisible\">.000</SPAN>";
    }
    return $or;
}

?>
