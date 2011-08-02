<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

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
<?php if (!ereg ('Firefox/3\.0|MSIE 7\.0', $_SERVER["HTTP_USER_AGENT"])): ?>
<link rel="stylesheet" type="text/css" href="optional-css3.css" media="screen"/>
<?php endif; ?>
<script type="text/javascript" 
 src="http://ajax.googleapis.com/ajax/libs/prototype/1.6.1/prototype.js"></script>
<script type="text/javascript" src="/DataTables-1.8.1/media/js/jquery.js"></script>
<script type="text/javascript" src="/DataTables-1.8.1/media/js/jquery.dataTables.js"></script>
<script type="text/javascript" src="/jquery-ui/js/jquery-ui-1.8.14.custom.min.js"></script>
<link rel="stylesheet" type="text/css" href="/jquery-ui/css/custom-theme/jquery-ui-1.8.14.custom.css"/>
<link rel="stylesheet" type="text/css" href="/DataTables-1.8.1/media/css/demo_page.css"/>
<link rel="stylesheet" type="text/css" href="/DataTables-1.8.1/media/css/demo_table_jui.css"/>
<script type="text/javascript">
  jQuery.noConflict();
</script>
<script type="text/javascript" src="/js/addEvent.js"></script>
<script type="text/javascript" src="/js/message.js"></script>
<script type="text/javascript" src="/js/edit-autosave-submit.js"></script>
<script type="text/javascript" src="/js/evidence.js"></script>
<script type="text/javascript" src="/js/report.js"></script>
<script type="text/javascript" src="/js/show-what.js"></script>
<script type="text/javascript" src="/js/datatable_setup.js"></script>
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
			<a href="/about">About</a>
			<a href="/genomes">Genomes</a>
			<a href="/guide_editing">Editing guide</a>
			<a href="/edits">Recent changes</a>
			<a href="/editors">Contributors</a>
			<a href="/download">Download</a>
<?php if (isset($_SESSION) && array_key_exists("user",$_SESSION)): ?>
			<a href="/report">Reports</a>
			<a href="/calculators">Calculators</a>
			<a href="/logout.php?return_url=<?=urlencode($_SERVER["REQUEST_URI"])?>"><u>Log out</u></a>
<?php endif; ?>
			<div class="clearer"><span></span></div>
		</div>

	</div>

	<div class="main">
		
			<div class="content"><?php if (!(ereg ("evidence.personalgenomes.org", $_SERVER["HTTP_HOST"]))) { ?>

<div class="redalert">
<P>Note: This is <strong>not</strong> the real GET-Evidence site.  It is a <strong>development sandbox</strong>.  If you expect the site to be stable and you want your edits to be saved, use <A href="http://evidence.personalgenomes.org<?=$_SERVER['REQUEST_URI']?>">evidence.personalgenomes.org<?=$_SERVER['REQUEST_URI']?></A> instead.</P>
</div>


			<?php } ?>

			<?php frag("content"); ?>

        </div>

<?php if (!ereg ('^/vis', $_SERVER["REQUEST_URI"])) { ?>
		<div class="sidenav">

<?php
		if (getCurrentUser()):
		  print "<div class=\"desc\">Logged in: <strong>" . htmlspecialchars(getCurrentUser("nickname")) . "</strong></div>";
		endif;
?>

			<h1>Gene search</h1>
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

<?php if (getCurrentUser("is_admin")): ?>
		<DIV class="sidebar_message_container"><P id="curation" class="sidebar_message ui-corner-all">You have <B>Curator Power</B>.
<?php if (isset($GLOBALS["signoff_edit_ids"])): ?>
		<BR /><BR /><A id="curator-signoff-orig" class="curator-signoff ui-state-highlight" href="#" edit-ids="<?=$GLOBALS["signoff_edit_ids"]?>">Sign off / approve this version</A>
<?php endif; ?>
		<SPAN class="ui-helper-hidden">
		<BR /><BR /><A id="curator-signoff-edited" class="curator-signoff ui-state-highlight" href="#">Submit &amp; sign off</A>
		</SPAN>
		</P></div>
<?php endif; ?>

		<div class="sidebar_message_container"><p id="message" class="message sidebar_message ui-corner-all" style="display: <?php echo (!isset($gOut["message"]) || 0==strlen($gOut["message"]) ? "none" : "block"); ?>;"><?php if(isset($gOut["message"])) echo $gOut["message"]; ?></p></div>

		</div>
<?php } ?>
	
		<div class="clearer"><span></span></div>

	</div>

</div>

<div class="footer">Data available under <A href="http://creativecommons.org/publicdomain/zero/1.0/">CC0</A>.  Web application &copy; 2010 Clinical Future, Inc.</div>
<!--
Template from <a href="http://arcsin.se">Arcsin</a>
Current oid <?php echo getCurrentUser("oid"); ?>
-->

</body>
</html>
