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
        document.getElementById('factorial_result').innerHTML = 
	    nonneg_error_message();
    } else {
        if ( a < 0 || b < 0 || c < 0 || d < 0 ) {
            document.getElementById('factorial_result').innerHTML = 
		nonneg_error_message();
        } else {
            var result = fisher_exact_two_tailed(a, b, c, d);
            if (result > 1.0) {
                result = 1;
            }
            document.getElementById('factorial_result').innerHTML = 
		"p = " + result;
        }
    }
}

function nonneg_error_message() {
    return "ERROR: Please enter non-negative numbers in all available " +
	"fields. They will be rounded to the nearest integer value.";
}

function update_gentyp_entry() {
    var form_type = document.getElementById('gentyp_data').value;
    if (form_type == "raw") {
        document.getElementById('gentyp_entry_raw').style.display = 'inline';
        document.getElementById('gentyp_entry_form_dom').style.display = 'none';
        document.getElementById('gentyp_entry_rec').style.display = 'none';
        document.getElementById('gentyp_entry_chr').style.display = 'none';
    } else if (form_type == "dom") {
        document.getElementById('gentyp_entry_raw').style.display = 'none';
        document.getElementById('gentyp_entry_dom').style.display = 'inline';
        document.getElementById('gentyp_entry_rec').style.display = 'none';
        document.getElementById('gentyp_entry_chr').style.display = 'none';
    } else if (form_type == "rec") {
        document.getElementById('gentyp_entry_raw').style.display = 'none';
        document.getElementById('gentyp_entry_dom').style.display = 'none';
        document.getElementById('gentyp_entry_rec').style.display = 'inline';
        document.getElementById('gentyp_entry_chr').style.display = 'none';
    } else if (form_type == "chr") {
        document.getElementById('gentyp_entry_raw').style.display = 'none';
        document.getElementById('gentyp_entry_dom').style.display = 'none';
        document.getElementById('gentyp_entry_rec').style.display = 'none';
        document.getElementById('gentyp_entry_chr').style.display = 'inline';
    } else {
    }
}

function eval_gentyp_data () {
    var form_type = document.getElementById('gentyp_data').value;
    var case_p = 0; var case_m = 0; var cont_p = 0; var cont_m = 0;
    var hyp_type = "";
    if (form_type == "raw") {
        for (var i = 0; i < document.raw_data.gentyp_hyp.length; i++) {
            if (document.raw_data.gentyp_hyp[i].checked == true) {
                hyp_type = document.raw_data.gentyp_hyp[i].value;
            }
        }
        var case_vv = get_int_from('case_vv');
        var case_vn = get_int_from('case_vn');
        var case_nn = get_int_from('case_nn');
        var cont_vv = get_int_from('cont_vv');
        var cont_vn = get_int_from('cont_vn');
        var cont_nn = get_int_from('cont_nn');
        if ( isNaN(case_vv) || isNaN(case_vn) || isNaN(case_nn) || 
	     isNaN(cont_vv) || isNaN(cont_vn) || isNaN(cont_nn) || 
	     case_vv < 0 || case_vn < 0 || case_nn < 0 || 
	     cont_vv < 0 || cont_vn < 0 || cont_nn < 0 ) {
            document.getElementById('gentyp_eval_result').innerHTML = 
		nonneg_error_message();
        }
        if (hyp_type == "dom") {
            case_p = case_vv + case_vn; 
	    case_m = case_nn; 
	    cont_p = cont_vv + cont_vn; 
	    cont_m = cont_nn;
        } else if (hyp_type == "rec") {
            case_p = case_vv; 
	    case_m = case_vn + case_nn; 
	    cont_p = cont_vv; 
	    cont_m = cont_vn + cont_nn;
        } else if (hyp_type == "chr") {
            case_p = case_vv * 2 + case_vn; 
	    case_m = case_vn + case_nn * 2;
            cont_p = cont_vv * 2 + cont_vn; 
	    cont_m = cont_vn + cont_nn * 2;
        } else {
            document.getElementById('gentyp_eval_result').innerHTML = 
		"Please pick a hypothesis to evaluate the data.";
        }
    } else if (form_type == "dom") {
        case_p = get_int_from('case_dom_p');
        case_m = get_int_from('case_dom_m');
        cont_p = get_int_from('cont_dom_p');
        cont_m = get_int_from('cont_dom_m');
    } else if (form_type == "rec") {
        case_p = get_int_from('case_rec_p');
        case_m = get_int_from('case_rec_m');
        cont_p = get_int_from('cont_rec_p');
	cont_m = get_int_from('cont_rec_m');
    } else if (form_type == "chr") {
        case_p = get_int_from('case_chr_p');
	case_m = get_int_from('case_chr_m');
        cont_p = get_int_from('cont_chr_p');
        cont_m = get_int_from('cont_chr_m');
    } else {
        document.getElementById('gentyp_eval_result').innerHTML = 
	    "Error: form type is unknown (??)";
    }
    if (case_p + case_m > 0 && cont_p + cont_m > 0) {
        var p_value = fisher_exact_two_tailed(case_p, case_m, cont_p, cont_m);
        if (p_value > 1.0) {
            p_value = 1;
        }
        var att_risk = "Please input disease incidence to estimate " +
	    "attributable risk.";
        var freq_dis = parseFloat(document.getElementById('dis_freq').value) / 
	    100.0;
        if ( isNaN(freq_dis) || freq_dis <= 0 ) {
            att_risk = nonneg_error_message();
        } else {
            cont_sum_norm = (case_p + case_m) * ((1.0 - freq_dis) / freq_dis);
            cont_p_norm = cont_p * cont_sum_norm / (cont_p + cont_m);
            att_risk = 100 * (case_p / (cont_p_norm + case_p) - freq_dis) + 
		"%";
            if ( (form_type == "raw" && hyp_type == "chr") || 
		 form_type == "chr" ) {
                att_risk = att_risk + 
		    "<BR>Note: This is an estimate which calculates the " +
		    "increased chance of existing in an affected individual " +
		    "from the perspective of each chromosome";
            }
        }
        document.getElementById('gentyp_eval_result').innerHTML = 
	    "<b>Fisher's exact (2-tailed):</b>\n<br>p = " + p_value +
	    "\n<br>From: <TABLE><TR><TD>case+: " + case_p + 
	    "</TD><TD>case-: " + case_m + "</TD>\n</TR><TR><TD>control+: " + 
	    cont_p + "</TD><TD>control-: " + cont_m + 
	    "</TD></TR></TABLE>\n<br><b>Attributable risk:</b> " + att_risk + 
	    "\n";
    } else {
        document.getElementById('gentyp_eval_result').innerHTML = 
	    nonneg_error_message();
    }
}

