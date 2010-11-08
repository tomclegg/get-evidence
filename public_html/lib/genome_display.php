<?php

function genome_display($shasum, $oid) {
    $db_query = theDb()->getAll ("SELECT nickname FROM private_genomes WHERE shasum=? AND oid=?",
                                            array($shasum, $oid));
    $returned_text = "<h1>Genome report for " . "</h1>\n";

    $results_file = "/home/trait/upload/" . $shasum . "-out/get-evidence.json";
    if (file_exists($results_file)) {
        $lines = file($results_file);
        foreach ($lines as $line) {
            $variant_data = json_decode($line, true);
            if ($variant_data["suff_eval"]) {
                $variant_data["clinical"] = eval_clinical($variant_data["variant_quality"]);
                $variant_data["evidence"] = eval_evidence($variant_data["variant_quality"]);
                $suff_eval_variants[] = $variant_data;
            } else {
                $insuff_eval_variants[] = $variant_data;
            }
            #$returned_text .= $line . "<br>\n";
        }

        usort($suff_eval_variants, "sort_reviewed");
        $returned_text .= "<h1>GET-Evidence evaluated variants:</h1>\n";
        $returned_text .= "<TABLE class=\"report_table\"><TR><TH>Variant</TH>"
                            . "<TH>Clinical Importance</TH>"
                            . "<TH>Evidence</TH>"
                            . "<TH>Impact</TH>"
                            . "<TH>Summary</TH></TR>\n";
        foreach ($suff_eval_variants as $variant) {
            $var_id = "";
            if (array_key_exists("amino_acid_change", $variant)) {
                $var_id = $variant["gene"] . "-" . $variant["amino_acid_change"];
            } else if (array_key_exists("dbSNP", $variant)) {
                $var_id = "rs" . $variant["dbSNP"];
            }
            if (strlen($var_id) > 0) {
                $returned_text .= "<TR><TD><A HREF=http://evidence.personalgenomes.org/"
                    . $var_id . " TARGET=\"_blank\">" . $var_id . "</A></TD><TD>"
                    . $variant["clinical"] . "</TD><TD>"
                    . $variant["evidence"] . "</TD><TD>"
                    . $variant["variant_impact"] . "</TD><TD>"
                    . $variant["summary_short"] . "</TD></TR>\n";
            }
        }
       $returned_text .= "</TABLE>\n";

        usort($insuff_eval_variants, "sort_by_autoscore");
        $returned_text .= "<h1>Insufficiently reviewed variants:</h1>\n";
        $returned_text .= "<TABLE class=\"report_table\"><TR><TH>Variant</TH>"
                            . "<TH>Autoscore</TH>"
                            . "<TH>Summary</TH></TR>\n";
        foreach ($insuff_eval_variants as $variant) {
            $var_id = "";
            if (array_key_exists("amino_acid_change", $variant)) {
                $var_id = $variant["gene"] . "-" . $variant["amino_acid_change"];
            } else if (array_key_exists("dbSNP", $variant)) {
                $var_id = "rs" . $variant["dbSNP"];
            }
            if (strlen($var_id) > 0) {
                if ($variant["GET-Evidence"]) {
                    $returned_text .= "<TR><TD><A HREF=http://evidence.personalgenomes.org/"
                            . $var_id . " TARGET=\"_blank\">" . $var_id . "</A></TD><TD>";
                } else {
                    $returned_text .= "<TR><TD>" . $var_id
                            . "<BR><A HREF=http://evidence.personalgenomes.org/"
                            . $var_id . ">Create GET-Evidence entry</A></TD><TD>";
                }
                $returned_text .= $variant["autoscore"] . "</TD><TD>";
                if (array_key_exists("summary_short", $variant)
                                    and strlen($variant["summary_short"]) > 0) {
                    $returned_text .= $variant["summary_short"] . "</TD></TR>\n";
                } else {
                    $returned_text .= autoscore_evidence($variant) . "</TD></TR>\n";
                }
            }
        }
        $returned_text .= "</TABLE>\n";
    } else {
        $returned_text = "Sorry, the results file for this genome is not available. "
                    . "This may be because genome data has not finished processing.";
    }
    return($returned_text);
}


function eval_evidence($variant_quality) {
    if (strlen($variant_quality) > 5) {
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

function eval_clinical($variant_quality) {
    if (strlen($variant_quality) > 5) {
        $sum = 0;
        $scores = preg_split('//', $variant_quality, -1, PREG_SPLIT_NO_EMPTY);
        for ($i = 4; $i <= 5; $i++) {
            if ($scores[$i] == "-") {
                $scores[$i] = 0;
            } else if ($scores[$i] == "!") {
                $scores[$i] = -1;
            }
        }
        if ($scores[4] >= 4 or
            ($scores[4] >= 3 and $scores[5] >= 4)) {
            return "High";
        } elseif ($scores[4] >= 3 or
            ($scores[4] >= 2 and $scores[5] >= 3)) {
            return "Moderate";
        } else {
            return "Low";
        }
    } else {
        return "Low";
    }
}

function autoscore_evidence($variant) {
    $items = array();
    if (array_key_exists("in_omim", $variant) and $variant["in_omim"]) {
        $items[] = "In OMIM";
    }
    if (array_key_exists("in_gwas", $variant) and $variant["in_gwas"]) {
        $items[] = "In HuGENet GWAS";
    }
    if (array_key_exists("in_pharmgkb", $variant) and $variant["in_pharmgkb"]) {
        $items[] = "In PharmGKB";
    }
    if (array_key_exists("disruptive", $variant) and $variant["disruptive"]) {
        $items[] = "Disruptive amino acid change";
    }
    if (array_key_exists("nonsense", $variant) and $variant["nonsense"]) {
        $items[] = "Nonsense mutation";
    }
    if (array_key_exists("testable", $variant) and $variant["testable"] == 1) {
        if (array_key_exists("reviewed", $variant) and $variant["reviewed"] == 1) {
            $items[] = "Testable gene in GeneTests with associated GeneReview";
        } else {
            $items[] = "Testable gene in GeneTests";
        }
    }
    $returned_text = implode(", ",$items);
    return $returned_text;
}

function sort_reviewed($a, $b) {
    $impact_sort_order = array("pathogenic", "pharmacogenetic",
                                "protective", "benign");
    $clinical_sort_order = array("High", "Moderate", "Low");
    $evidence_sort_order = array("Well-established", "Likely", "Uncertain");
    $cmpa = array_search($a['variant_impact'], $impact_sort_order);
    $cmpb = array_search($b['variant_impact'], $impact_sort_order);
    if ($cmpa < $cmpb) { return -1; }
    if ($cmpa > $cmpb) { return 1; }
    $cmpa = array_search($a['clinical'], $clinical_sort_order);
    $cmpb = array_search($b['clinical'], $clinical_sort_order);
    if ($cmpa < $cmpb) { return -1; }
    if ($cmpa > $cmpb) { return 1; }
    $cmpa = array_search($a['evidence'], $evidence_sort_order);
    $cmpb = array_search($b['evidence'], $evidence_sort_order);
    if ($cmpa < $cmpb) { return -1; }
    if ($cmpa > $cmpb) { return 1; }
    return 0;
}

function sort_by_autoscore($a, $b) {
    if ($a['autoscore'] == $b['autoscore']) {
        return strnatcmp($a['gene']."-".$a['amino_acid_change'],
                        $b['gene']."-".$a['amino_acid_change']);
    } else {
        return ($a['autoscore'] > $b['autoscore']) ? -1: 1;
    }
}


?>
