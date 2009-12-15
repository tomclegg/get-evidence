<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base";

$_GET["q"] = trim ($_GET["q"], "\" \t\n\r\0");

if (ereg ("^[0-9]+$", $_GET["q"]))
  $variant_id = $_GET["q"];
else if (ereg ("^([A-Za-z0-9_]+)[- ]([A-Za-z]+[0-9]+[A-Za-z\\*]+)$", $_GET["q"], $regs) &&
	 aa_sane ($aa = $regs[2])) {
  $gene = $regs[1];
  $variant_id = evidence_get_variant_id ("$gene $aa");
  if (!$variant_id) {
    $aa_long = aa_long_form ($aa);
    $aa_short = aa_short_form ($aa_long);
    header ("HTTP/1.1 404 Not found");
    $gOut["title"] = "$gene $aa_short";
    $gOut["content"] = "<h1>$gene $aa_short</h1>\n<p>($gene $aa_long)</p><p>Not found.</p>\n";
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

<p>$row0[summary_short]</p>

";

$firstrow = true;
$sections = array ("Publications" => "",
		   "Genomes" => "");
foreach ($report as $row)
{
  if ($firstrow)
    {
      $firstrow = false;
      continue;
    }

  $id_prefix = "v_${variant_id}__a_$row[article_pmid]__g_$row[genome_id]__p_$row[edit_id]__";

  if ($row["article_pmid"] > 0)
    {
      $section = "Publications";
      $item = "<A href=\"http://www.ncbi.nlm.nih.gov/pubmed/$row[article_pmid]\">PMID $row[article_pmid]</A><BR />";
    }
  else if ($row["genome_id"] > 0)
    {
      $section = "Genomes";
      $item = "Genome $row[genome_id]";
    }
  $item .= editable ("${id_prefix}f_summary_short__70x5__textile",
		    $row[summary_short]);
  if ($row[summary_long])
    $item .= editable ("${id_prefix}f_summary_long__70x5__textile",
		       $row[summary_long]);
  $sections[$section] .= "<li>$item</li>\n";
}

$newPublicationForm = '
<div id="article_new"></div>
<li>PMID&nbsp;<input type="text" id="article_pmid" size=12 />&nbsp;<button onclick="evidence_add_article('.$variant_id.', $(\'article_pmid\').value); $(\'article_pmid\').value=\'\'; return false;">Add</button></li>
';

$html = "";
$html .= "<UL>$section\n<div id=\"publications\">"
  . $sections["Publications"]
  . "</div>"
  . $newPublicationForm
  . "</UL>\n";
// $html .= "<UL>$section\n" . $sections["Genomes"] . "</UL>\n";

$gOut["content"] .= $html;

go();

?>
