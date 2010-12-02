<?php
  ;

// Copyright 2009, 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

include "lib/setup.php";
require_once ("lib/blosum.php");
require_once ("lib/quality_eval.php");
$gOut["title"] = "GET-Evidence";


function seealso_related ($gene, $aa_pos, $skip_variant_id)
{
    $related_variants = theDb()->getAll ("SELECT v.variant_gene gene, CONCAT(v.variant_aa_from,v.variant_aa_pos,v.variant_aa_to) aa_long, CONCAT(v.variant_aa_del,v.variant_aa_pos,v.variant_aa_ins) aa_indel_short FROM variants v WHERE v.variant_gene=? AND v.variant_aa_pos=? AND v.variant_id <> ?", array ($gene, $aa_pos, $skip_variant_id));
    $seealso = "";
    foreach ($related_variants as $x) {
      if (!($aa_long = $x["aa_long"]))
	$aa_long = aa_long_form ($x["aa_indel_short"]);
      $aa_short = aa_short_form ($aa_long);
      $seealso .= "<LI>See also: <A href=\"".$x["gene"]."-".$aa_short."\">".$x["gene"]." ".$aa_short."</A></LI>\n";
    }
    if ($seealso)
	$seealso = "<DIV id=\"seealso\"><UL>$seealso</UL></DIV>\n";
    return $seealso;
}


$_GET["q"] = trim ($_GET["q"], "\" \t\n\r\0");

if ($x = preg_replace ('{\b(\d+)(?:_(\d+))?del([A-Z]+)ins([A-Z]+[a-z]*)$}', '$3$1$4', $_GET["q"]))
  $_GET["q"] = $x;

if (ereg ("^[0-9]+$", $_GET["q"]))
  $variant_id = $_GET["q"];
else if (ereg ("^(rs[0-9]+)(;([0-9]+))?$", $_GET["q"], $regs)) {
  $variant_id = evidence_get_variant_id ($regs[1]);
  $variant_name = evidence_get_variant_name ($variant_id, "-", true);
  if ($regs[1] != $variant_name) {
    header ("Location: $variant_name");
    exit;
  }
  $max_edit_id = $regs[3];
}
else if (preg_match ("{^([-A-Za-z0-9_]+)[- \t\n]+([A-Za-z]+[0-9]+[A-Za-z\\*]+)(;([0-9]+))?\$}", $_GET["q"], $regs) &&
	 aa_sane ($regs[2])) {
  $aa = $regs[2];
  $aa_long = aa_long_form ($aa);
  $aa_short = aa_short_form ($aa_long);
  $gene = strtoupper($regs[1]);
  $max_edit_id = count($regs) >= 4 ? $regs[4] : false;
  $variant_id = evidence_get_variant_id ($variant_name = "$gene $aa");
}
else if (preg_match ("{^([-A-Za-z0-9_]+)[- \t\n]+([A-Z]+)([0-9]+)([A-Za-z\\*]+)(;([0-9]+))?\$}", $_GET["q"], $regs) &&
	 aa_indel_sane (($aa_pos = $regs[3]),
			($aa_del = $regs[2]),
			($aa_ins = $regs[4]))) {
  $aa = "$aa_del$aa_pos$aa_ins";
  $aa_short = aa_short_form ($aa);
  $aa_long = aa_indel_long_form ($aa_pos, $aa_del, $aa_ins);
  $gene = strtoupper($regs[1]);
  $max_edit_id = $regs[6];
  $variant_name = "$gene $aa_short";
  $variant_id = evidence_get_variant_id ($gene, $aa_pos, $aa_del, $aa_ins);
}


if (!$variant_id && $aa) {
  header ("HTTP/1.1 404 Not found");
  $gOut["title"] = "$gene $aa_short - GET-Evidence";
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

<BUTTON onclick="return evidence_add_variant('$gene','$aa_short');">Create new entry</BUTTON>
EOF
;
  go();
  exit;
}


