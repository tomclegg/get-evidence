<?php

require_once ("lib/quality_eval.php");

function genome_display($shasum, $oid) {
    $db_query = theDb()->getAll ("SELECT nickname FROM private_genomes WHERE shasum=? AND oid=?",
                                            array($shasum, $oid));
    $returned_text = "<h1>Genome report for " . $db_query[0]['nickname'] . "</h1>\n";

    $returned_text .= "<form action=\"/genome_download.php\" method=\"GET\">\n"
                    . "<input type=\"hidden\" name=\"download_genome_id\" value=\""
                    . $shasum . "\">\n"
                    . "<input type=\"hidden\" name=\"download_nickname\" value=\""
		    . htmlspecialchars($db_query[0]['nickname']) . "\">\n"
                    . "<input type=\"submit\" value=\"Download source data\" "
                    . "class=\"button\" \/>";

    $results_file = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum . "-out/get-evidence.json";
    if (file_exists($results_file)) {
        $lines = file($results_file);
        foreach ($lines as $line) {
            $variant_data = json_decode($line, true);
	    if (!$variant_data) continue; // sometimes we can't read python's json??
            # Get allele frequency
            if (array_key_exists("num",$variant_data) and array_key_exists("denom",$variant_data)) {
                $allele_freq = 100 * ($variant_data["num"] / $variant_data["denom"]);
                if ($allele_freq > 10) {
                    $variant_data["allele_freq"] = sprintf("%d%%", $allele_freq);
                } elseif ($allele_freq > 1) {
                    $variant_data["allele_freq"] = sprintf("%.1f%%", $allele_freq);
                } elseif ($allele_freq > 0.1) {
                    $variant_data["allele_freq"] = sprintf("%.2f%%", $allele_freq);
                } else {
                    $variant_data["allele_freq"] = sprintf("%.3f%%", $allele_freq);
                }
            } else {
                $variant_data["allele_freq"] = "?";
            }
            # Get zygosity
            $eval_zyg_out = eval_zygosity( $variant_data["variant_dominance"],
                                            $variant_data["genotype"],
                                            $variant_data["ref_allele"]);
            $variant_data["suff_eval"] = quality_eval_suff($variant_data["variant_quality"], $variant_data["variant_impact"]);
            if ($variant_data["suff_eval"]) {
                $variant_data["clinical"] = quality_eval_clinical($variant_data["variant_quality"]);
                $variant_data["evidence"] = quality_eval_evidence($variant_data["variant_quality"]);
                $variant_data["expect_effect"] = $eval_zyg_out[0];
                $variant_data["zygosity"] = $eval_zyg_out[1];
                $variant_data["inheritance_desc"] = $eval_zyg_out[2];
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
                            . "<TH>Allele freq</TH>"
                            . "<TH>Summary</TH></TR>\n";
        foreach ($suff_eval_variants as $variant) {
            $var_id = "";
            if (array_key_exists("amino_acid_change", $variant)) {
                $var_id = $variant["gene"] . "-" . $variant["amino_acid_change"];
            } else if (array_key_exists("dbSNP", $variant)) {
                $var_id = "rs" . $variant["dbSNP"];
            }
            if (strlen($var_id) > 0) {
                $returned_text .= "<TR><TD><A HREF=\"http://evidence.personalgenomes.org/"
                    . $var_id . "\">" . $var_id . "</A></TD><TD>"
                    . $variant["clinical"] . "</TD><TD>"
                    . $variant["evidence"] . "</TD><TD>"
                    . "<ul>" . ucfirst($variant["variant_impact"]) . "</ul><p>"
                    . $variant["inheritance_desc"] . ", " . $variant["zygosity"] . "</TD><TD>"
                    . $variant["allele_freq"] . "</TD><TD>"
                    . $variant["summary_short"] . "</TD></TR>\n";
            }
        }
        $returned_text .= "</TABLE>\n";

        usort($insuff_eval_variants, "sort_by_autoscore");
        $returned_text .= "<h1>Insufficiently reviewed variants:</h1>\n";
        $returned_text .= "<TABLE class=\"report_table\"><TR><TH>Variant</TH>"
                            . "<TH>Autoscore</TH>"
                            . "<TH>Allele freq</TH>"
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
                    $returned_text .= "<TR><TD><A HREF=\"http://evidence.personalgenomes.org/"
                            . $var_id . "\">" . $var_id . "</A></TD><TD>";
                } else {
                    $returned_text .= "<TR><TD>" . $var_id
                            . "<BR><A HREF=http://evidence.personalgenomes.org/"
                            . $var_id . ">Create GET-Evidence entry</A></TD><TD>";
                }
                $returned_text .= $variant["autoscore"] . "</TD><TD>";
                $returned_text .= $variant["allele_freq"] . "</TD><TD>";
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

function eval_zygosity($variant_dominance, $genotype, $ref_allele = null) {
    // 1 = expected to have effect (het dominant or hom recessive)
    // 0 = unclear ("other" inheritance or possible errors)
    // -1 = no effect expected (recessive carrier) or unknown
    $alleles = preg_split('/\//', $genotype);
    $zygosity = "Heterozygous";
    if (array_key_exists(1,$alleles) && ($alleles[0] == $alleles[1])) {
        $zygosity = "Homozygous";
    }
    if ($variant_dominance == "dominant") {
        if ( $ref_allele and 
            $ref_allele != $alleles[0] or $ref_allele != $alleles[1]) {
            return array (1, $zygosity, "Dominant"); // An effect is expected.
        } else {
            return array (0, $zygosity . "(matching ref??)", "Dominant"); // Error? maybe pathogenic ref? 
                                      // Need to have "pathogenic allele" to know.
        }
    } elseif ($variant_dominance == "other") {
        return array (0, $zygosity, "Complex/Other");
    } elseif ($variant_dominance == "recessive") {
        if ($zygosity == "Homozygous") {
            if ($ref_allele and $ref_allele == $alleles[0]) {
                return array (0, $zygosity . "(matching ref??)", "Recessive"); // Error or pathogenic ref? see above.
            } else {
                return array (1, $zygosity, "Recessive"); // Error or pathogenic ref? see above.
            }
        } else {
            return array (-1, "Carrier (" . $zygosity . ")", "Recessive"); // Recessive carrier
        }
    } else {
        return array (-1, $zygosity, "Unknown"); // "unknown" inheritance and other
    }
    return 0;
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
    if ($a['expect_effect'] > $b['expect_effect']) { return -1; }
    if ($a['expect_effect'] < $b['expect_effect']) { return 1; }
    return 0;
}

function sort_by_autoscore($a, $b) {
    if ($a['autoscore'] == $b['autoscore']) {
        if (!array_key_exists('gene',$a)) $a['gene'] = 0;
        if (!array_key_exists('gene',$b)) $b['gene'] = 0;
        if (!array_key_exists('amino_acid_change',$a)) $a['amino_acid_change'] = 0;
        if (!array_key_exists('amino_acid_change',$b)) $b['amino_acid_change'] = 0;
        return strnatcmp($a['gene']."-".$a['amino_acid_change'],
                        $b['gene']."-".$a['amino_acid_change']);
    } else {
        return ($a['autoscore'] > $b['autoscore']) ? -1: 1;
    }
}


?>
