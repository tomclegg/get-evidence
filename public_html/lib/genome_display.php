<?php

require_once ("lib/quality_eval.php");

function genome_get_results ($shasum, $oid) {
    $ret = array();

    $ret["progress"] = 0;
    $ret["status"] = "unknown";

    $still_processing = false;

    $resultfile = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum . "-out/get-evidence.json";
    $lockfile = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum . "-out/lock";
    if (file_exists ($lockfile)) {
	$logfile = $lockfile;
	$ret["progress"] = 1;
	$ret["status"] = "failed";
	if (!is_writable ($lockfile) || // if !writable, fuser won't work; assume lock is current
	    "ok" == shell_exec("fuser ''".escapeshellarg($lockfile)." >/dev/null && echo -n ok")) {
	    $still_processing = true;
	    $total_steps = 100;
	    foreach (file($lockfile) as $logline) {
		if (preg_match ('{^#status (\d*)/?(\d*) (.+)}', $logline, $regs)) {
		    if ($regs[2] > 0) $total_steps = $regs[2];
		    $ret["progress"] = $regs[1] / $total_steps;
		    $ret["status"] = $regs[3];
		}
	    }
	}
    } else {
	$logfile = preg_replace ('{lock$}', 'log', $lockfile);
	if (file_exists ($logfile) && file_exists ($resultfile)) {
	    $ret["progress"] = 1;
	    $ret["status"] = "finished";
	}
    }
    if (!file_exists ($logfile) || !is_readable ($logfile))
	$logfile = "/dev/null";

    $ret["log"] = file_get_contents ($logfile);
    $ret["logmtime"] = filemtime ($logfile);
    $ret["logfilename"] = $logfile;
    $ret["log"] .= "\n\nLog file ends: ".date("r",$ret["logmtime"]);

    return $ret;
}

function &genome_coverage_results($shasum, $oid) {
    $coverage_file = $GLOBALS["gBackendBaseDir"]."/upload/{$shasum}-out/missing_coding.json";
    $fh = fopen ($coverage_file, "r");
    if (!$fh) { $out = false; return $out; }
    $missing = 0;
    $length = 0;
    $out = array();
    while (($injson = fgets($fh)) !== false) {
	if (!preg_match('{clin_test}', $injson))
	    continue;
	$gene = json_decode($injson, true);
	$length += $gene['length'];
	if ($gene['missing'] == 0)
	    continue;
	$missing += $gene['missing'];
	if (!$gene['clin_test'])
	    continue;
	$out[] = $gene;
    }
    fclose ($fh);
    $out = array ('genes' => $out,
		  'missing' => $missing,
		  'length' => $length);
    return $out;
}

