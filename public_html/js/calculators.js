function return_fisher_exact() {
    var input_a = document.getElementById('fisher_a').value;
    var a = parseInt(parseFloat(input_a) + 0.5);
    var input_b = document.getElementById('fisher_b').value;
    var b = parseInt(parseFloat(input_b) + 0.5);
    var input_c = document.getElementById('fisher_c').value;
    var c = parseInt(parseFloat(input_c) + 0.5);
    var input_d = document.getElementById('fisher_d').value;
    var d = parseInt(parseFloat(input_d) + 0.5);
    var n = a + b + c + d;
    if ( isNaN(a) || isNaN(b) || isNaN(c) || isNaN(d) ) {
        document.getElementById('factorial_result').innerHTML = nonneg_error_message();
    } else {
        if ( a < 0 || b < 0 || c < 0 || d < 0 ) {
            document.getElementById('factorial_result').innerHTML = nonneg_error_message();
        } else {
            var result = fisher_exact_two_tailed(a, b, c, d);
            if (result > 1.0) {
                result = 1;
            }
            document.getElementById('factorial_result').innerHTML = "p = " + result;
        }
    }
}

function nonneg_error_message() {
    return "ERROR: Please enter non-negative numbers in all available fields. They will be rounded to the nearest integer value.";
}

function update_genotype_entry_form() {
    var form_type = document.getElementById('genotype_data').value;
    if (form_type == "raw") {
        document.getElementById('genotype_entry_form_raw').style.display = 'inline';
        document.getElementById('genotype_entry_form_dom').style.display = 'none';
        document.getElementById('genotype_entry_form_rec').style.display = 'none';
        document.getElementById('genotype_entry_form_chr').style.display = 'none';
    } else if (form_type == "dom") {
        document.getElementById('genotype_entry_form_raw').style.display = 'none';
        document.getElementById('genotype_entry_form_dom').style.display = 'inline';
        document.getElementById('genotype_entry_form_rec').style.display = 'none';
        document.getElementById('genotype_entry_form_chr').style.display = 'none';
    } else if (form_type == "rec") {
        document.getElementById('genotype_entry_form_raw').style.display = 'none';
        document.getElementById('genotype_entry_form_dom').style.display = 'none';
        document.getElementById('genotype_entry_form_rec').style.display = 'inline';
        document.getElementById('genotype_entry_form_chr').style.display = 'none';
    } else if (form_type == "chr") {
        document.getElementById('genotype_entry_form_raw').style.display = 'none';
        document.getElementById('genotype_entry_form_dom').style.display = 'none';
        document.getElementById('genotype_entry_form_rec').style.display = 'none';
        document.getElementById('genotype_entry_form_chr').style.display = 'inline';
    } else {
    }
}

