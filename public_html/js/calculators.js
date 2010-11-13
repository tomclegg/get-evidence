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
        document.getElementById('factorial_result').innerHTML = error_message();
    } else {
        if ( a < 0 || b < 0 || c < 0 || d < 0 ) {
            document.getElementById('factorial_result').innerHTML = error_message();
        } else {
            var result = fisher_exact_two_tailed(a, b, c, d);
            if (result > 1.0) {
                result = 1;
            }
            document.getElementById('factorial_result').innerHTML = "p = " + result;
        }
    }
}

function error_message() {
    return "ERROR: Please enter non-negative numbers. They will be rounded to the nearest integer value.";
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


