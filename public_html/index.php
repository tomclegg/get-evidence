<?php

include "lib/setup.php";
$gOut["title"] = "Evidence Base";

if (ereg ("^[0-9]+$", $_GET["q"]))
  $variant_id = $_GET["q"];
else if (ereg ("^(chr[0-9XYM])-([0-9]+)-([ACGT]+)", $_GET["q"], $regs))
  $variant_id = evidence_get_variant_id ($regs[1], $regs[2], $regs[3]);
if (!$variant_id)
  {
    if (!$_GET["q"])
      {
	$variant_id = theDb()->getOne ("SELECT MAX(variant_id) FROM snap_release");
	if (!$variant_id) $variant_id = 1;
	header ("Location: /?q=$variant_id");
	exit;
      }
    if (!$variant_id)
      {
	$gOut["content"] = '<h1>Not found</h1><p>No results were found for your query: <cite>'.htmlspecialchars($_GET["q"]).'</cite>';
	go();
	exit;
      }
  }
$report =& evidence_get_report ("latest", $variant_id);
$row0 =& $report[0];

$gOut["title"] = "$row0[variant_gene] $row0[variant_aa_from]$row0[variant_aa_pos]$row0[variant_aa_to] - Evidence Base";

$gOut["content"] = "
<h1>$row0[variant_gene] $row0[variant_aa_from]$row0[variant_aa_pos]$row0[variant_aa_to]</h1>

<p>$row0[summary_short]</p>

";

$html = "";
$outsection = false;
$firstrow = true;
foreach ($report as $row)
{
  if ($firstrow)
    {
      $firstrow = false;
      continue;
    }

  $id_prefix = "v_${variant_id}__p_$row[edit_id]__";

  if ($row["article_pmid"] > 0)
    {
      $section = "Publications";
      $item = "<A href=\"http://www.ncbi.nlm.nih.gov/pubmed/$row[article_pmid]\">PMID $row[article_pmid]</A><BR />";
    }
  if ($row["genome_id"] > 0)
    {
      $section = "Genomes";
      $item = "Genome $row[genome_id]";
    }
  if ($outsection != $section)
    {
      if ($outsection !== false)
	{
	  $html .= "</UL>\n";
	}
      $html .= "<UL>$section:\n";
      $outsection = $section;
    }
  $item .= editable ("${id_prefix}f_summary_short__70x5__textile",
		    $row[summary_short]);
  if ($row[summary_long])
    $item .= editable ("${id_prefix}f_summary_long__70x5__textile",
		       $row[summary_long]);
  $html .= "<li>$item</li>\n";
}
if ($outsection !== false)
  $html .= "</ul>\n";
$gOut["content"] .= $html;

go();

?>
