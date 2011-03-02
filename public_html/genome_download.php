<?php

include "lib/setup.php";

$ext = '.gff';
$genome_id = $_REQUEST['download_genome_id'];
if (@$_REQUEST['download_type'] == 'ns') {
    $ext = '.ns.gff';
    $fullPath = $GLOBALS["gBackendBaseDir"] . "/upload/" . $genome_id . "-out/ns.gff";
} else
    $fullPath = $GLOBALS["gBackendBaseDir"] . "/upload/" . $genome_id . "/genotype.gff";
$nickname = $_REQUEST['download_nickname'];
$nickname = preg_replace('/ +/', '_', $nickname) . $ext;

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
    if (! file_exists($fullPath)) {
        if (file_exists ($fullPath . ".gz")) {
            $fullPath = $fullPath . ".gz";
            $nickname = $nickname . ".gz";
        }
    }
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
