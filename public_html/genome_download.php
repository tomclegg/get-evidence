<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";

$ext = '';
$genome_id = $_REQUEST['download_genome_id'];
if (@$_REQUEST['download_type'] == 'ns') {
    $ext = $ext . '.ns';
    $fullPath = $GLOBALS["gBackendBaseDir"] . "/upload/" . $genome_id . "-out/ns";
} else {
    $fullPath = $GLOBALS["gBackendBaseDir"] . "/upload/" . $genome_id . "/genotype";
}

if (! file_exists($fullPath)) {
    if (file_exists($fullPath . '.gff')) {
	$ext = $ext . '.gff';
	$fullPath = $fullPath . '.gff';
    } elseif (file_exists($fullPath . '.gz')) {
	$ext = $ext . '.gz';
	$fullPath = $fullPath . '.gz';
    } elseif (file_exists($fullPath . '.gff.gz')) {
	$ext = $ext . '.gff.gz';
	$fullPath = $fullPath . '.gff.gz';
    } elseif (file_exists($fullPath . '.bz2')) {
	$ext = $ext . '.bz2';
	$fullPath = $fullPath . '.bz2';
    } elseif (file_exists($fullPath . '.gff.bz2')) {
	$ext = $ext . '.gff.bz2';
	$fullPath = $fullPath . '.gff.bz2';
    }
}

$nickname = $_REQUEST['download_nickname'];
$nickname = preg_replace('/ +/', '_', $nickname) . $ext;

$user = getCurrentUser();
$db_query = theDb()->getAll ("SELECT * FROM private_genomes WHERE shasum=?",
                                    array($genome_id));

# check you should have permission
$permission = false;
foreach ($db_query as $result) {
    if ($result['oid'] == $user['oid']
	|| $result['is_public'] > 0
	|| @$_REQUEST['access_token'] == hash_hmac('md5', $genome_id, $GLOBALS['gSiteSecret'])
	|| $result['oid'] == $pgp_data_user
        || $result['oid'] == $public_data_user)
	$permission = true;
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