if (!$variant_id)
  {
    if (!$_GET["q"])
      {
	header ("Location: /about");
	exit;
      }
    else
      {
	$rows =& theDb()->getAll("SELECT * FROM variants WHERE variant_gene LIKE ? ORDER BY variant_gene, variant_aa_pos, variant_aa_to LIMIT 2",
			     array($_GET["q"]."%"));
	if (count($rows) == 0)
	  {
	    header ("HTTP/1.1 404 Not found");
	    $gOut["content"] = '<h1>Not found</h1><p>No results were found for your query: <cite>'.htmlspecialchars($_GET["q"]).'</cite></p>';
	    go();
	  }
	else if (count($rows) == 1)
	  {
	    header ("Location: "
		    .urlencode (evidence_get_variant_name ($rows[0], "-")));
	  }
	else
	  {
	    header ("Location: report?type=search&q=".urlencode ($_GET["q"]));
	  }
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
<DIV class=\"redalert\">
<P>You are viewing ";
    if ($next_version)
	$history_box .= "an <STRONG>old version</STRONG> of this page that was saved on <STRONG>$version_date</STRONG>";
    else
	$history_box .= "the latest version of this page, saved on <STRONG>$version_date</STRONG>";
    $history_box .= " by <A href=\"edits?oid=".urlencode($contributor["oid"])."\">".htmlspecialchars($contributor["fullname"])."</A>.</P><UL>";

    $variant_name = evidence_get_variant_name ($variant_id, "-");

    if ($previous_version)
	$history_box .= "<LI>View the <A href=\"$variant_name;$previous_version\">previous version</A>";

    if ($next_version)
	$history_box .= "<LI>View the <A href=\"$variant_name;$next_version\">next version</A>";

    if ($next_version)
	$history_box .= "<LI>View the <A href=\"$variant_name\">latest version";
    else
	$history_box .= "<LI>View <A href=\"$variant_name\">this version without highlighted changes";
    $history_box .= "</A>";
    if (getCurrentUser())
	$history_box .= " and enable editing features";
    $history_box .= "</LI>\n";

    $history_box .= "</UL></DIV>";
    $gDisableEditing = TRUE;
}


$report =& evidence_get_report (($history_box && $max_edit_id) ? 0 + $max_edit_id : "latest",
				$variant_id);
$row0 =& $report[0];

$variant_name_long = evidence_get_variant_name ($row0, " ", false);
$variant_name_short = evidence_get_variant_name ($row0, " ", true);


$gOut["title"] = "$variant_name_short - GET-Evidence";

if (!quality_eval_suff($row0["variant_quality"],$row0["variant_impact"])) {
    $gOut["content"] = "<DIV class=\"redalert\">"
            . "<p>Note: <STRONG>This variant has not been sufficiently evaluated</STRONG> by a GET-Evidence editor.</p>"
            . "<p>To be considered sufficiently evaluated a variant must have both \"variant evidence\" and \"clinical importance\" scores filled in.</p>"
            . "<p>Please help improve GET-Evidence by <A href=\"guide_editing\">evaluating evidence</A> for this variant!</p>"
            . "</DIV>\n";
} else {
    $gOut["content"] = "";
}

$gOut["content"] .= "<h1>$variant_name_short</h1>\n<!-- $variant_id -->\n";
if ($variant_name_long != $variant_name_short)
    $gOut["content"] .= "<p>($variant_name_long)</p>\n";

$gOut["content"] .= seealso_related($row0["variant_gene"], $row0["variant_aa_pos"], $variant_id);
$gOut["content"] .= $history_box;


$renderer = new evidence_row_renderer;
foreach ($report as $row) {
    if ($row["article_pmid"] > 0 || $row["genome_id"] > 0)
	break;
    $renderer->render_row ($row);
}
$gOut["content"] .= $renderer->html();


$gOut["content"] .= evidence_render_oddsratio_summary_table ($report);


$rsid_seen = array();
$allele_frequency = array();

$firstrow = true;
$sections = array ("Publications" => new evidence_row_renderer,
		   "Genomes" => new evidence_row_renderer);
foreach ($report as $row)
{
  $section = FALSE;
  if ($row["article_pmid"] > 0)
    $section = "Publications";
  else if ($row["genome_id"] > 0)
    $section = "Genomes";
  if ($section)
    $sections[$section]->render_row ($row);
  if ($row["rsid"])
    $rsid_seen[$row["rsid"]] = 1;
  if ($row["chr"])
    $allele_frequency[$row["chr"]." ".$row["chr_pos"]." ".$row["allele"]] = 1;
}


if ($morelocs = theDb()->getAll ("SELECT CONCAT(chr,' ',chr_pos,' ',allele) x
	 FROM variant_locations
	 WHERE variant_id=?", array ($variant_id))) {
    foreach ($morelocs as $chr_pos_allele) {
	$allele_frequency[$chr_pos_allele["x"]] = 1;
    }
}


$html = "";


$gotsome = 0;
$html .= "<H2>Allele frequency</H2>\n<DIV id=\"allele_frequency\">\n";
$html .= "<UL>\n";
foreach ($allele_frequency as $chr_pos_allele => $f) {
  list ($chr, $pos, $allele) = explode (" ", $chr_pos_allele);
  $frows = theDb()->getAll ("SELECT * FROM allele_frequency WHERE chr=? AND chr_pos=? AND allele=?", array ($chr, $pos, $allele));
  foreach ($frows as $frow) {
      if (!$frow["denom"])
	continue;
      $num = $frow["num"];
      $denom = $frow["denom"];
      $f = sprintf ("%.1f%%", 100 * $num / $denom);
      $tag = $frow["dbtag"];
      if ($tag == "1000g") $tag = "1000 Genomes";
      if ($tag == "hapmap") $tag = "HapMap";
      $html .= "<LI>$allele @ $chr:$pos: $f ($num/$denom) in $tag</LI>\n";
      $gotsome = 1;
  }
}
if (isset ($row0["variant_f"])) {
    $html .= "<LI>Overall frequency computed as "
	. sprintf ("%.1f%% (%d/%d)",
		   100 * $row0["variant_f"],
		   $row0["variant_f_num"],
		   $row0["variant_f_denom"])
	. "</LI>\n";
    $gotsome = 1;
}
if (!$gotsome) {
    $html .= "<LI>None available.</LI>\n";
}
$html .= "</UL>\n";
$html .= "</DIV>\n";


$newPublicationForm = '';
if (getCurrentUser("oid"))
   $newPublicationForm = '
<div id="article_new"></div>
<P>PMID&nbsp;<input type="text" id="article_pmid" size=12 />&nbsp;<button onclick="evidence_add_article('.$variant_id.', $(\'article_pmid\').value); $(\'article_pmid\').value=\'\'; return false;">Add</button></P>
';

$html .= "<H2>Publications<BR />&nbsp;</H2>\n<DIV id=\"publications\">"
  . $sections["Publications"]->html()
  . "</DIV>"
  . $newPublicationForm;

if ($sections["Genomes"] != "")
  $html .= "<H2>Genomes<BR />&nbsp;</H2>\n<DIV id=\"genomes\">"
    . $sections["Genomes"]->html()
    . "</DIV>";


$external_refs = theDb()->getAll ("SELECT * FROM variant_external WHERE variant_id=? ORDER BY tag", array ($variant_id));
if (!$external_refs) $external_refs = array();

$gt = theDb()->getAll ("SELECT DISTINCT disease_name
 FROM gene_disease gd
 LEFT JOIN diseases d ON gd.disease_id = d.disease_id
 WHERE gene=?
 OR gene IN (SELECT aka FROM gene_canonical_name WHERE official=?)",
		       array ($row0["variant_gene"], $row0["variant_gene"]));
if (sizeof($gt)) {
    $ref["tag"] = "GeneTests";
    $ref["content"] = "GeneTests records for the {$row0[variant_gene]} gene";
    $ref["url"] = "http://www.ncbi.nlm.nih.gov/sites/GeneTests/lab/gene/".urlencode($row0["variant_gene"]);
    foreach ($gt as $x) {
	$ref["content"] .= "\n".$x["disease_name"];
    }
    array_unshift ($external_refs, $ref);
}


if (ereg ("^rs([0-9]+)$", $variant_name_short, $regs))
  $rsid_seen[$regs[1]] = 1;

foreach ($rsid_seen as $rsid => $dummy) {
  array_unshift ($external_refs,
		 array ("tag" => "dbSNP",
			"content" => "rs$rsid",
			"url" => "http://www.ncbi.nlm.nih.gov/projects/SNP/snp_ref.cgi?searchType=adhoc_search&type=rs&rs=rs$rsid"));
}

if (count($external_refs)) {
    $html .= "<H2>Other external references<BR />&nbsp;</H2><DIV id=\"external\">\n";
    $lasttag = FALSE;
    foreach ($external_refs as $r) {
	$content = $r["content"];
	if ($r["tag"] != $lasttag) {
	    if ($lasttag !== FALSE)
		$html .= "</UL>\n";
	}
	if (ereg ("^<", $content)) {
	    $html .= evidence_add_vote_tags ($variant_id, $content);
	    $lasttag = FALSE;
	    continue;
	}
	if ($r["tag"] != $lasttag)
	    $html .= "<UL><STRONG>" . htmlspecialchars ($r["tag"]) . "</STRONG>";
	if (!ereg ("[a-z]", $content))
	  $content = ucfirst (strtolower ($content));
	$content = htmlspecialchars ($content);
	$html .= "<LI>";
	if ($r["url"]) {
	    $url_abbrev = ereg_replace ("^https?://", "", $r["url"]);
	    if (strlen ($url_abbrev) > 64)
		$url_abbrev = ereg_replace ('\?.*', '', $url_abbrev);
	    $title_and_more = explode ("\n", $content, 2);
	    $html .= "<A href=\"" . htmlspecialchars ($r["url"]) . "\">" . $title_and_more[0] . "</A>";
	    if (count($title_and_more) > 1)
	      $html .= "<BR />".nl2br($title_and_more[1]);
	    $html .= "<BR /><SPAN class=\"searchurl\">" . htmlspecialchars ($url_abbrev) . "</SPAN>";
	} else
	  $html .= $content;
	$html . "</LI>";
	$lasttag = $r["tag"];
    }
    if ($lasttag !== FALSE)
	$html .= "</UL>\n";
    $html .= "</DIV>\n";
}

$html .= "<H2>Other <I>in silico</I> analyses<BR />&nbsp;</H2>\n<DIV id=\"in_silico\">\n<UL>\n";
if ($aa) {
  $html .= "<LI>NBLOSUM100 score = <STRONG>"
      .ereg_replace ("-", "&ndash;",
		     $row0["nblosum100"])
      ."</STRONG></LI>\n";
}
$autoscore_html = $row0["autoscore"];
if ($autoscore_html > 0) {
  $autoscore_html = "<SPAN onmouseover=\"Tip('".htmlspecialchars($row0["autoscore_flags"])."',BALLOON,true,FIX,[this,-18,0],FOLLOWMOUSE,false,ABOVE,true,WIDTH,-400);\" onmouseout=\"UnTip();\">".$autoscore_html."</SPAN>";
}
$html .= "<LI>GET-Evidence autoscore = <STRONG>" . $autoscore_html . "</STRONG>";
$html .= "</LI>\n";
$html .= "</UL>\n";
$html .= "</DIV>";

$html .= "<H2>Edit history<BR />&nbsp;</H2>\n<DIV id=\"edit_history\">";
$html .= evidence_render_history ($variant_id);
$html .= "</DIV>";

$gOut["content"] .= $html;

// $gOut["content"] .= "<!--\n" . ereg_replace ("-->", "\\-\\-\\>", json_encode ($report)) . "-->\n";
$gOut["content"] .= "<!--\n" . ereg_replace ("-->", "\\-\\-\\>", json_encode (evidence_get_assoc_flat_summary ("latest", $variant_id))) . "-->\n";

$gOut["content"] = "<form id=\"mainform\" action=\"save.php\" method=\"POST\">" . $gOut["content"] . "</form>";

go();

?>
