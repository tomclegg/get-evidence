<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

include "lib/setup.php";

$new_flat = false;
if (isset($_REQUEST["url"]) && strlen($_REQUEST["url"]) > 0)
    $new_flat = evidence_set_my_web_vote ($_REQUEST["variant_id"],
					  $_REQUEST["url"],
					  $_REQUEST["score"]);

$myvotes =& evidence_get_my_web_vote ($_REQUEST["variant_id"]);
$allvotes =& evidence_get_web_votes ($_REQUEST["variant_id"]);

$response = array ("my" => $myvotes,
		   "all" => $allvotes);
if ($new_flat)
    $response["autoscore"] = $new_flat["autoscore"];

header ("Content-type: application/json");
print json_encode ($response);
