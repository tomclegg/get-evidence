<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Genomes";

$page_content = "";  // all html output stored here
$public_data_user = "http://www.google.com/profiles/PGP.uploader";

$display_genome_ID = $_POST['display_genome_id'];
$user_request_oid = $_POST['user_request_oid'];

$user = getCurrentUser();

if (strlen($display_genome_ID) > 0) {
    $db_query = theDb()->getAll ("SELECT oid FROM private_genomes WHERE shasum=?", 
                                            array($display_genome_ID));
    # check you should have permission
    $permission = false;
    $request_ID = $public_data_user;     # reset if to this user if they have permission
    foreach ($db_query as $result) {
        if ($result['oid'] == $user['oid']) {
            $permission = true;
            $request_ID = $user['oid'];
        } elseif ($result['oid'] == $public_data_user) {
            $permission = true;
        }
    }
    if ($permission) {
        $page_content .= genome_display($display_genome_ID, $request_ID);
    } else {
        $page_content .= "Sorry, for some reason you've requested a genome you don't have "
                    . "access to. Perhaps you've been logged off?<br>\n";
    }
} else {
    $page_content .= "<h2>Public genomes</h2>";
    $page_content .= "Public genomes will be listed here.";
    $page_content .= list_uploaded_genomes($public_data_user);
    $page_content .= "<hr>";
    if ($user) {
        $page_content .= "<h2>Uploaded genomes</h2>\n";
        $page_content .= list_uploaded_genomes($user["oid"]);
        $page_content .= "<hr>";
        $page_content .= "<h2>Upload a genome for analysis</h2>\n";
        $page_content .= upload_warning();
        $page_content .= genome_entry_form();
    } else {
        $page_content .= "<h2>Genome analysis</h2>"
                    . "<h3>You need to be logged in using your OpenID to use "
                    . "GET-Evidence for private genome analysis. Because we "
                    . "cannot guarantee the security of our system we "
                    . "highly recommend creating a private installation of "
                    . "GET-Evidence for genome analysis of private genomes."
                    . "</h3>";
    }
}

$gOut["content"] = $page_content;

go();

// Functions

function list_uploaded_genomes($user_oid) {
    global $public_data_user, $user;
    $returned_text = "";
    $db_query = theDb()->getAll ("SELECT * FROM private_genomes WHERE oid=?", array("$user_oid"));
    if ($db_query) {
        $returned_text .= "<TABLE class=\"report_table\">\n";
        $returned_text .= "<TR><TH>Nickname</TH><TH>Action</TH></TR>\n";
        foreach ($db_query as $result) {
            $returned_text .= "<TR><TD>" . $result['nickname'] . "</TD><TD>";
            if ($user_oid == $public_data_user and $user['oid'] != $public_data_user) {
                $returned_text .= genome_display_actions($result) . "</TD></TR>\n";
            } else {
                $returned_text .= uploaded_genome_actions($result) . "</TD></TR>\n";
            }
        }
        $returned_text .= "</TABLE>\n";
    } else {
        $returned_text .= "You have no uploaded genomes.\n";
    }
    return($returned_text);
}

function genome_display_actions($result) {
    $returned_text = "<form action=\"/genomes.php\" method=\"post\">\n";
    $returned_text .= "<input type=\"hidden\" name=\"display_genome_id\" value=\"" 
                    . $result['shasum'] . "\">\n";
    $returned_text .= "<input type=\"submit\" value=\"Get report\" "
                        . "class=\"button\" \/></form>\n";
    return($returned_text);
}

function uploaded_genome_actions($result) {
    global $public_data_user, $user;
    # Get report button
    $returned_text = "<form action=\"/genomes.php\" method=\"post\">\n";
    $returned_text .= "<input type=\"hidden\" name=\"display_genome_id\" value=\"" 
                    . $result['shasum'] . "\">\n";
    $returned_text .= "<input type=\"submit\" value=\"Get report\" "
                        . "class=\"button\" \/></form><br>\n";
    # Reprocess data button
    $returned_text .= "<form action=\"/genome_upload.php\" method=\"post\">\n";
    $returned_text .= "<input type=\"hidden\" name=\"reprocess_genome_id\" value=\""
                    . $result['shasum'] . "\">\n";
    $returned_text .= "<input type=\"submit\" value=\"Reprocess data\" "
                        . "class=\"button\" \/></form><br>\n";
    # Delete file button
    $returned_text .= "<form action=\"/genome_upload.php\" method=\"post\">\n";
    $returned_text .= "<input type=\"hidden\" name=\"delete_genome_id\" value=\""
                    . $result['shasum'] . "\">\n";
    $returned_text .= "<input type=\"hidden\" name=\"delete_genome_nickname\" value=\""
                    . $result['nickname'] . "\">\n";
    $returned_text .= "<input type=\"hidden\" name=\"user_oid\" value=\"" 
                    . $user['oid'] . "\">\n";
    $returned_text .= "<input type=\"submit\" value=\"Delete data\" "
                        . "class=\"button\" \/></form><br>\n";
    return($returned_text);
}

function genome_display($shasum, $oid) {
    $db_query = theDb()->getAll ("SELECT nickname FROM private_genomes WHERE shasum=? AND oid=?",
                                            array($shasum, $oid));
    $returned_text = "<h1>Genome report for " . $db_query[0]['nickname'] . "</h1>\n";

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
        $scores = preg_split('//', $variant_quality, -1);
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
        $scores = preg_split('//', $variant_quality, -1);
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


function upload_warning() {
    $returned_text = "<h2><font color=red>WARNING</font></h2>\n";
    $returned_text .= "<h2><font color=red>You may upload genomes for analysis if logged in, "
                    . "but we cannot guarantee the security of our database.\n"
                    . "Please install a private version of GET-Evidence to "
                    . "ensure privacy and security of your genome data."
                    . "</font></h2></p>\n";
    return($returned_text);
}

function genome_entry_form() {
    $returned_text = "<div>\n<form enctype=\"multipart/form-data\" "
                    . "action=\"/genome_upload.php\" method=\"post\">\n";
    $returned_text .= "<label class=\"label\">Filename<br>\n";
    $returned_text .= "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" "
                        . "value=\"300000000\">\n";
    $returned_text .= "<input type=\"file\" class=\"file\" name=\"genotype\" "
                        . "id=\"genotype\"></label><br>\n";
    $returned_text .= "<label class=\"label\">Genome name<br>\n";
    $returned_text .= "<input type=\"text\" size=\"64\" name=\"nickname\" "
                        . "id=\"nickname\"></label>\n";
    $returned_text .= "<input type=\"submit\" value=\"Upload\" class=\"button\" />\n";
    $returned_text .= "</form>\n</div>\n";

    return($returned_text);
}

?>
