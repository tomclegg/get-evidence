<?php
    ;

// Written by Madeleine Price Ball & Clinical Future, see git-blame.
// See GET-Evidence project for licensing.

function fishersexact_compute ($figs, $htmlformat=FALSE)
{
    // Based on Wikipedia's description (as of Nov 12 2010) of the
    // R implementation: to test all distributions "at least as extreme" take
    // all others with P-values less than or equal to the original are added
    // to get the sum.
    if ( ($figs["case_neg"] + $figs["case_pos"]) < 1 || 
	 ($figs["control_neg"] + $figs["control_pos"]) < 1)
	return $htmlformat ? "-<SPAN class=\"invisible\">.000</SPAN>" : "-";
    
    $fisher_p_sum = 0.1;

    $fisher_a = $figs["case_pos"];
    $fisher_b = $figs["case_neg"];
    $fisher_c = $figs["control_pos"];
    $fisher_d = $figs["control_neg"];

    $fisher_p_orig = fishersexact_getprob($fisher_a, $fisher_b, $fisher_c, $fisher_d);
    $fisher_p_sum = $fisher_p_orig;
    $fisher_a_b = $fisher_a + $fisher_b;
    $fisher_a_c = $fisher_a + $fisher_c;
    $fisher_b_d = $fisher_b + $fisher_d;
    $fisher_sum = $fisher_a + $fisher_b + $fisher_c + $fisher_d;
    // Sum over all scenarios with at-least-as-extreme p-values
    for ($fisher_newa = 0; $fisher_newa <= $fisher_sum; $fisher_newa++) {
	if ($fisher_newa == $fisher_a) {
	    continue;
	}
	$fisher_newb = $fisher_a_b - $fisher_newa;
	$fisher_newc = $fisher_a_c - $fisher_newa;
	$fisher_newd = $fisher_b_d - $fisher_newb;
	if ($fisher_newb >= 0 && $fisher_newc >= 0 && $fisher_newd >= 0) {
	    $fisher_p_new = fishersexact_getprob($fisher_newa, $fisher_newb,
					 $fisher_newc, $fisher_newd);
	    if ($fisher_p_new <= $fisher_p_orig) {
		$fisher_p_sum = $fisher_p_sum + $fisher_p_new;
	    }
	}
    }
    // More than 1 in a p value is nonsensical, set to 1.
    if ($fisher_p_sum > 1) $fisher_p_sum = 1;

    $fisher_result = sprintf("%.4f", $fisher_p_sum);
    return $fisher_result;
}

function fishersexact_getprob ($fisher_1a, $fisher_1b, $fisher_1c, $fisher_1d) 
{
    $fisher_1n = $fisher_1a + $fisher_1b + $fisher_1c + $fisher_1d;
    $fisher_log_num = log_factorial($fisher_1a + $fisher_1b) + 
	log_factorial($fisher_1c + $fisher_1d) +
        log_factorial($fisher_1a + $fisher_1c) + 
	log_factorial($fisher_1b + $fisher_1d);
    $fisher_log_denom = log_factorial($fisher_1a) + 
	log_factorial($fisher_1b) + log_factorial($fisher_1c) + 
	log_factorial($fisher_1d) + log_factorial($fisher_1n);
    $fisher_log_result = $fisher_log_num - $fisher_log_denom;
    return exp($fisher_log_result);
}

function log_factorial ($logfac_n) {
    if ($logfac_n == 0) {
        return 0;
    } else {
        $logfac_sum = 0;
        for ($logfac_i = $logfac_n; $logfac_i >= 1; $logfac_i--) {
            $logfac_sum = $logfac_sum + log($logfac_i);
        }
        return $logfac_sum;
    }
}

?>
