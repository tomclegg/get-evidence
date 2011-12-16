<?php ; // -*- mode: java; c-basic-offset: 2; tab-width: 8; indent-tabs-mode: nil; -*-

// Copyright 2009-2011 Clinical Future, Inc.
// Authors: see git-blame(1)

include "lib/setup.php";

$gOut["title"] = "GET-Evidence: Log in";

if (getCurrentUser('oid')) {
$gOut["content"] = <<<EOF
<p>You are logged in again.</p>
<p>This window will close automatically in a few seconds.</p>

<script type="text/javascript">
  window.setTimeout(window.close, 4000);
</script>
EOF
;
} else {
  ob_start();
  include 'lib/loginform.php';
  $gOut["content"] = ob_get_clean();
  $gOut["content"] .= "<p>&nbsp;</p>";
}
$gOut["nosidebar"] = true;
go();
