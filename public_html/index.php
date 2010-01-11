<?php

include "lib/setup.php";
$gOut["title"] = "GET-Evidence";


function seealso_related ($gene, $aa_pos, $skip_variant_id)
{
    $related_variants = theDb()->getAll ("SELECT v.variant_gene gene, CONCAT(v.variant_aa_from,v.variant_aa_pos,v.variant_aa_to) aa_long FROM variants v WHERE v.variant_gene=? AND v.variant_aa_pos=? AND v.variant_id <> ?", array ($gene, $aa_pos, $skip_variant_id));
    $seealso = "";
    foreach ($related_variants as $x)
	{
	    $seealso .= "<LI>See also: <A href=\"".$x["gene"]."-".$x["aa_long"]."\">".$x["gene"]." ".$x["aa_long"]."</A></LI>\n";
	}
    if ($seealso)
	$seealso = "<DIV id=\"seealso\"><UL>$seealso</UL></DIV>\n";
    return $seealso;
}


$_GET["q"] = trim ($_GET["q"], "\" \t\n\r\0");

if (ereg ("^[0-9]+$", $_GET["q"]))
  $variant_id = $_GET["q"];
else if (ereg ("^([A-Za-z0-9_]+)[- ]([A-Za-z]+[0-9]+[A-Za-z\\*]+)(;([0-9]+))?$", $_GET["q"], $regs) &&
	 aa_sane ($aa = $regs[2])) {
  $gene = strtoupper($regs[1]);
  $max_edit_id = $regs[4];
  $variant_id = evidence_get_variant_id ("$gene $aa");
  if (!$variant_id) {
    $aa_long = aa_long_form ($aa);
    $aa_short = aa_short_form ($aa_long);
    header ("HTTP/1.1 404 Not found");
    $gOut["title"] = "$gene $aa_short";
    $gOut["content"] = <<<EOF
<H1>$gene $aa_short</H1>

<P>($gene $aa_long)</P>

<P>There is no GET-Evidence entry for this variant.</P>

EOF
;
    if (ereg("[0-9]+", $aa, $regs))
	$gOut["content"] .= seealso_related ($gene, $regs[0], 0);
    if (getCurrentUser())
      $gOut["content"] .= <<<EOF
&nbsp;

<BUTTON onclick="return evidence_add_variant('$gene','$aa_long');">Create new entry</BUTTON>
EOF
;
    go();
    exit;
  }
}
if (!$variant_id)
  {
    if (!$_GET["q"])
      {
	header ("Location: /edits");
	exit;
      }
    else
      {
	$q = theDb()->query ("SELECT * FROM variants WHERE variant_gene LIKE ? ORDER BY variant_gene, variant_aa_pos, variant_aa_to LIMIT 30",
			     array($_GET["q"]."%"));
	if (theDb()->isError($q)) die ($q->getMessage());
	$html = "";
	while ($row =& $q->fetchRow()) {
	  $html .= "<LI><A href=\"$row[variant_gene]-$row[variant_aa_from]$row[variant_aa_pos]$row[variant_aa_to]\">$row[variant_gene] $row[variant_aa_from]$row[variant_aa_pos]$row[variant_aa_to]</A></LI>\n";
	}
	if ($html == "") {
	  header ("HTTP/1.1 404 Not found");
	  $gOut["content"] = '<h1>Not found</h1><p>No results were found for your query: <cite>'.htmlspecialchars($_GET["q"]).'</cite></p>';
	}
	else
	  $gOut["content"] = "<h1>Search results</h1><p><ul>$html</ul></p>";
	go();
	exit;
      }
  }


