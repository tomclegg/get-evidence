<?php

include "lib/setup.php";

header ("Content-type: application/json");
print json_encode ($_POST);