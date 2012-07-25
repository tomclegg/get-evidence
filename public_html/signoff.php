<?php
    ;

// Copyright: see COPYING
// Authors: see git-blame(1)

include "lib/setup.php";

$edit_ids = explode (",", $_REQUEST["edit_ids"]);
evidence_signoff ($edit_ids);

header ("Content-type: application/json");
print json_encode (array("success" => true));
