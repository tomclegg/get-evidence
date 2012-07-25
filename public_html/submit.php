<?php // -*- mode: java; indent-tabs-mode: nil; -*-

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";

foreach (array('api_key', 'api_secret', 'dataset_locator', 'dataset_name', 'dataset_is_public') as $k) {
    if (!isset($_REQUEST[$k])) {
        respond(false, array('error' => "Missing request parameter '$k'"));
    }
}

if (!preg_match('/^[a-z]{2}$/', $_REQUEST['api_key'])) {
    respond(false, 'Invalid api_key');
}

if (hash_hmac('md5', $_REQUEST['api_key'], $gSiteSecret) != $_REQUEST['api_secret']) {
    respond(false, 'Incorrect api_secret');
}

$api_key = $_REQUEST['api_key'];
$shasum = hash('sha1', $_REQUEST['dataset_locator']);

theDB()->query ("INSERT IGNORE INTO private_genomes SET
                 oid=?, shasum=?, upload_date=SYSDATE()",
                array ($api_key, $shasum));
theDB()->query ("UPDATE private_genomes SET
                 dataset_locator=?, nickname=?, is_public=? WHERE oid=? AND shasum=?",
                array ($_REQUEST['dataset_locator'],
                       $_REQUEST['dataset_name'],
                       $_REQUEST['dataset_is_public'],
                       $api_key, $shasum));
$confirm_shasum = theDb()->getOne ("SELECT shasum FROM private_genomes WHERE oid=? AND shasum=?",
                                   array($api_key, $shasum));
if ($confirm_shasum != $shasum) {
    respond(false, array('error' => 'Some sort of database error happened'));
}

if (isset($_REQUEST['human_id']) &&
    substr($_REQUEST['human_id'],0,2) == $api_key) {
    // If your api_key is "hu", you are in charge of global human IDs
    // starting with "hu".  Ignore attempts to assign other human IDs.
    theDB()->query ("INSERT IGNORE INTO genomes SET global_human_id=?",
                    array ($_REQUEST['human_id']));
    theDB()->query ("UPDATE private_genomes SET
                     global_human_id=? WHERE oid=? AND shasum=?",
                    array ($_REQUEST['human_id'],
                           $api_key, $shasum));
    if ($_REQUEST['human_name']) {
        theDB()->query ("UPDATE genomes SET name=? WHERE global_human_id=?",
                        array ($_REQUEST['human_name'], $_REQUEST['human_id']));
    }
}

$result_url = 'http://' . $_SERVER['HTTP_HOST'] . '/genomes?display_genome_id=' . $shasum;
if (!$_REQUEST['dataset_is_public']) {
    $access_token = hash_hmac('md5', $shasum, $gSiteSecret);
    $result_url .= '&access_token=' . $access_token;
}

respond(true, array('result_url' => $result_url));

function respond($success, $data)
{
    $data['success'] = $success;
    print json_encode($data);
    exit;
}
