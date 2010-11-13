<?php

include "lib/setup.php";
include "lib/genome_display.php";
$gOut["title"] = "GET-Evidence: Genomes";

$page_content = "";  // all html output stored here
$public_data_user = "http://www.google.com/profiles/PGP.uploader";

$display_genome_ID = $_REQUEST['display_genome_id'];
$user_request_oid = $_REQUEST['user_request_oid'];

if (preg_match ('{^\d+$}', $_SERVER['QUERY_STRING'], $matches)) {
    $display_genome_ID = theDb()->getOne
	("SELECT shasum FROM private_genomes WHERE private_genome_id=?",
	 array ($matches[0]));
}

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
    $page_content .= list_uploaded_genomes($public_data_user);
    $page_content .= "<hr>";
    $page_content .= "<h2>Your uploads</h2>\n";
    if ($user) {
        $page_content .= list_uploaded_genomes($user["oid"]);
        $page_content .= "<hr>";
        $page_content .= "<h2>Upload a genome for analysis</h2>\n";
	$page_content .= upload_warning();
        $page_content .= genome_entry_form();
    } else {
        $page_content .= "<P>If you log in with your OpenID, you can process "
		    . "your own data by uploading it here.</P>";
	$page_content .= upload_warning();
    }
}

$gOut["content"] = $page_content;

go();

// Functions

function list_uploaded_genomes($user_oid) {
    global $public_data_user, $user;
    $db_query = theDb()->getAll ("SELECT * FROM private_genomes WHERE oid=? ORDER BY upload_date", array("$user_oid"));
    if ($db_query) {
        $returned_text = "<TABLE class=\"report_table\">\n";
        $returned_text .= "<TR><TH>Nickname</TH><TH>Action</TH></TR>\n";
        foreach ($db_query as $result) {
            $returned_text .= "<TR><TD>" . $result['nickname'] . "</TD><TD>";
            if ($user_oid == $public_data_user and $user['oid'] != $public_data_user) {
                $returned_text .= public_genome_actions($result) . "</TD></TR>\n";
            } else {
                $returned_text .= uploaded_genome_actions($result) . "</TD></TR>\n";
            }
        }
        $returned_text .= "</TABLE>\n";
	return $returned_text;
    }
    if ($user_oid == $public_data_user)
	return "<P>No public genomes are available yet.</P>";
    return "<P>You have not uploaded any genomes.</P>\n";
}

function public_genome_actions($result) {
    $returned_text = "<form action=\"/genomes.php\" method=\"get\">\n";
    $returned_text .= "<input type=\"hidden\" name=\"display_genome_id\" value=\"" 
                    . $result['shasum'] . "\">\n";
    $returned_text .= "<input type=\"submit\" value=\"Get report\" "
                        . "class=\"button\" \/></form>\n";
    return($returned_text);
}

function uploaded_genome_actions($result) {
    global $public_data_user, $user;
    # Get report button
    $returned_text = "<form action=\"/genomes\" method=\"get\">\n";
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

function upload_warning() {
    return <<<EOT

<DIV class="redalert">

<P><BIG><STRONG>IMPORTANT:</STRONG></BIG> By uploading data our system
you agree to our <A HREF="tos">terms of service</A>.</P>

<P>&nbsp;</P>

<P>In particular, we do <STRONG>not</STRONG> guarantee the privacy of any
data you upload.</P>

<P>&nbsp;</P>

<P>If you want to process private data, we encourage you to install
your own GET-Evidence, or to contact us for other
options.</P>
</DIV>

EOT
;
}

function genome_entry_form() {
    $returned_text = "<div>\n<form enctype=\"multipart/form-data\" "
                    . "action=\"/genome_upload.php\" method=\"post\">\n";
    $returned_text .= "<label class=\"label\">Filename<br>\n";
    $returned_text .= "<input type=\"hidden\" name=\"MAX_FILE_SIZE\" "
                        . "value=\"500000000\">\n";
    $returned_text .= "<input type=\"file\" class=\"file\" name=\"genotype\" "
                        . "id=\"genotype\"></label><br>\n";
    $returned_text .= "<label class=\"label\">Genome name<br>\n";
    $returned_text .= "<input type=\"text\" size=\"64\" name=\"nickname\" "
                        . "id=\"nickname\"></label>\n";
    $returned_text .= "<input type=\"submit\" value=\"Upload\" class=\"button\" />\n";
    $returned_text .= "</form>\n</div>\n";
    $returned_text .= "<P>&nbsp;</P>\n";

    return($returned_text);
}

?>
