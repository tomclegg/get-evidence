<?php

  // Copyright 2009 Scalable Computing Experts
  // Author: Tom Clegg

function oddsratio_compute ($figs)
{
    if ($figs["case_neg"] > 0)
	return (($figs["case_pos"] / $figs["case_neg"]) /
		(($figs["control_pos"]+1) / ($figs["control_neg"]+1)));
    else
	return "-";
}

?>
