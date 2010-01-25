<?php

  // Copyright 2009 Scalable Computing Experts
  // Author: Tom Clegg

function oddsratio_compute ($figs)
{
    if ($figs["case_neg"] < 1) return "-";
    $control_neg = $figs["control_neg"];
    $control_pos = $figs["control_pos"];
    if ($control_pos < 1) {
	$control_pos = 1;
	$control_neg++;
    }
    if ($control_neg == 0) return "-";
    return (($figs["case_pos"] / $figs["case_neg"]) /
	    ($control_pos / $control_neg));
}

?>
