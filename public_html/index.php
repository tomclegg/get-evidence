<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base";

$_GET["q"] = trim ($_GET["q"], "\" \t\n\r\0");

if (ereg ("^[0-9]+$", $_GET["q"]))
  $variant_id = $_GET["q"];
else if (ereg ("^([A-Za-z0-9_]+)[- ]([A-Za-z]+[0-9]+[A-Za-z\\*]+)$", $_GET["q"], $regs) &&
	 aa_sane ($aa = $regs[2])) {
  $gene = strtoupper($regs[1]);
  $variant_id = evidence_get_variant_id ("$gene $aa");
  if (!$variant_id) {
    $aa_long = aa_long_form ($aa);
    $aa_short = aa_short_form ($aa_long);
    header ("HTTP/1.1 404 Not found");
    $gOut["title"] = "$gene $aa_short";
    $gOut["content_textile"] = <<<EOF
h1. $gene $aa_short

($gene $aa_long)

There is no Evidence Base entry for this variant.

EOF
;
    if (getCurrentUser())
      $gOut["content_textile"] .= <<<EOF
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
$report =& evidence_get_report ("latest", $variant_id);
$row0 =& $report[0];

$aa_long = "$row0[variant_aa_from]$row0[variant_aa_pos]$row0[variant_aa_to]";
$aa_short = aa_short_form($aa_long);

$gOut["title"] = "$row0[variant_gene] $aa_short - Evidence Base";

$gOut["content"] = "
<h1>$row0[variant_gene] $aa_short</h1>

<p>($row0[variant_gene] $aa_long)</p>

";

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
	if ($r["tag"] != $lasttag) {
	    if ($lasttag !== FALSE)
		$html .= "</UL>\n";
	    $html .= "<UL><STRONG>" . htmlspecialchars ($r["tag"]) . "</STRONG>";
	}
	$html .= "<LI>" . htmlspecialchars ($r["content"]);
	if ($r["url"])
	    $html .= " <A href=\"" . htmlspecialchars ($r["url"]) . "\">" . htmlspecialchars ($r["url"]) . "</A>";
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
