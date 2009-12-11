<?php

global $gOut;
if (!array_key_exists ("title", $gOut)) $gOut["title"] = $gOut["site_title"];

function frag($tag)
{
   global $gOut;
   if(array_key_exists($tag, $gOut))
     {
       print $gOut[$tag];
     }
   else if(function_exists ("print_$tag"))
     {
       call_user_func ("print_$tag", $tag);
     }
}

?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN"
"http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=iso-8859-1"/>
<meta name="description" content="description"/>
<meta name="keywords" content="keywords"/> 
<meta name="author" content="author"/> 
<link rel="stylesheet" type="text/css" href="default.css" media="screen"/>
<script type="text/javascript" 
 src="http://ajax.googleapis.com/ajax/libs/prototype/1.6.1/prototype.js"></script>
<title><?php frag("title"); ?></title>
</head>

<body>

<div class="container">

	<div class="header">
		
		<div class="title">
			<h1><?php frag("title"); ?></h1>
		</div>

		<div class="navigation">
			<a href="/">Browse</a>
			<a href="/about.php">About</a>
			<a href="/contact.php">Contact</a>
<?php if (isset($_SESSION) && array_key_exists("user",$_SESSION)): ?>
			<a href="/logout.php"><u>Log out</u></a>
<?php endif; ?>
			<div class="clearer"><span></span></div>
		</div>

	</div>

	<div class="main">
		
		<div class="content">

			<?php frag("content"); ?>

		</div>

		<div class="sidenav">

<?php
		if (getCurrentUser()):
		  print "<div class=\"desc\">Logged in: <strong>" . htmlspecialchars(getCurrentUser("nickname")) . "</strong></div>";
		endif;
?>

			<h1>Search</h1>
			<form action="/search.php">
			<div>
				<input type="text" name="q" class="styled" /> <input type="submit" value="search" class="button" />
			</div>
			</form>

<?php		if (!getCurrentUser()): ?>

			<h1>Log in</h1>
			<form action="/openid_start.php" method="post">
			<div>
			   OpenID URL (or <a href="#" onClick="$('auth_url').value='https://www.google.com/accounts/o8/id';">use Google</a> or <a href="#" onClick="$('auth_url').value='http://yahoo.com';">use Yahoo</a>):<br /><input type="text" name="auth_url" class="styled" id="auth_url"
<?php
			if (isset($_SESSION) && array_key_exists ("auth_url", $_SESSION)):
			  print " value=\"" . htmlentities($_SESSION["auth_url"]) . "\"";
			endif;
?>

 /> <input type="submit" value="log in" class="button" />
<?php
			if (isset($_SESSION) && array_key_exists ("auth_error", $_SESSION)):
			  print "<br />" . htmlspecialchars($_SESSION["auth_error"]);
			  unset ($_SESSION["auth_error"]);
			endif;
?>
			</div>
			</form>
<?php		endif; ?>

		</div>
	
		<div class="clearer"><span></span></div>

	</div>

</div>

<div class="footer">&copy; 2009 President and Fellows of Harvard College</div>
<!--
Template from <a href="http://arcsin.se">Arcsin</a>
-->

</body>
</html>