$history_box = "";
if ($max_edit_id) {
    $version_date = strftime ("%B %e, %Y at %l:%M%P",
			      theDb()->getOne("SELECT UNIX_TIMESTAMP(edit_timestamp)
						FROM edits WHERE edit_id=?",
					      array($max_edit_id)));
    $previous_version = theDb()->getOne("SELECT MAX(edit_id) FROM edits
					WHERE variant_id=? AND edit_id<? AND is_draft=0",
					array($variant_id, $max_edit_id));
    $next_version = theDb()->getOne("SELECT MIN(edit_id) FROM edits
					WHERE variant_id=? AND edit_id>? AND is_draft=0",
				    array($variant_id, $max_edit_id));
    $contributor = theDb()->getRow("SELECT oid, fullname FROM edits
					LEFT JOIN eb_users ON edit_oid=oid
					WHERE edit_id=?",
				   array ($max_edit_id));
    $history_box .= "
<DIV style=\"outline: 1px dashed #300; background-color: #fdd; color: #300; padding: 20px 20px 0 20px; margin: 0 0 10px 0;\">
<P style=\"margin: 0; padding: 0;\">You are viewing ";
    if ($next_version)
	$history_box .= "an <STRONG>old version</STRONG> of this page that was saved on <STRONG>$version_date</STRONG>";
    else
	$history_box .= "the latest version of this page, saved on <STRONG>$version_date</STRONG>";
    $history_box .= " by <A href=\"edits?oid=".urlencode($contributor["oid"])."\">".htmlspecialchars($contributor["fullname"])."</A>.<UL>";

    if ($previous_version)
	$history_box .= "<LI>View the <A href=\"$gene-$aa;$previous_version\">previous version</A>";

    if ($next_version)
	$history_box .= "<LI>View the <A href=\"$gene-$aa;$next_version\">next version</A>";

    if ($next_version)
	$history_box .= "<LI>View the <A href=\"$gene-$aa\">latest version";
    else
	$history_box .= "<LI>View <A href=\"$gene-$aa\">this version without highlighted changes";
    $history_box .= "</A>";
    if (getCurrentUser())
	$history_box .= " and enable editing features";
    $history_box .= "</LI>\n";

    $history_box .= "</UL></P></DIV>";
    $gDisableEditing = TRUE;
}


$report =& evidence_get_report (($history_box && $max_edit_id) ? 0 + $max_edit_id : "latest",
				$variant_id);
$row0 =& $report[0];

$aa_long = "$row0[variant_aa_from]$row0[variant_aa_pos]$row0[variant_aa_to]";
$aa_short = aa_short_form($aa_long);


$gOut["title"] = "$row0[variant_gene] $aa_short - GET-Evidence";

$gOut["content"] = "
<h1>$row0[variant_gene] $aa_short</h1>

<p>($row0[variant_gene] $aa_long)</p>

<!-- $variant_id -->

"
    .seealso_related($row0["variant_gene"], $row0["variant_aa_pos"], $variant_id)
    .$history_box;


$gOut["content"] .= evidence_render_row ($row0);

$firstrow = true;
$sections = array ("Publications" => "",
		   "Genomes" => "");
foreach ($report as $row)
{
  if ($row["article_pmid"] > 0)
    $section = "Publications";
  else if ($row["genome_id"] > 0)
    $section = "Genomes";
  $sections[$section] .= evidence_render_row ($row);
}

$newPublicationForm = '';
if (getCurrentUser("oid"))
   $newPublicationForm = '
<div id="article_new"></div>
<P>PMID&nbsp;<input type="text" id="article_pmid" size=12 />&nbsp;<button onclick="evidence_add_article('.$variant_id.', $(\'article_pmid\').value); $(\'article_pmid\').value=\'\'; return false;">Add</button></P>
';

$html = "";
$html .= "<H2>Publications<BR />&nbsp;</H2>\n<DIV id=\"publications\">"
  . $sections["Publications"]
  . "</DIV>"
  . $newPublicationForm;

if ($sections["Genomes"] != "")
  $html .= "<H2>Genomes<BR />&nbsp;</H2>\n<DIV id=\"genomes\">"
    . $sections["Genomes"]
    . "</DIV>";

$external_refs = theDb()->getAll ("SELECT * FROM variant_external WHERE variant_id=? ORDER BY tag", array ($variant_id));
if ($external_refs) {
    $html .= "<H2>Other external references<BR />&nbsp;</H2><DIV id=\"external\">\n";
    $lasttag = FALSE;
    foreach ($external_refs as $r) {
	$content = $r["content"];
	if ($r["tag"] != $lasttag) {
	    if ($lasttag !== FALSE)
		$html .= "</UL>\n";
	}
	if (ereg ("^<", $content)) {
	    $html .= $content;
	    $lasttag = FALSE;
	    continue;
	}
	if ($r["tag"] != $lasttag)
	    $html .= "<UL><STRONG>" . htmlspecialchars ($r["tag"]) . "</STRONG>";
	$content = htmlspecialchars ($content);
	$html .= "<LI>" . $content;
	if ($r["url"]) {
	    $url_abbrev = $r["url"];
	    if (strlen ($url_abbrev) > 64)
		$url_abbrev = ereg_replace ('\?.*', '', $url_abbrev);
	    $html .= " <A href=\"" . htmlspecialchars ($r["url"]) . "\">" . htmlspecialchars ($url_abbrev) . "</A>";
	}
	$html . "</LI>";
	$lasttag = $r["tag"];
    }
    if ($lasttag !== FALSE)
	$html .= "</UL>\n";
    $html .= "</DIV>\n";
}

$html .= "<H2>Edit history<BR />&nbsp;</H2>\n<DIV id=\"edit_history\">";
$html .= evidence_render_history ($variant_id);
$html .= "</DIV>";

$gOut["content"] .= $html;

go();

?>
