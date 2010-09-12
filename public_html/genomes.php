<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Genomes";

$page_content = "";  // all html output stored here

$display_genome_ID = $_POST['genome_id'];  // ID corresponding to genome

$user = getCurrentUser();

if ($user and $display_genome_ID) {
    $page_content .= genome_display($display_genome_ID);
} else {
    $page_content .= "<h2>Public genomes</h2>";
    $page_content .= "Public genomes will be listed here.";
    $page_content .= "<hr>";
    if ($user) {
        $page_content .= "<h2>Uploaded genomes</h2>\n";
        $page_content .= list_uploaded_genomes($user);
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

function list_uploaded_genomes($user) {
    $returned_text = "";
    $user_oid = $user['oid'];
    $db_query = theDb()->getAll ("SELECT * FROM private_genomes WHERE oid=?", array("$user_oid"));
    if ($db_query) {
        $returned_text .= "<TABLE border=1>\n";
        $returned_text .= "<TR><TD>Nickname</TD><TD>ID</TD><TD>Action</TD></TR>\n";
        foreach ($db_query as $result) {
            $returned_text .= "<TR><TD>" . $result['nickname'] . "</TD><TD>" 
                            . $result['shasum'] . "</TD><TD>" 
                            . uploaded_genome_actions($result) . "</TD></TR>\n";
        }
        $returned_text .= "</TABLE>\n";
    } else {
        $returned_text .= "You have no uploaded genomes.\n";
    }
    return($returned_text);
}

function uploaded_genome_actions($result) {
    $returned_text = "<form action=\"/genomes.php\" method=\"post\">\n";
    $returned_text .= "<input type=\"hidden\" name=\"genome_id\" value=\"" 
                    . $result['shasum'] . "\">\n";
    $returned_text .= "<input type=\"submit\" value=\"Get report\" "
                        . "class=\"button\" \/></form>\n";
    return($returned_text);
}

function genome_display($shasum) {
    $returned_text = "Display results for $shasum\n";
    return($returned_text);
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
