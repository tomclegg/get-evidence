<?php

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";
include "lib/whpipeline.php";
$gOut["title"] = "GET-Evidence: Genome uploaded";

$user = getCurrentUser();
$page_content = "";


include('xmlrpc/xmlrpc.inc');

// check that we have a file
if (isset($_POST['reprocess_genome_id'])) {
    $reprocess_genome_ID = $_POST['reprocess_genome_id'];
    $reprocess_type = 'full';
    if (@$_POST['reproc_type'] == 'getev') {
	$reprocess_type = 'getev';
    }
    $in_dir = $GLOBALS["gBackendBaseDir"] . "/upload/" . $reprocess_genome_ID;
    $permname = $in_dir . "/genotype";
    if (! file_exists($permname)) {
	if (file_exists ($permname . ".zip")) {
	    $permname = $permname . ".zip";
	} elseif (file_exists ($permname . ".gz")) {
            $permname = $permname . ".gz";
        } elseif (file_exists ($permname . ".bz2")) {
            $permname = $permname . ".bz2";
        } elseif (file_exists ($permname . ".gff")) {
	    $permname = $permname . ".gff";
	} elseif (file_exists ($permname . ".gff.gz")) {
	    $permname = $permname . ".gff.gz";
	} elseif (file_exists ($permname . ".gff.bz2")) {
	    $permname = $permname . ".gff.bz2";
	} elseif (is_link ($symlink = $in_dir . '/input.locator')) {
	    $warehouse_locator = readlink($symlink);
	}
    }
    if (file_exists($permname) || $warehouse_locator) {
        $page_content .= "<P>Reprocessing data: " . $reprocess_genome_ID . "</P>\n";
        $page_content .= "<P>The <A href=\"genomes?display_genome_id=$reprocess_genome_ID\">existing results</A> will remain available until the new analysis is complete.</P>\n";
	if ($warehouse_locator)
	    run_whpipeline($warehouse_locator, $reprocess_genome_ID, $reprocess_type == 'getev');
	else
	    send_to_server($permname, $reprocess_type);
    } else {
        $page_content .= "<P>Error! Sorry, for some reason we are unable to find the "
            . "original file for " . $reprocess_genome_ID . "</P>";
    }
} elseif (isset($_POST['delete_genome_id'])) {
    $delete_genome_id = $_POST['delete_genome_id'];
    if (!preg_match ('{^[0-9]+$}', $delete_genome_id)) {
	$page_content .= "<P>Invalid delete_genome_id supplied: $delete_genome_id</P>";
    } else {
	$shasum = theDb()->getOne
	    ("SELECT shasum FROM private_genomes WHERE private_genome_id=? AND oid=?",
	     array($delete_genome_id, $user['oid']));
	theDb()->query
	    ("DELETE FROM private_genomes WHERE private_genome_id=? AND oid=?", 
	     array ($delete_genome_id, $user['oid']));
	$keeping_data = theDb()->getOne ("SELECT 1 FROM private_genomes WHERE shasum=? LIMIT 1",
					 array($shasum));
	if ($keeping_data) {
	    $page_content .= "<P>This entry has been removed from your \"uploaded genomes\" list, but the underlying data has not been deleted because it is referenced by other processing jobs.  Either you uploaded it more than once, or another user uploaded an identical file.</P>";
	} else {
	    $dir1 = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum;
	    $dir2 = $GLOBALS["gBackendBaseDir"] . "/upload/" . $shasum . "-out";
	    if (delete_directory($dir2) &&
		delete_directory($dir1)) {
		$page_content .= "<P>Data and results for this job (input data hash $shasum) have been removed.</P>";
	    } else {
		$page_content .= "<P><B>OOPS.</B>  For some reason we are unable to delete your data.</P><P>Please <B>save a copy</B> of this hash: $shasum.</P><P>This may help the site admin track down and fix the problem.</P>";
		error_log ("Failed to delete id=$delete_genome_id sha1=$shasum");
	    }
	}
    }
} elseif((!empty($_FILES["genotype"])) && (($_FILES['genotype']['error'] == 2) || $_FILES['genotype']['error'] == 3)) {
    $page_content .= "Error: file too large! Size limit is 500MB.";
} elseif((!empty($_FILES["genotype"])) && ($_FILES['genotype']['error'] == 0)) {
    $filename = basename($_FILES['genotype']['name']);
    $ext = substr($filename, strrpos($filename, '.') + 1);
    if (($ext == "txt" || $ext == "csv" || $ext == "gff" || $ext == "zip" || $ext == "gz" || $ext == "bz2") && ($_FILES["genotype"]["size"] < 524288000)) {
        $tempname = $_FILES['genotype']['tmp_name'];
        $shasum = sha1_file($tempname);
        $page_content .= "shasum is $shasum<br>";
        $permname = $GLOBALS["gBackendBaseDir"] . "/upload/$shasum/genotype";
        if ($ext == "zip") {
	    $permname = $permname . ".zip";
	} elseif ($ext == "gz") {
            $permname = $permname . ".gz";
        } elseif ($ext == "bz2") {
            $permname = $permname . ".bz2";
        }
	$already_have = (file_exists($permname) &&
			 sha1_file($permname) == $shasum);
        // Attempt to move the uploaded file to its new place
	if ($already_have)
	  unlink ($tempname);
	else
	  @mkdir ($GLOBALS["gBackendBaseDir"] . "/upload/$shasum");
        if ($already_have || move_uploaded_file($tempname, $permname)) {
            $nickname = $_POST['nickname'];
            $oid = $user['oid'];
	    if (!$already_have)
	      send_to_server($permname);
            theDB()->query ("INSERT IGNORE INTO private_genomes SET
                                oid=?, nickname=?, shasum=?, upload_date=SYSDATE()",
                                array ($oid,$nickname,$shasum));
	    header ("Location: genomes?display_genome_id=$shasum");
        } else {
            $page_content .= "Error: A problem occurred during file upload!";
        }
    } else {
        $page_content .= "Error: Only .txt, .csv, .gff, .zip, .gz or .bz2 files under 500MB are accepted for upload";
    }
} elseif (isset($_POST['location']) && $user && $user['oid']) {
  $location = preg_replace('{/\.\./}','',$_POST['location']); # No shenanigans
  if (preg_match('{^file:///}',$location)) {
    $location = preg_replace('{^file://}','',$location);
    if (file_exists($location) && strpos ($location, $GLOBALS["gBackendBaseDir"] . "/upload/") === 0) {
      $shasum = sha1_file($location);
      $permname = $GLOBALS["gBackendBaseDir"] . "/upload/$shasum/genotype";
      if (preg_match ('{\.zip$}', $location))
	  $permname = $permname . ".zip";
      elseif (preg_match ('{\.gz$}', $location))
          $permname = $permname . ".gz";
      elseif (preg_match ('{\.bz2$}', $location))
	  $permname = $permname . ".bz2";
      // Attempt to move the uploaded file to its new place
      @mkdir ($GLOBALS["gBackendBaseDir"] . "/upload/$shasum");
      $already_have = (file_exists($permname) &&
		       sha1_file($permname) == $shasum);
      if ($already_have || copy($location,$permname)) {
        $nickname = $_POST['nickname'];
        $oid = $user['oid'];
	if (!$already_have)
	  send_to_server($permname);
        theDB()->query ("INSERT IGNORE INTO private_genomes SET
                            oid=?, nickname=?, shasum=?, upload_date=SYSDATE()",
                            array ($oid,$nickname,$shasum));
	header ("Location: genomes?display_genome_id=$shasum");
	exit;
      } else {
        $page_content .= "Error: A problem occurred during file upload!";
      }
    } else {
      $page_content .= "Error: file not found on local filesystem!";
    }
  } else {
    $page_content .= "Error: Please use the file:/// syntax to refer to a local file!";
  }
} else {
    $page_content .= "Error: No file uploaded or file size exceeds limit";
}

// Send the filename to the xml-rpc server.
function send_to_server($permname, $type = "full") {
    $client = new xmlrpc_client("http://localhost:8080/");
    $client->return_type = 'phpvals';
    if ($type == "getev") {
	$message = new xmlrpcmsg("reprocess_getev", array(new xmlrpcval($permname, "string")));
    } else {
	$message = new xmlrpcmsg("submit_local", array(new xmlrpcval($permname, "string")));
    }
    $resp = $client->send($message);
    if ($resp->faultCode()) { error_log ("xmlrpc send Error: ".$resp->faultString()); }
    error_log ("xmlrpc send success: ".$resp->value());
    return true;
}

// Delete all files in a directory recursively, then delete directory
function delete_directory($dirname) {
    if (preg_match ('{/$}', $dirname)) // don't accidentally delete /foo/$ttypo
	return false;
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
