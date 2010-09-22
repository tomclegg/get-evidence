<?php

function eval_suff ($variant_quality)
{
    if (strlen($variant_quality) < 6) {
        return False;
    } else {
        if ($variant_quality[2] == "-" and $variant_quality[3] == "-") {
            return False;
        } elseif ($variant_quality[4] == "-" and $variant_quality[5] == "-") {
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



?>
