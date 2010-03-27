<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

global $gOut;
if (!array_key_exists ("title", $gOut)) $gOut["title"] = $gOut["site_title"];

function frag($tag)
{
   global $gOut, $gTheTextile;
   if(array_key_exists($tag."_textile", $gOut))
     {
       print $gTheTextile->textileThis($gOut[$tag."_textile"]);
     }
   else if(array_key_exists($tag, $gOut))
     {
       print $gOut[$tag];
     }
   else if(function_exists ("print_$tag"))
     {
       call_user_func ("print_$tag", $tag);
     }
}

header('Content-Type: text/html; charset=UTF-8');

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8"/>
<meta name="description" content="description"/>
<meta name="keywords" content="keywords"/> 
<meta name="author" content="author"/> 
<link rel="stylesheet" type="text/css" href="default.css" media="screen"/>
<script type="text/javascript" 
 src="http://ajax.googleapis.com/ajax/libs/prototype/1.6.1/prototype.js"></script>
<script type="text/javascript" src="/js/addEvent.js"></script>
<script type="text/javascript" src="/js/message.js"></script>
<script type="text/javascript" src="/js/edit-autosave-submit.js"></script>
<script type="text/javascript" src="/js/evidence.js"></script>
<script type="text/javascript" src="/js/report.js"></script>
<title><?php frag("title"); ?></title>
</head>

<body>
<script type="text/javascript" src="/js/wz_tooltip.js"></script>
<script type="text/javascript" src="/js/tip_balloon.js"></script>

<div class="container">

	<div class="header">
		
		<div class="title">
			<h1><?php frag("title"); ?></h1>
		</div>

		<div class="navigation">
			<a href="/edits">Recent changes</a>
			<a href="/editors">Contributors</a>
			<a href="/about">About</a>
			<a href="/download">Download</a>
			<a href="/report">Reports</a>
			<a href="/vis">Visualization</a>
<?php if (isset($_SESSION) && array_key_exists("user",$_SESSION)): ?>
			<a href="/logout.php?return_url=<?=urlencode($_SERVER["REQUEST_URI"])?>"><u>Log out</u></a>
<?php endif; ?>
			<div class="clearer"><span></span></div>
		</div>

	</div>

	<div class="main">
		
			<div class="content"><?php if (ereg ("evidence-dev", $_SERVER["HTTP_HOST"])) { ?>

<div style="outline: 1px dashed #300; background-color: #fdd; color: #300; padding: 20px; margin: 0 0 10px 0;">
			    <P style="margin: 0; padding: 0;">Note: This is <strong>not</strong> the real GET-Evidence site.  It is a <strong>development sandbox</strong>.  If you expect the site to be stable and you want your edits to be saved, use <A href="http://evidence.personalgenomes.org/">evidence.personalgenomes.org</A> instead.</P>
</div>


			<?php } ?><form id="mainform" action="save.php" method="POST">

			<?php frag("content"); ?>

		</form></div>

<?php if (!ereg ('^/vis', $_SERVER[REQUEST_URI])) { ?>
		<div class="sidenav">

<?php
		if (getCurrentUser()):
		  print "<div class=\"desc\">Logged in: <strong>" . htmlspecialchars(getCurrentUser("nickname")) . "</strong></div>";
		endif;
?>

			<h1>Search</h1>
			<form action="/">
			<div>
				"GENE" or "GENE A123C":<br />
				<input type="text" name="q" class="styled" size="12" /> <input type="submit" value="search" class="button" />
			</div>
			</form>

<?php		if (!getCurrentUser()): ?>

			<h1>Log in</h1>
			<div>
<?php
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
			if (isset($_SESSION) && array_key_exists ("auth_url", $_SESSION)):
			  print " value=\"" . htmlentities($_SESSION["auth_url"]) . "\"";
			endif;
?>

			  />&nbsp;<input type="submit" value="Log in" class="button" />
			</form>
<?php
			if (isset($_SESSION) && array_key_exists ("auth_error", $_SESSION)):
			  print "<br />" . htmlspecialchars($_SESSION["auth_error"]);
			  unset ($_SESSION["auth_error"]);
			endif;
?>
			</div>
<?php		endif; ?>

		<div class="unsubmitted_message_container"><div id="message" class="unsubmitted_message" style="display: <?php echo (0==strlen($gOut["message"]) ? "none" : "block"); ?>;"><?php echo $gOut["message"]; ?></div></div>

		</div>
<?php } ?>
	
		<div class="clearer"><span></span></div>

	</div>

</div>

<div class="footer">Data available under <A href="http://creativecommons.org/publicdomain/zero/1.0/">CC0</A>.  Web application &copy; 2009 Scalable Computing Experts.</div>
<!--
Template from <a href="http://arcsin.se">Arcsin</a>
-->

</body>
</html>
