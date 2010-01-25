<?php

  // Copyright 2009 Scalable Computing Experts
  // Author: Tom Clegg

function oddsratio_compute ($figs, $htmlformat=FALSE)
{
    if ($figs["case_neg"] < 1) return $htmlformat ? "-<SPAN class=\"invisible\">.000</SPAN>" : "-";
    $control_neg = $figs["control_neg"];
    $control_pos = $figs["control_pos"];
    if ($control_pos < 1) {
	$control_pos = 1;
	$control_neg++;
    }
    if ($control_neg == 0) return "-";
    $or = sprintf ("%.3f", (($figs["case_pos"] / $figs["case_neg"]) /
			    ($control_pos / $control_neg)));
    if ($or > 1000) {
	$or = round($or);
	if ($htmlformat)
	    $or .= "<SPAN class=\"invisible\">.000</SPAN>";
    }
    return $or;
}

?>
