<?php

include "lib/setup.php";

$genome_id = $_REQUEST['download_genome_id'];
$fullPath = $GLOBALS["gBackendBaseDir"] . "/upload/" . $genome_id . "/genotype.gff";
$nickname = $_REQUEST['download_nickname'];
$nickname = preg_replace('/ +/', '_', $nickname) . ".gff";

$pgp_data_user = "http://www.google.com/profiles/PGP.uploader";
$public_data_user = "http://www.google.com/profiles/Public.Genome.Uploader";
$user = getCurrentUser();
$db_query = theDb()->getAll ("SELECT oid FROM private_genomes WHERE shasum=?",
                                    array($genome_id));

# check you should have permission
$permission = false;
foreach ($db_query as $result) {
    if ($result['oid'] == $user['oid'] || $result['oid'] == $pgp_data_user
        || $result['oid'] == $public_data_user) $permission = true;
}

if ($permission) {
    if ($fd = fopen ($fullPath, "r")) {
        $fsize = filesize($fullPath);
        header("Content-type: text/plain");
        header("Content-Disposition: attachment; filename=\"" . $nickname . "\"");
        header("Content-length: $fsize");
        header("Cache-control: private"); //use this to open files directly
        while(!feof($fd)) {
            $buffer = fread($fd, 2048);
            echo $buffer;
        }
    } else {
        print "Error: Unable to open file for download!";
    }
    fclose ($fd);
} else {
    print "Sorry, you don't have permission to download this genome.";
}

?>