function eval_genotype_data () {
    var form_type = document.getElementById('genotype_data').value;
    var case_p = 0; var case_m = 0; var cont_p = 0; var cont_m = 0;
    var hyp_type = "";
    if (form_type == "raw") {
        for (var i = 0; i < document.raw_data.genotype_hyp.length; i++) {
            if (document.raw_data.genotype_hyp[i].checked == true) {
                hyp_type = document.raw_data.genotype_hyp[i].value;
            }
        }
        var case_vv = parseInt ( Math.round (parseFloat(document.getElementById('case_vv').value) ) );
        var case_vn = parseInt ( Math.round (parseFloat(document.getElementById('case_vn').value) ) );
        var case_nn = parseInt ( Math.round (parseFloat(document.getElementById('case_nn').value) ) );
        var cont_vv = parseInt ( Math.round (parseFloat(document.getElementById('cont_vv').value) ) );
        var cont_vn = parseInt ( Math.round (parseFloat(document.getElementById('cont_vn').value) ) );
        var cont_nn = parseInt ( Math.round (parseFloat(document.getElementById('cont_nn').value) ) );
        if ( isNaN(case_vv) || isNaN(case_vn) || isNaN(case_nn) || isNaN(cont_vv) || isNaN(cont_vn) || isNaN(cont_nn)
                || case_vv < 0 || case_vn < 0 || case_nn < 0 || cont_vv < 0 || cont_vn < 0 || cont_nn < 0 ) {
            document.getElementById('genotype_eval_result').innerHTML = nonneg_error_message();
        }
        if (hyp_type == "dom") {
            case_p = case_vv + case_vn; case_m = case_nn; cont_p = cont_vv + cont_vn; cont_m = cont_nn;
        } else if (hyp_type == "rec") {
            case_p = case_vv; case_m = case_vn + case_nn; cont_p = cont_vv; cont_m = cont_vn + cont_nn;
        } else if (hyp_type == "chr") {
            case_p = case_vv * 2 + case_vn; case_m = case_vn + case_nn * 2;
            cont_p = cont_vv * 2 + cont_vn; cont_m = cont_vn + cont_nn * 2;
        } else {
            document.getElementById('genotype_eval_result').innerHTML = "Please pick a hypothesis to evaluate the data.";
        }
    } else if (form_type == "dom") {
        case_p = parseInt ( Math.round (parseFloat(document.getElementById('case_dom_p').value) ) );
        case_m = parseInt ( Math.round (parseFloat(document.getElementById('case_dom_m').value) ) );
        cont_p = parseInt ( Math.round (parseFloat(document.getElementById('cont_dom_p').value) ) );
        cont_m = parseInt ( Math.round (parseFloat(document.getElementById('cont_dom_m').value) ) );
    } else if (form_type == "rec") {
        case_p = parseInt ( Math.round (parseFloat(document.getElementById('case_rec_p').value) ) );
        case_m = parseInt ( Math.round (parseFloat(document.getElementById('case_rec_m').value) ) );
        cont_p = parseInt ( Math.round (parseFloat(document.getElementById('cont_rec_p').value) ) );
        cont_m = parseInt ( Math.round (parseFloat(document.getElementById('cont_rec_m').value) ) );
    } else if (form_type == "chr") {
        case_p = parseInt ( Math.round (parseFloat(document.getElementById('case_chr_p').value) ) );
        case_m = parseInt ( Math.round (parseFloat(document.getElementById('case_chr_m').value) ) );
        cont_p = parseInt ( Math.round (parseFloat(document.getElementById('cont_chr_p').value) ) );
        cont_m = parseInt ( Math.round (parseFloat(document.getElementById('cont_chr_m').value) ) );
    } else {
        document.getElementById('genotype_eval_result').innerHTML = "Error: form type is unknown (??)";
    }
    if (case_p + case_m > 0 && cont_p + cont_m > 0) {
        var p_value = fisher_exact_two_tailed(case_p, case_m, cont_p, cont_m);
        if (p_value > 1.0) {
            p_value = 1;
        }
        var att_risk = "Please input disease incidence to estimate attributable risk.";
        var freq_dis = parseFloat(document.getElementById('dis_freq').value) / 100.0;
        if ( isNaN(freq_dis) || freq_dis <= 0 ) {
            att_risk = nonneg_error_message();
        } else {
            cont_sum_norm = (case_p + case_m) * ((1.0 - freq_dis) / freq_dis);
            cont_p_norm = cont_p * cont_sum_norm / (cont_p + cont_m);
            att_risk = 100 * (case_p / (cont_p_norm + case_p) - freq_dis) + "%";
            if ( (form_type == "raw" && hyp_type == "chr") || form_type == "chr" ) {
                att_risk = att_risk + "<BR>Note: This is an estimate which calculates the increased chance "
                            + "of existing in an affected individual from the perspective of each chromosome";
            }
        }
        document.getElementById('genotype_eval_result').innerHTML = "<b>Fisher's exact (2-tailed):</b>\n<br>"
                                + "p = " + p_value
                                + "\n<br>From: <TABLE><TR><TD>case+: " + case_p + "</TD><TD>case-: " + case_m 
                                + "</TD>\n</TR><TR><TD>control+: " + cont_p + "</TD><TD>control-: " + cont_m 
                                + "</TD></TR></TABLE>\n<br><b>Attributable risk:</b> " + att_risk + "\n";
    } else {
        document.getElementById('genotype_eval_result').innerHTML = nonneg_error_message();
    }
}

function fisher_exact_two_tailed (a, b, c, d) {
    // Based on Wikipedia's description (as of Nov 12 2010) of the 
    // R implementation: to test all distributions "at least as extreme" take 
    // all others with P-values less than or equal to the original are added 
    // to get the sum.
    var p_orig = prob_table(a, b, c, d);
    var p_sum = p_orig;
    var a_b = a + b; var c_d = c + d; var a_c = a + c; var b_d = b + d; var n = a + b + c + d;
    for (var newa = 0; newa <= n; newa++) {
        newb = a_b - newa;
        newc = a_c - newa;
        newd = b_d - newb;
        if (newa == a) {
            continue;
        }
        if (newb >= 0 && newc >= 0 && newd >= 0) {
            var p_new = prob_table(newa, newb, newc, newd);
            if (p_new <= p_orig) {
                p_sum = p_sum + p_new;
            }
        }
    }
    return p_sum;
}

function prob_table(a, b, c, d) {
    var n = a + b + c + d;
    var log_numerator = log_factorial(a + b) + log_factorial(c + d) + log_factorial(a + c) + log_factorial(b + d);
    var log_denominator = log_factorial(a) + log_factorial(b) + log_factorial(c) + log_factorial(d) + log_factorial(n);
    var log_result = log_numerator - log_denominator;
    var result = Math.exp(log_result);
    return result;
}

function log_factorial(n) {
    if (n == 0) {
        return 0;
    } else {
        var sum_log = 0;
        var i = n;
        for (i = n; i >= 1; i--) {
            sum_log = sum_log + Math.log(i);
        }
        return sum_log;
    }
}


