<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence: Genome uploaded";

$user = getCurrentUser();
$page_content = "";

$reprocess_genome_ID = $_POST['reprocess_genome_id'];
$delete_genome_ID = $_POST['delete_genome_id'];
$delete_genome_nickname = $_POST['delete_genome_nickname'];
$user_oid = $_POST['user_oid'];

include('xmlrpc/xmlrpc.inc');

// Сheck that we have a file
if ($reprocess_genome_ID) {
    $page_content .= "Starting reprocessing of " . $reprocess_genome_ID . "<br>\n";
    $page_content .= "Old data will remain available until new analysis is complete.<br>\n";
    $permname = "/home/trait/upload/" . $reprocess_genome_ID . "/genotype.gff";
    send_to_server($permname);
} elseif ($delete_genome_ID) {
    if ($user['oid'] == $user_oid) {
        $page_content .= "Deleting " . $user_oid . " " . $delete_genome_nickname 
                        . " " . $delete_genome_ID . "<br>\n";
        theDb()->query ("DELETE FROM private_genomes WHERE oid=? AND nickname=? AND shasum=?", 
                            array ("$user_oid", "$delete_genome_nickname", "$delete_genome_ID"));
        $db_query = theDb()->getAll ("SELECT * FROM private_genomes WHERE shasum=?", array($delete_genome_ID));
        if ($db_query) {
            $page_content .= "Your usage of this data instance (Nickname \""
                            . $delete_genome_nickname . "\", ID \"" . $delete_genome_ID
                            . "\") has been removed, but the underlying data remains because "
                            . "another user has uploaded a duplicate, or you have a duplicate "
                            . "of this genome under a different nickname.";
        } else {
            $dir1 = "/home/trait/upload/" . $delete_genome_ID;
            $dir2 = "/home/trait/upload/" . $delete_genome_ID . "-out";
            if ($dir1 != "/home/trait/upload/" and delete_directory($dir1) and delete_directory($dir2)) {
                $page_content .= "All original data for this genome has been removed."
                    . " Genome ID: " . $delete_genome_ID . "<br>\n";
            } else {
                $page_content .= "ERROR: For some reason we are unable to delete this genome!<br>\n";
                $page_content .= "Please SAVE A COPY of this genome ID: " . $delete_genome_ID . "<br>\n";
                $page_content .= "This ID is important for later identification of the data. "
                                . "The maintainers of this database might be able to help you. <br>\n";
            }
            
        }

    } else {
        $page_content .= "User ID doesn't match the requesting user!";
    }
} elseif((!empty($_FILES["genotype"])) && ($_FILES['genotype']['error'] == 0)) {
    $filename = basename($_FILES['genotype']['name']);
    $ext = substr($filename, strrpos($filename, '.') + 1);
    if (($ext == "txt" || $ext == "gff" || $ext == "gz") && ($_FILES["genotype"]["size"] < 300000000)) {
        $tempname = $_FILES['genotype']['tmp_name'];
        $shasum = sha1_file($tempname);
        $page_content .= "shasum is $shasum<br>";
        $permname = "/home/trait/upload/$shasum/genotype.gff";
        // Attempt to move the uploaded file to its new place
        mkdir ("/home/trait/upload/$shasum");
        if (move_uploaded_file($tempname, $permname)) {
            $nickname = $_POST['nickname'];
            $oid = $user['oid'];
            send_to_server($permname);
            theDB()->query ("INSERT IGNORE INTO private_genomes SET
                                oid=?, nickname=?, shasum=?, upload_date=SYSDATE()",
                                array ($oid,$nickname,$shasum));
            $page_content .= "It's done! The file has been saved as: $permname<br>";
            $page_content .= "User ID is " . $oid . ", genome ID is " . $shasum . ", nickname is " . $nickname . "<br>\n";
        } else {
            $page_content .= "Error: A problem occurred during file upload!";
        }
    } else {
        $page_content .= "Error: Only .txt or .gff files under 1MB are accepted for upload";
    }
} else {
    $page_content .= "Error: No file uploaded";
}

// Send the filename to the xml-rpc server.
function send_to_server($permname) {
    $client = new xmlrpc_client("http://localhost:8080/");
    $client->return_type = 'phpvals';
    $message = new xmlrpcmsg("submit_local", array(new xmlrpcval($permname, "string")));
    $resp = $client->send($message);
    if ($resp->faultCode()) { echo "Error: $resp->faultString();"; }
    echo $resp->value();

}

// Delete all files in a directory recursively, then delete directory
function delete_directory($dirname) {
    if (is_dir($dirname))
        $dir_handle = opendir($dirname);
    if (!$dir_handle)
        return false;
    while($file = readdir($dir_handle)) {
        if ($file != "." && $file != "..") {
            if (!is_dir($dirname."/".$file))
                unlink($dirname."/".$file);
            else
                delete_directory($dirname.'/'.$file);    
        }
    }
    closedir($dir_handle);
    rmdir($dirname);
    return true;
}

$gOut["content"] = $page_content;

go();

?>
