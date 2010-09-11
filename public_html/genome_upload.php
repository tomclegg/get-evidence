<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Genome uploaded";

$user = getCurrentUser();
$page_content = "";

include('xmlrpc/xmlrpc.inc');

// Сheck that we have a file
if((!empty($_FILES["genotype"])) && ($_FILES['genotype']['error'] == 0)) {
    $filename = basename($_FILES['genotype']['name']);
    $ext = substr($filename, strrpos($filename, '.') + 1);
    if (($ext == "txt" || $ext == "gff") && ($_FILES["genotype"]["size"] < 300000000)) {
        $tempname = $_FILES['genotype']['tmp_name'];
        $shasum = sha1_file($tempname);
        $page_content .= "shasum is $shasum<br>";
        $permname = "/tmp/$shasum/genotype.gff";
        // Attempt to move the uploaded file to its new place
        mkdir ("/tmp/$shasum");
        if (move_uploaded_file($tempname, $permname)) {
            $nickname = $_POST['nickname'];
            $oid = $user['oid'];
            $page_content .= "It's done! The file has been saved as: $permname<br>"; 
            $page_content .= "User ID is " . $oid . ", genome ID is " . $shasum . ", nickname is " . $nickname . "<br>\n";
            theDB()->query ("INSERT IGNORE INTO private_genomes SET
                                oid=?, nickname=?, shasum=?",
                                array ($oid,$nickname,$shasum));
        } else {
            $page_content .= "Error: A problem occurred during file upload!";
        }
    } else {
        $page_content .= "Error: Only .txt or .gff files under 1MB are accepted for upload";
    }
} else {
    $page_content .= "Error: No file uploaded";
}

// Now, send the filename to the xml-rpc server.
$client = new xmlrpc_client("http://localhost:8080/");
$client->return_type = 'phpvals';
$message = new xmlrpcmsg("submit_local", array(new xmlrpcval($permname, "string")));
$resp = $client->send($message);
if ($resp->faultCode()) { echo "Error: $resp->faultString();"; }
echo $resp->value();

$gOut["content"] = $page_content;

go();
?>
