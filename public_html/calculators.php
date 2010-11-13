<?php

include "lib/setup.php";

$gOut["title"] = "GET-Evidence: Calculators";

$page_content = "";  // all html output stored here

$page_content .= "<h1>PGP calculators<h1>";


$page_content .= "<script type=\"text/javascript\" src=\"/js/calculators.js\"></script>\n"
            . "<noscript>Your browser doesn't support or has disabled JavaScript</noscript>";

$page_content .= "<h2>Fisher's Exact Test (two-tailed)</h2>";

$page_content .=  "<TABLE><TR><TD>case+: <input type='text' id='fisher_a' size=4 ></TD>\n"
                . "<TD>case-: <input type='text' id='fisher_b' size=4 ></TD></TR>\n"
                . "<TR><TD>control+: <input type='text' id='fisher_c' size=4 ></TD>\n"
                . "<TD>control-: <input type='text' id='fisher_d' size=4 ></TD></TR></TABLE>\n"
                . "<input type='submit' value='Calculate p-value' onClick='return_fisher_exact()'>\n"
                . "<p id=\"factorial_result\">Click button to get result... </p>\n";


$gOut["content"] = $page_content;

go();


?>
