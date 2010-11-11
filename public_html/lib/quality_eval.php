<?php

function quality_eval_suff ($variant_quality, $impact="pathogenic")
{
    if (strlen($variant_quality) < 7) {
        return False;
    } else {
        if ($variant_quality[2] == "-" and $variant_quality[3] == "-") {
            return False;
        } elseif ($variant_quality[4] == "-" and $variant_quality[5] == "-"
                    and $variant_quality[6] == "-"
                    and $impact != "benign" and $impact != "protective") {
            return False;
        } else {
            for ($i=0; $i<6; $i++) {
                if ($variant_quality[$i] != "-") {
                    $num_eval++;
                }
            }
            if ($num_eval >= 4) {
                return True;
            } else {
                return False;
            }
        }
    }
}

function quality_eval_clinical($variant_quality) {
    // ---  High clinical importance requires:
    // * Penetrance score 3 or greater (>=5% attributable risk)
    // AND
    // * Severity >= 4 OR Severity >= 3 and Treatability >= 4
    // ---  Moderate clinical importance requires:
    // * Penetrance score 2 or greater (>=1% attributable risk)
    // AND
    // * Severity >= 3 OR Severity >= 2 and Treatability >= 3
    // ---  Low clinical importance
    // All variants who fail to meet above criteria.
    if (strlen($variant_quality) > 6) {
        $sum = 0;
        $scores = preg_split('//', $variant_quality, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 4; $i <= 6; $i++) {
            if ($scores[$i] == "-") {
                $scores[$i] = 0;
            } else if ($scores[$i] == "!") {
                $scores[$i] = -1;
            }
        }
        if ($scores[6] >= 3 and ($scores[4] >= 4 or
            ($scores[4] >= 3 and $scores[5] >= 4))) {
            return "High";
        } elseif ($scores[6] >= 2 and ($scores[4] >= 3 or
            ($scores[4] >= 2 and $scores[5] >= 3))) {
            return "Moderate";
        } else {
            return "Low";
        }
    } else {
        return "Low";
    }
}

function quality_eval_evidence($variant_quality) {
    if (strlen($variant_quality) > 6) {
        $sum = 0;
        $scores = preg_split('//', $variant_quality, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 0; $i <= 3; $i++) {
            if ($scores[$i] == "-") {
                $scores[$i] = 0;
            } else if ($scores[$i] == "!") {
                $scores[$i] = -1;
            }
        }
        $sum = $scores[0] + $scores[1] + $scores[2] + $scores[3];
        if ( ($scores[2] >= 4 or $scores[3] >= 4)
            and $sum >= 8 ) {
            return "Well-established";
        } else if ( ($scores[2] >= 3 or $scores[3] >= 3)
                    and $sum >= 5 ) {
            return "Likely";
        } else {
            return "Uncertain";
        }
    } else {
        return "Uncertain";
    }
}

function quality_eval_qualify_impact ($scores, $impact)
{
    if (quality_eval_suff($scores, $impact)) {
        $qualify_clinical = strtolower(quality_eval_clinical($scores));
        $qualify_evidence = quality_eval_evidence($scores);
        if ($qualify_evidence = "Well-established") {
            $qualify_evidence = "";
        }
        $impact = $qualify_clinical . " clinical importance, " . $qualify_evidence . " " . $impact;
    } else {
        $impact = "insufficiently evaluated " . $impact;
    }
    return ucfirst($impact);
}


?>
