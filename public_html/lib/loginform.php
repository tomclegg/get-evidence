<?php ; // -*- mode: java; c-basic-offset: 2; tab-width: 8; indent-tabs-mode: nil; -*-

global $gOpenidEasyProviders;
foreach ($gOpenidEasyProviders as $url => $name) {
?>
  <form action="/openid_start.php" method="post">
    <input type="hidden" name="return_url" value="<?=htmlentities($_SERVER["REQUEST_URI"])?>">
    <input type="hidden" name="auth_url" id="auth_url" value="<?=htmlentities($url)?>">
    <input type="submit" value="<?=htmlentities($name)?> login" class="button" />
    </form>
    <br />
<?php
}
?>
  <form action="/openid_start.php" method="post">
  <input type="hidden" name="return_url" value="<?=htmlentities($_SERVER["REQUEST_URI"])?>">
  OpenID URL:<br /><input type="text" name="auth_url" class="styled" id="auth_url"
  <?php
  if (isset($_SESSION) && array_key_exists ("auth_url", $_SESSION)) {
    print " value=\"" . htmlentities($_SESSION["auth_url"]) . "\"";
  }
  ?>
    
  />&nbsp;<input type="submit" value="Log in" class="button" />
  </form>

  <?php
  if (isset($_SESSION) && array_key_exists ("auth_error", $_SESSION)) {
    print "<br />" . htmlspecialchars($_SESSION["auth_error"]);
    unset ($_SESSION["auth_error"]);
  }
?>
