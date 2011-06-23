<?php

// Written by Madeleine Price Ball
// Part of the GET-Evidence software and shared under its license.

include "lib/setup.php";

$gOut["title"] = "GET-Evidence: Calculators";

$page_content = "";  // all html output stored here

$page_content .= "<h1>PGP calculators<h1>";


$page_content .= "<script type=\"text/javascript\" src=\"/js/calculators.js\">"
    . "</script><noscript>Your browser doesn't support or has disabled "
    . "JavaScript</noscript>\n";

$page_content .= "<h2>Fisher's Exact Test (two-tailed)</h2>";
$page_content .=  "<TABLE><TR><TD>"
    . "case+: <input type='text' id='fisher_a' size=4 ></TD>\n"
    . "<TD>case-: <input type='text' id='fisher_b' size=4 ></TD></TR>\n"
    . "<TR><TD>control+: <input type='text' id='fisher_c' size=4 ></TD>\n"
    . "<TD>control-: <input type='text' id='fisher_d' size=4 >"
    . "</TD></TR></TABLE>\n"
    . "<input type='submit' value='Calculate p-value' "
    . "onClick='return_fisher_exact()'>\n"
    . "<p id=\"factorial_result\">Click button to get result... </p>\n";

$page_content .= "<h2>Case/control and penetrance analysis</h2>";
$page_content .=
    "<p>This tool assists estimation of increased disease/phenotype " .
    "incidence for variants believed to cause increased susceptibility. " .
    "For example, a study might implicate a variant as causing increased " .
    "suscpetibility to Crohn's disease in a dominant manner, finding it " .
    "in 19 out of 443 cases and only 3 out of 320 controls. If you use " .
    "these numbers and enter .02% for prevalence, you'll find the variant " .
    "estimated to cause .07% attributable risk (4.5-fold risk).</p>" .
    "<p>Be cautious about \"rescuing\" published hypotheses of high " .
    "penetrance variants (which originally lacked strong statistical " .
    "significance) with a new \"low penetrance\" evaluation. " .
    "New knowledge of incidence in controls should disprove the published " .
    "hypothesis, not significantly change hypothesized penetrance. " .
    "For example: if a paper reports a variant was seen in 2 out of 90 " .
    "cases of congenital heart defect and wasn't seen in 200 controls, it " .
    "is proposing a high penetrance effect for the variant. If you later " .
    "find the variant in 2 out of 100 public genomes, don't re-evaluate the " .
    "variant as having low penetrance. This is instead evidence that the " .
    "original hypothesis was wrong.</p>\n" .
    "<h3>Disease/phenotype prevalence (percentage):</h3>" .
    "<input type='text' id='dis_freq' size=4>%\n";
$page_content .= "<h3>Fill in phenotype information: </h3>\n"
    . "<select id='gentyp_data' onChange='update_gentyp_entry()'>\n"
    . "<option value='raw'>Individual genotypes</option>\n"
    . "<option value='dom'>Pooled genotypes: Dominant hypothesis</option>\n"
    . "<option value='rec'>Pooled genotypes: Recessive hypothesis</option>\n"
    . "<option value='chr'>Counting chromosomes (Other/unknown hypothesis)"
    . "</option></select >\n"; 
$page_content .= "<div id='gentyp_entry_raw' style='display: inline'>"
    . "<h3>Individual genotypes:</h3>"
    . "<form name='raw_data'>"
    . "<TABLE><TR><TD><b>Case Var/Var</b><br>(Hom. carrier): "
    . "<input type='text' id='case_vv' size=4 ></TD>\n"
    . "<TD><b>Case Var/Normal</b><br>(Het. carrier): "
    . "<input type='text' id='case_vn' size=4 ></TD>\n"
    . "<TD><b>Case Normal/Normal</b><br>(Hom. non-carrier): "
    . "<input type='text' id='case_nn' size=4 ></TD></TR>\n"
    . "<TR><TD><b>Control Var/Var</b><br>(Hom. carrier): "
    . "<input type='text' id='cont_vv' size=4 ></TD>\n"
    . "<TD><b>Control Var/Normal</b><br>(Het. carrier): "
    . "<input type='text' id='cont_vn' size=4 ></TD>\n"
    . "<TD><b>Control Normal/Normal</b><br>(Hom. non-carrier): "
    . "<input type='text' id='cont_nn' size=4 ></TD></TR>\n"
    . "</TABLE>\n"
    . "<p><h4>Hypothesis to evaluate under</h4></p>"
    . "<input type='radio' name='gentyp_hyp' value='dom' checked>"
    . "...a dominant hypothesis (VV & Vn versus nn)<br>\n"
    . "<input type='radio' name='gentyp_hyp' value='rec'>"
    . "...a recessive hypothesis (vv versus vN & NN)<br>\n"
    . "<input type='radio' name='gentyp_hyp' value='chr'>"
    . "...other/unknown hypothesis, count chromosomes (V versus N)<br>"
    . "</form>\n</div>\n";