function eval_undisc_path () {
    var hyp_dis_prev = 
    	parseFloat(document.getElementById('hyp_dis_prev').value) / 100;
    var hyp_gene_cause =
    	parseFloat(document.getElementById('hyp_gene_cause').value) / 100;
    var hyp_var_cause =
	parseFloat(document.getElementById('hyp_var_cause').value) / 100;
    var hyp_penet =
    	parseFloat(document.getElementById('hyp_penet').value) / 100;
    var hyp_var_hyp = document.getElementById('hyp_var_hyp').value;
    var hyp_cont_var = document.getElementById('hyp_cont_var').value;
    hyp_freq = "";
    if (hyp_var_hyp == "dom") {
	hyp_freq = hyp_dis_prev * hyp_gene_cause * hyp_var_cause / hyp_penet;
    } else if (hyp_var_hyp == "rec") {
	hyp_freq = Math.sqrt(hyp_dis_prev * hyp_gene_cause / hyp_penet) *
	    hyp_var_cause;
    }
    var hyp_cont_var = get_int_from('hyp_cont_var');
    var hyp_cont_ref = get_int_from('hyp_cont_ref');
    output = "";
    if (isNaN(hyp_freq)) {
	output = "Please enter some numbers for the hypothetical variant.";
    } else {
	output = "Hypothetical variant frequency: " + hyp_freq + "<BR \>\n";
	if (isNaN(hyp_cont_var) || isNaN(hyp_cont_ref) || 
	    hyp_cont_var < 0 || hyp_cont_ref < 0 || 
	    hyp_cont_var + hyp_cont_ref <= 0) {
	    output = output + "Please enter some numbers for observations " +
		"in random controls (must be positive integers).<BR \>\n";
	} else {
	    hyp_cont_tot = hyp_cont_var + hyp_cont_ref;
	    var prob_less_than_var = 0;
	    for (var k = 0; k < hyp_cont_var; k++) {
		var prob = n_choose_k(hyp_cont_tot, k) * 
		    Math.pow(hyp_freq, k) * 
		    Math.pow((1-hyp_freq), (hyp_cont_tot - k));
	        prob_less_than_var += prob;
	    }
	    var p_value = 1 - prob_less_than_var;
	    output = output +
	        "Chance you would have at least this many observations for " +
		"such a variant by chance: " + p_value;
	}
    }
    document.getElementById('undisc_path_result').innerHTML = output;
}

function get_int_from (name) {
    int_data = 
	parseInt(Math.round(parseFloat(document.getElementById(name).value)));
    return int_data;
}

function fisher_exact_two_tailed (a, b, c, d) {
    // Based on Wikipedia's description (as of Nov 12 2010) of the 
    // R implementation: to test all distributions "at least as extreme" take 
    // all others with P-values less than or equal to the original are added 
    // to get the sum.
    var p_orig = prob_table(a, b, c, d);
    var p_sum = p_orig;
    var a_b = a + b; 
    var c_d = c + d; 
    var a_c = a + c; 
    var b_d = b + d; 
    var n = a + b + c + d;
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
    var log_numerator = log_factorial(a + b) + log_factorial(c + d) + 
	log_factorial(a + c) + log_factorial(b + d);
    var log_denominator = log_factorial(a) + log_factorial(b) + 
	log_factorial(c) + log_factorial(d) + log_factorial(n);
    var log_result = log_numerator - log_denominator;
    var result = Math.exp(log_result);
    return result;
}

function n_choose_k(n, k) {
    var log_n_fac = log_factorial(n);
    var log_k_fac = log_factorial(k);
    var log_n_minus_k_fac = log_factorial( n - k );
    var log_result = log_n_fac - (log_k_fac + log_n_minus_k_fac);
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
