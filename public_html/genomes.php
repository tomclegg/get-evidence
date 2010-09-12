<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Genomes";

$page_content = "";  // all html output stored here

$user = getCurrentUser();

$page_content .= "<h1>Upload a genome for analysis</h1>\n";
$page_content .= warning();
if ($user) {
    $page_content .= genome_entry_form();
} else {
    $page_content .= "<h2> You need to be logged in using your OpenID to upload "
                    . "genomes for analysis on GET-Evidence.</h2>\n";
}

$gOut["content"] = $page_content;

go();

function warning() {
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
