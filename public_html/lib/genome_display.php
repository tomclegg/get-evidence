<?php

require_once ("lib/quality_eval.php");

function genome_display($shasum, $oid) {
    $db_query = theDb()->getAll ("SELECT nickname, global_human_id FROM private_genomes WHERE shasum=? AND oid=?",
                                            array($shasum, $oid));
    $ds = array ("Name" => false,
		 "Public profile" => false,
		 "This report" => "<a href=\"/genomes?$shasum\">evidence.personalgenomes.org/genomes?$shasum</a>");
    if ($db_query[0]['nickname']) {
	$realname = $db_query[0]['nickname'];
	if (preg_match ('{^PGP\d+ \((.+)\)}', $realname, $regs))
	    $realname = $regs[1];
	$ds["Name"] = htmlspecialchars ($realname, ENT_QUOTES, "UTF-8");
    }
    $global_human_id = $db_query[0]['global_human_id'];
    if (preg_match ('{^hu[A-F0-9]+$}', $global_human_id)) {
	$hu = false;
	// $hu = json_decode(file_get_contents("http://my.personalgenomes.org/api/get/$global_human_id"), true);
	if ($hu && isset($hu["realname"]))
	    $ds["Name"] = $hu["realname"];
	$url = "https://my.personalgenomes.org/profile/$global_human_id";
	$ds["Public profile"] = "<a href=\"".htmlspecialchars($url)."\">".preg_replace('{^https?://}','',$url)."</a>";
    }
    $data_size = filesize ($GLOBALS["gBackendBaseDir"]."/upload/{$shasum}/genotype.gff");
    if ($data_size) {
	if ($data_size > 1000000) $data_size = (floor($data_size / 1000000) . " MB");
	else if ($data_size > 1000) $data_size = (floor($data_size / 1000) . " KB");
	else $data_size = $data_size . " B";
	$ds["Source data"] = "<a href=\"/genome_download.php?download_genome_id=$shasum&amp;download_nickname=".urlencode($db_query[0]['nickname'])."\">download GFF</a> ($data_size)";
    }
    $qrealname = htmlspecialchars($ds["Name"], ENT_QUOTES, "UTF-8");
    $GLOBALS["gOut"]["title"] = $qrealname." - GET-Evidence variant report";
    $returned_text = "<h1>Variant report for ".htmlspecialchars($db_query[0]['nickname'],ENT_QUOTES,"UTF-8")."</h1><ul>";
    foreach ($ds as $k => $v)
	if ($v)
	    $returned_text .= "<li>$k: $v</li>\n";
    $returned_text .= "</ul>\n";

    $results_file = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum . "-out/get-evidence.json";
    if (file_exists($results_file)) {
	$variants = array();
        $lines = file($results_file);
        foreach ($lines as $line) {
            $variant_data = json_decode($line, true);
	    if (!$variant_data) continue; // sometimes we can't read python's json??
            # Get allele frequency
            if (array_key_exists("num",$variant_data) &&
		array_key_exists("denom",$variant_data) &&
		$variant_data["denom"] > 0) {
		$allele_freq = sprintf("%.3f", $variant_data["num"] / $variant_data["denom"]);
                $variant_data["allele_freq"] = $allele_freq;
            } else {
                $variant_data["allele_freq"] = "";
            }
            # Get zygosity
            $eval_zyg_out = eval_zygosity( $variant_data["variant_dominance"],
                                            $variant_data["genotype"],
                                            $variant_data["ref_allele"]);
            $variant_data["suff_eval"] = quality_eval_suff($variant_data["variant_quality"], $variant_data["variant_impact"]);
            if ($variant_data["suff_eval"]) {
                $variant_data["clinical"] = quality_eval_clinical($variant_data["variant_quality"]);
                $variant_data["evidence"] = quality_eval_evidence($variant_data["variant_quality"]);
            } else {
                $variant_data["clinical"] = "";
                $variant_data["evidence"] = "";
            }
	    $variant_data["expect_effect"] = $eval_zyg_out[0];
	    $variant_data["zygosity"] = $eval_zyg_out[1];
	    $variant_data["inheritance_desc"] = $eval_zyg_out[2];
	    $variants[] = $variant_data;
        }

	$returned_text .= "<p align='right'><input type='checkbox' id='variant_table_showall' name='variant_table_showall' value='1' class='ui-state-default variant_table_updater' /> show all variants<br />(turn off relevance filters)</p>\n";

        usort($variants, "sort_variants");
        $returned_text .= "<TABLE class='report_table variant_table datatables_please' datatables_name='variant_table'><THEAD><TR>"
	    . "<TH class='Invisible ui-helper-hidden'>Row number</TH>"
	    . "<TH>Variant</TH>"
	    . "<TH class='SortImportance'>Clinical<BR />Importance</TH>"
	    . "<TH class='SortEvidence'>Evidence</TH>"
	    . "<TH>Impact</TH>"
	    . "<TH class='RenderFreq'>Allele<BR />freq</TH>"
	    . "<TH>Summary</TH>"
	    . "<TH class='Invisible ui-helper-hidden'>Sufficient</TH>"
	    . "</TR></THEAD><TBODY>\n";
	$rownumber = 0;
        foreach ($variants as $variant) {
	    ++$rownumber;
            $var_id = "";
            if (array_key_exists("amino_acid_change", $variant)) {
                $var_id = $variant["gene"] . "-" . $variant["amino_acid_change"];
            } else if (array_key_exists("dbSNP", $variant)) {
                $var_id = "rs" . $variant["dbSNP"];
            }
            if (strlen($var_id) > 0) {
                $returned_text .= "<TR><TD class='ui-helper-hidden'>$rownumber</TD>"
		    . "<TD><A HREF=\"http://evidence.personalgenomes.org/"
                    . $var_id . "\">" . $var_id . "</A></TD><TD>"
                    . $variant["clinical"] . "</TD><TD>"
                    . $variant["evidence"] . "</TD><TD>"
                    . "<ul>" . ucfirst($variant["variant_impact"]) . "</ul><p>"
                    . $variant["inheritance_desc"] . ", " . $variant["zygosity"] . "</TD><TD>"
                    . $variant["allele_freq"] . "</TD><TD>"
                    . $variant["summary_short"] . "</TD><TD class='ui-helper-hidden'>"
                    . $variant["suff_eval"] . "</TD></TR>\n";
            }
        }
        $returned_text .= "</TBODY></TABLE>\n";
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

function sort_variants($a, $b) {
    if ($a['suff_eval'] && $b['suff_eval'])
	return sort_reviewed ($a, $b);
    if ($a['suff_eval'])
	return -1;
    if ($b['suff_eval'])
	return 1;
    return sort_by_autoscore ($a, $b);
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