function genome_display($shasum, $oid) {
    $results = genome_get_results ($shasum, $oid);
    $db_query = theDb()->getAll ("SELECT nickname, global_human_id FROM private_genomes WHERE shasum=? AND oid=?",
                                            array($shasum, $oid));
    $ds = array ("Name" => false,
		 "Public profile" => false,
		 "This report" => "<a href=\"/genomes?$shasum\">{$_SERVER['HTTP_HOST']}/genomes?$shasum</a>");
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
    $sourcefile = $GLOBALS["gBackendBaseDir"]."/upload/{$shasum}/genotype.gff";
    if (! file_exists($sourcefile)) $sourcefile = $sourcefile . ".gz";
    $data_size = filesize ($sourcefile);
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

    if ($results["progress"] < 1) {
	$returned_text .= "<li>Processing status: &nbsp; <div style='margin:3px 0 -3px 0;display:inline-block;height:12px;' id='variant_report_progress' value='{$results['progress']}'></div> &nbsp; <div style='display:inline' id='variant_report_status'>{$results['status']}</div><input type='hidden' id='display_genome_id' value='{$shasum}' /></li>\n";
    }
    $logfile = $results["logfilename"];
    $log = $results["log"];
    $returned_text .= "<li><A id=\"showdebuginfo\" href=\"#\" onclick=\"jQuery('#debuginfo').toggleClass('ui-helper-hidden');jQuery('#showdebuginfo').html('Show/hide debugging info');return false;\">Show debugging info</A></li>\n";
    $returned_text .= "</ul>\n";
    $returned_text .= "<DIV id='debuginfo' class='ui-helper-hidden'><BLOCKQUOTE><PRE id='debuginfotext'>Log file: ".$logfile."\n\n".htmlspecialchars($log,ENT_QUOTES,"UTF-8")."\n\n</PRE></BLOCKQUOTE></DIV>\n";

    $results_file = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum . "-out/get-evidence.json";
    if (file_exists($results_file)) {
	$variants = array(array(),array());
        $lines = file($results_file);
        foreach ($lines as $line) {
            $variant_data = json_decode($line, true);
	    if (!is_array($variant_data)) continue; // sometimes we can't read python's json??

            $variant_data["name"] = "";
            if (array_key_exists("amino_acid_change", $variant_data)) {
                $variant_data["name"] = $variant_data["gene"] . "-" . $variant_data["amino_acid_change"];
            } else if (array_key_exists("dbSNP", $variant_data)) {
                $variant_data["name"] = "rs" . $variant_data["dbSNP"];
            } else
		continue;

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
	    if ($variant_data["suff_eval"])
		$variants[0][] = $variant_data;
	    else
		$variants[1][] = $variant_data;
        }
	$coverage =& genome_coverage_results ($shasum, $oid);

	$returned_text .= "<div id='variant_table_tabs'><ul>\n"
	    . "<li><A href='#variant_table_tab_0'>Evaluated variants</A></li>\n"
	    . "<li><A href='#variant_table_tab_1'>Insufficiently evaluated variants</A></li>\n";
	if ($coverage)
	    $returned_text .=
		"<li><A href='#variant_table_tab_2'>Coverage</A></li>\n";
	$returned_text .=
	    "</ul>\n"
	    . "<div id='variant_table_tab_0'>";

	$returned_text .= "<div style='float:right; margin-bottom: 3px' id='variant_filter_radio'>
<input type='radio' name='variant_filter_radio' id='variant_filter_radio0' checked /><label for='variant_filter_radio0'>Show all</label>
<input type='radio' name='variant_filter_radio' id='variant_filter_radio1' /><label for='variant_filter_radio1'>Show rare (<i>f</i><10%) pathogenic variants</label>
</div><br clear=all />";

        usort($variants[0], "sort_variants");
        $returned_text .= "<TABLE class='report_table variant_table datatables_please' datatables_name='variant_table'><THEAD><TR>"
	    . "<TH class='Invisible ui-helper-hidden'>Row number</TH>"
	    . "<TH>Variant</TH>"
	    . "<TH class='SortImportance SortDescFirst'>Clinical<BR />Importance</TH>"
	    . "<TH class='SortEvidence Invisible'>Evidence</TH>"
	    . "<TH class='SortDescFirst'>Impact</TH>"
	    . "<TH class='RenderFreq'>Allele<BR />freq</TH>"
	    . "<TH class='Unsortable'>Summary</TH>"
	    . "<TH class='Invisible ui-helper-hidden'>Sufficient</TH>"
	    . "</TR></THEAD><TBODY>\n";
	$rownumber = 0;
        foreach ($variants[0] as $variant) {
	    ++$rownumber;
	    $returned_text .= "<TR><TD class='ui-helper-hidden'>$rownumber</TD>"
		. "<TD><A HREF=\""
		. $variant["name"] . "\">" . $variant["name"] . "</A></TD><TD>"
		. $variant["clinical"] . "</TD><TD>"
		. $variant["evidence"] . "</TD><TD>"
		. $variant["evidence"]
		. " " . $variant["variant_impact"] . "<br /><br />"
		. $variant["inheritance_desc"] . ", " . $variant["zygosity"] . "</TD><TD>"
		. $variant["allele_freq"] . "</TD><TD>"
		. $variant["summary_short"] . "</TD><TD class='ui-helper-hidden'>"
		. $variant["suff_eval"] . "</TD></TR>\n";
        }
        $returned_text .= "</TBODY></TABLE>\n";

	$returned_text .= "</div>\n<div id='variant_table_tab_1'>\n";

        usort($variants[1], "sort_variants");
        $returned_text .= "<TABLE class='report_table variant_table datatables_please' datatables_name='variant_table_insuff'><THEAD><TR>"
	    . "<TH class='Invisible ui-helper-hidden'>Row number</TH>"
	    . "<TH>Variant</TH>"
	    . "<TH class='SortNumeric SortDescFirst'>Autoscore</TH>"
	    . "<TH class='RenderFreq'>Allele<BR />freq</TH>"
	    . "<TH class='Unsortable'>Autoscore Reasons</TH>"
	    . "<TH class='Invisible ui-helper-hidden'>Sufficient</TH>"
	    . "</TR></THEAD><TBODY>\n";
	$rownumber = 0;
        foreach ($variants[1] as $variant) {
	    ++$rownumber;
	    $returned_text .= "<TR><TD class='ui-helper-hidden'>$rownumber</TD>"
		. "<TD><A HREF=\""
		. $variant["name"] . "\">" . $variant["name"] . "</A></TD><TD>"
		. $variant["autoscore"]. "</TD><TD>"
		. $variant["allele_freq"] . "</TD><TD>"
        . autoscore_evidence($variant) . "</TD><TD class='ui-helper-hidden'>"
		. $variant["suff_eval"] . "</TD></TR>\n";
        }
        $returned_text .= "</TBODY></TABLE>\n";

	if ($coverage) {
	    $returned_text .= "</div>\n<div id='variant_table_tab_2'>\n";

	    $returned_text .= '<P>Exome coverage: '
		. ($coverage['length'] - $coverage['missing'])
		. ' / '
		. $coverage['length']
		. ' = '
		. sprintf ('%.2f', 100*(1-($coverage['missing'] / $coverage['length'])))
		. '%</P>';
	    $returned_text .= "<TABLE class='report_table variant_table datatables_please' datatables_name='variant_table_coverage' style='width: 100%'><THEAD><TR>"
		. "<TH class='Invisible ui-helper-hidden'>Row number</TH>"
		. "<TH>Gene</TH>"
		. "<TH class='SortChromosome'>Chromosome</TH>"
		. "<TH class='RenderFreq'>Coverage</TH>"
		. "<TH class='SortNumeric'>Missing</TH>"
		. "<TH class='SortNumeric'>Length</TH>"
		. "</TR></THEAD><TBODY>\n";
	    $rownumber = 0;
	    foreach ($coverage['genes'] as $gene) {
		++$rownumber;
		$returned_text .= '<TR><TD class="ui-helper-hidden">'
		    . $rownumber . '</TD><TD><A HREF="report?type=search&q='
		    . $gene['gene'] . '">' . $gene['gene'] . '</A></TD><TD>'
		    . str_replace('chr','',$gene['chr']) . '</TD><TD>'
		    . ($gene['length']>0 ? (1-($gene['missing']/$gene['length'])) : '-')
		    . '</TD><TD>'
		    . $gene['missing'] . '</TD><TD>'
		    . $gene['length'] . '</TD></TR>' . "\n";
	    }
	    $returned_text .= '</TBODY></TABLE>' . "\n";
	}

	$returned_text .= "</div></div>\n";
    }
    return($returned_text);
}

function eval_zygosity($variant_dominance, $genotype, $ref_allele = null) {
    // 1 = expected to have effect (het dominant or hom recessive)
    // 0 = unclear ("other" inheritance or possible errors)
    // -1 = no effect expected (recessive carrier) or unknown
    $alleles = preg_split('/\//', $genotype);
    $zygosity = "Heterozygous";
    if (!array_key_exists(1,$alleles) || ($alleles[0] == $alleles[1])) {
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
    if (array_key_exists("frameshift", $variant) and $variant["frameshift"]) {
        $items[] = "Frameshift";
    }
    if (array_key_exists("indel", $variant) and $variant["indel"]) {
        $items[] = "Frame-preserving indel";
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