$page_content .= "<div id='gentyp_entry_dom' style='display: none'>"
    . "<h3>Dominant hypothesis numbers:</h3>"
    . "<TABLE><TR><TD><b>Case+ (Var/Var & Var/normal)</b><br>"
    . "(Hom. & Het. carrier): "
    . "<input type='text' id='case_dom_p' size=4 ></TD>\n"
    . "<TD><b>Case- (normal/normal)</b><br>(Hom. non-carrier): "
    . "<input type='text' id='case_dom_m' size=4 ></TD></TR>\n"
    . "<TR><TD><b>Control+ (Var/Var & Var/normal)</b><br>(Hom. carrier): "
    . "<input type='text' id='cont_dom_p' size=4 ></TD>\n"
    . "<TD><b>Control- (normal/normal)</b><br>(Hom. non-carrier): "
    . "<input type='text' id='cont_dom_m' size=4 ></TD></TR></TABLE>\n"
    . "</div>\n";
$page_content .= "<div id='gentyp_entry_rec' style='display: none'>"
    . "<h3>Recessive hypothesis numbers:</h3>"
    . "<TABLE><TR><TD><b>Case+ (var/var)</b><br>(Homozygous variant): "
    . "<input type='text' id='case_rec_p' size=4 ></TD>\n"
    . "<TD><b>Case- (var/Normal & Normal/Normal)</b><br>"
    . "(Het. carrier & non-carrier): "
    . "<input type='text' id='case_rec_m' size=4 ></TD></TR>\n"
    . "<TR><TD><b>Control+ (var/var)</b><br>(Homozygous variant): "
    . "<input type='text' id='cont_rec_p' size=4 ></TD>\n"
    . "<TD><b>Control- (var/Normal & Normal/Normal)</b><br>"
    . "(Het carrier & non-carrier): "
    . "<input type='text' id='cont_rec_m' size=4 ></TD></TR></TABLE>\n"
    . "</div>\n";
$page_content .= "<div id='gentyp_entry_chr' style='display: none'>"
    . "<h3>Other/unknown hypothesis (counting chromosomes):</h3>"
    . "<TABLE><TR><TD><b>Case+</b><br>(variant alleles): "
    . "<input type='text' id='case_chr_p' size=4 ></TD>\n"
    . "<TD><b>Case-</b><br>(normal/non-variant alleles): "
    . "<input type='text' id='case_chr_m' size=4 ></TD></TR>\n"
    . "<TR><TD><b>Control+</b><br>(variant alleles): "
    . "<input type='text' id='cont_chr_p' size=4 ></TD>\n"
    . "<TD><b>Control-</b><br>(normal/non-variant alleles): "
    . "<input type='text' id='cont_chr_m' size=4 ></TD></TR></TABLE>\n"
    . "</div>\n";
$page_content .= "<input type='submit' "
    . "value='Calculate p-value and attributable risk' "
    . "onClick='eval_gentyp_data()'>\n"
    . "<p id=\"gentyp_eval_result\">Click button to get results... </p>\n";

$page_content .= "<h2>Compare controls against a " .
    "\"undiscovered pathogenic\" hypothesis</h2>\n" .
    "<p>This tool is to assist with evaluation of significance for variants " .
    "seen in controls and thus hypothesized to be nonpathogenic/benign. " .
    "To do this, we compare them against a hypothetical as-yet-undiscovered " .
    "pathogenic variant. First we estimate the maximum allele frequency for " .
    "that hypothetical pathogenic variant, then we find how significantly " .
    "our observations of this variant in controls deviates from the " .
    "hypothetical pathogenic variant's maximum allele frequency.</p>";
$page_content .=  "<h3>Hypothetical as-yet-undiscovered variant's data:</h3>" .
    "\n<TABLE><TR><TD>Disease prevalence:</TD><TD>" .
    "<input type='text' id='hyp_dis_prev' size=5>%</TD></TR>\n<TR><TD>" .
    "Fraction of the disease caused by this gene:</TD><TD>" .
    "<input type='text' id='hyp_gene_cause' size=5>%</TD></TR>\n" .
    "<TR><TD>Within disease caused by this gene, fraction of disease<br/>" .
    "attributable to this variant:</TD><TD>" .
    "<input type='text' id='hyp_var_cause' size=5>%</TD></TR>\n" .
    "<TR><TD>Penetrance of hypothetical variant:</TD><TD>" . 
    "<input type='text' id='hyp_penet' size=5>%</TD></TR></TABLE>\n" .
    "<p><h4>Hypothetical variant is:</h4>\n" . 
    "<select id='hyp_var_hyp''>\n" .
    "<option value='dom'>dominant</option>\n" .
    "<option value='rec'>recessive</option>\n" .
    "</select >\n</p>" .
    "<TABLE><TR><TD>Number of <b>variant</b> alleles<BR/>" .
    "in the randomly chosen<BR/>control population" .
    "</TD><TD><input type='text' id='hyp_cont_var' size=5></TD></TR>\n" .
    "<TR><TD>Number of <b>reference</b> alleles<BR/>" .
    "in the randomly chosen<BR/>control population</TD><TD>" .
    "<input type='text' id='hyp_cont_ref' size=5></TD></TR></TABLE>\n" .
    "<input type='submit' value='Calculate p-value' " .
    "onClick='eval_undisc_path()'>\n" .
    "<p id=\"undisc_path_result\">Click button to get result... </p>\n";

$gOut["content"] = $page_content;

go();


?>
