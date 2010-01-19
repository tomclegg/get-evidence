<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

require_once ("lib/article.php");
require_once ("lib/hapmap.php");

function evidence_create_tables ()
{
  theDb()->query ("
  CREATE TABLE IF NOT EXISTS variants (
  variant_id SERIAL,
  variant_gene VARCHAR(16),
  variant_aa_pos INT UNSIGNED,
  variant_aa_from ENUM('Ala','Arg','Asn','Asp','Cys','Gln','Glu','Gly','His','Ile','Leu','Lys','Met','Phe','Pro','Ser','Thr','Trp','Tyr','Val','Stop'),
  variant_aa_to ENUM('Ala','Arg','Asn','Asp','Cys','Gln','Glu','Gly','His','Ile','Leu','Lys','Met','Phe','Pro','Ser','Thr','Trp','Tyr','Val','Stop'),
  UNIQUE (variant_gene, variant_aa_pos, variant_aa_from, variant_aa_to)
)");
  theDb()->query ("
  CREATE TABLE IF NOT EXISTS edits (
  edit_id SERIAL,
  variant_id BIGINT NOT NULL REFERENCES variants.variant_id,
  previous_edit_id BIGINT,
  is_draft TINYINT NOT NULL DEFAULT 1,
  is_delete TINYINT NOT NULL DEFAULT 0,
  edit_oid VARCHAR(255),
  edit_timestamp DATETIME,
  signoff_oid VARCHAR(255),
  signoff_timestamp DATETIME,
  variant_impact ENUM('pathogenic','putative pathogenic','unknown','putative benign','benign') NOT NULL DEFAULT 'unknown',
  variant_dominance ENUM('unknown','dominant','recessive') NOT NULL DEFAULT 'unknown',
  summary_short TEXT,
  summary_long TEXT,
  talk_text TEXT,
  article_pmid INT UNSIGNED NOT NULL,
  genome_id BIGINT UNSIGNED NOT NULL,
  disease_id BIGINT UNSIGNED NOT NULL,
  
  INDEX (variant_id,edit_timestamp),
  INDEX (edit_oid, edit_timestamp),
  INDEX (previous_edit_id, edit_oid),
  INDEX (variant_id, article_pmid, genome_id, edit_timestamp),
  INDEX (is_draft, edit_timestamp)
)");
  theDb()->query ("ALTER TABLE edits ADD disease_id BIGINT UNSIGNED NOT NULL AFTER genome_id");

  foreach (array ("snap_latest", "snap_release") as $t) {
      theDb()->query ("CREATE TABLE IF NOT EXISTS `$t` LIKE edits");
      theDb()->query ("ALTER TABLE `$t` ADD disease_id BIGINT UNSIGNED NOT NULL");
      theDb()->query ("ALTER TABLE `$t` ADD UNIQUE `snapkey` (variant_id, article_pmid, genome_id, disease_id)");
      theDb()->query ("ALTER TABLE `$t` DROP INDEX `snap_key`");
  }

  theDb()->query ("CREATE TABLE IF NOT EXISTS genomes (
  genome_id SERIAL,
  global_human_id VARCHAR(16) NOT NULL,
  name VARCHAR(128),
  UNIQUE(global_human_id))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS datasets (
  dataset_id VARCHAR(16) NOT NULL,
  genome_id BIGINT UNSIGNED NOT NULL,
  dataset_url VARCHAR(255),
  sex ENUM('M','F'),
  INDEX(genome_id,dataset_id),
  UNIQUE(dataset_id))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS diseases (
  disease_id SERIAL,
  disease_name VARCHAR(255) NOT NULL,
  UNIQUE disease_name_unique (disease_name))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_occurs (
  variant_id BIGINT UNSIGNED NOT NULL,
  rsid BIGINT UNSIGNED NOT NULL,
  dataset_id VARCHAR(16) NOT NULL,
  UNIQUE(variant_id,dataset_id,rsid),
  INDEX `rsid` (`rsid`)
  )");
  theDb()->query ("ALTER TABLE variant_occurs
  ADD zygosity ENUM('heterozygous','homozygous')
  ");
  theDb()->query ("ALTER TABLE variant_occurs
  ADD chr CHAR(6),
  ADD chr_pos INT UNSIGNED,
  ADD allele CHAR(1),
  ADD INDEX chr_pos_allele (chr,chr_pos,allele)
  ");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_locations (
  chr CHAR(6) NOT NULL,
  chr_pos INT UNSIGNED NOT NULL,
  allele CHAR(1) NOT NULL,
  rsid BIGINT UNSIGNED,
  gene_aa VARCHAR(32),
  INDEX chr_pos_allele (chr, chr_pos, allele),
  INDEX (rsid))");
  theDb()->query ("ALTER TABLE variant_locations
  ADD variant_id BIGINT UNSIGNED,
  ADD INDEX(variant_id),
  ADD UNIQUE(chr,chr_pos,allele,gene_aa)");

  theDb()->query ("CREATE TABLE IF NOT EXISTS taf (
  chr CHAR(6) NOT NULL,
  chr_pos INT UNSIGNED NOT NULL,
  allele CHAR(1) NOT NULL,
  taf TEXT,
  UNIQUE(chr, chr_pos, allele)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_external (
  variant_id BIGINT UNSIGNED NOT NULL,
  tag CHAR(16),
  content TEXT,
  url VARCHAR(255),
  updated DATETIME,
  INDEX(variant_id,tag),
  INDEX(tag,variant_id)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS genetests_genes (
  gene CHAR(16) NOT NULL PRIMARY KEY
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS allele_frequency (
  chr CHAR(6),
  chr_pos INT UNSIGNED,
  allele CHAR(1),
  dbtag CHAR(6),
  num INT UNSIGNED,
  denom INT UNSIGNED,
  UNIQUE(chr,chr_pos,allele,dbtag)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_frequency (
  variant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  num INT UNSIGNED,
  denom INT UNSIGNED,
  f FLOAT,
  INDEX(f))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS dbsnp (
  id INT UNSIGNED NOT NULL PRIMARY KEY,
  chr CHAR(7) NOT NULL,
  chr_pos INT UNSIGNED NOT NULL,
  orient TINYINT UNSIGNED NOT NULL,
  INDEX chr_pos_orient (chr,chr_pos,orient)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS gene_disease (
  gene VARCHAR(32) NOT NULL,
  disease_id BIGINT UNSIGNED NOT NULL,
  dbtag VARCHAR(12) NOT NULL,
  UNIQUE `gene_disease_dbtag` (gene,disease_id,dbtag),
  INDEX `disease_index` (disease_id,gene,dbtag),
  INDEX `dbtag_index` (dbtag)
  )");

  theDb()->query ("CREATE TABLE IF NOT EXISTS gene_canonical_name (
  aka VARCHAR(32) NOT NULL,
  official VARCHAR(32) NOT NULL,
  UNIQUE aka_official (aka,official))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_disease (
  variant_id BIGINT UNSIGNED NOT NULL,
  disease_id BIGINT UNSIGNED NOT NULL,
  dbtag CHAR(6) NOT NULL,
  UNIQUE `variant_disease_dbtag` (variant_id,disease_id,dbtag),
  INDEX `disease_index` (disease_id,variant_id,dbtag),
  INDEX `dbtag_index` (dbtag)
  )");
}

function evidence_get_genome_id ($global_human_id)
{
  $genome_id = theDb()->getOne ("SELECT genome_id FROM genomes WHERE global_human_id=?",
				array ($global_human_id));
  if ($genome_id > 0)
    return $genome_id;
  $q = theDb()->query ("INSERT INTO genomes SET global_human_id=?",
		       array ($global_human_id));
  if (theDb()->isError($q)) {
    $genome_id = theDb()->getOne ("SELECT genome_id FROM genomes WHERE global_human_id=?",
				  array ($global_human_id));
    if ($genome_id > 0)
      return $genome_id;
    die ("evidence_get_genome_id: DB error: " . $q->getMessage() . " -- lookup failed");
  }
  else
    return theDb()->query ("SELECT LAST_INSERT_ID()");
}

function evidence_get_variant_id ($gene,
				  $aa_pos=false,
				  $aa_from=false,
				  $aa_to=false,
				  $create_flag=false)
{
  if ($aa_pos === false) {
    if (ereg ("^([-A-Za-z0-9_]+)[- ]+([A-Za-z]+)([0-9]+)([A-Za-z\\*]+)$", $gene, $regs)) {
      $gene = $regs[1];
      $aa_from = $regs[2];
      $aa_pos = $regs[3];
      $aa_to = $regs[4];
    }
    else
      return null;
  }
  else if (!aa_sane("$aa_from$aa_pos$aa_to"))
    return null;

  $gene = strtoupper ($gene);

  $aa_from = aa_long_form ($aa_from);
  $aa_to = aa_long_form ($aa_to);

  if ($create_flag) {
    $q = theDb()->query ("INSERT IGNORE INTO variants
			SET variant_gene=?,
			variant_aa_pos=?,
			variant_aa_from=?,
			variant_aa_to=?",
		    array ($gene, $aa_pos, $aa_from, $aa_to));
    if (!theDb()->isError($q) &&
	theDb()->affectedRows())
      return theDb()->getOne ("SELECT LAST_INSERT_ID()");
  }
  return theDb()->getOne ("SELECT variant_id FROM variants
				WHERE variant_gene=?
				AND variant_aa_pos=?
				AND variant_aa_from=?
				AND variant_aa_to=?",
			  array ($gene, $aa_pos, $aa_from, $aa_to));
}

function evidence_approve ($edit_id, $signoff_oid)
{
  theDb()->query ("UPDATE edits SET signoff_oid=?, signoff_timestamp=now() WHERE edit_id=? AND signoff_oid IS NULL", array ($signoff_oid, $edit_id));
  if (theDB()->affectedRows() != 1)
    {
      if (theDb()->getOne ("SELECT 1 FROM edits WHERE edit_id=?", $edit_id))
	{
	  // TODO: handle warning ("already approved")
	}
      else
	{
	  // TODO: handle warning ("no such entry")
	}
    }
}

function evidence_edit_id_generate ($previous_edit_id=null, $variant_id=null)
{
  if ($previous_edit_id)
    $variant_id = theDb()->getOne ("SELECT variant_id FROM edits WHERE edit_id=?",
				   $previous_edit_id);
  else if (!$variant_id)
    die ("evidence_edit_id_generate(): need either previous edit id or variant id");

  theDb()->query ("INSERT INTO edits (variant_id, edit_oid, previous_edit_id, is_draft)
		   VALUES (?, ?, ?, 1)",
		  array ($variant_id,
			 $_SESSION["user"]["oid"],
			 $previous_edit_id));
  $new_edit_id = theDb()->getOne ("SELECT LAST_INSERT_ID()");
  return $new_edit_id;
}

function evidence_save_draft ($edit_id, $newrow)
{
  $stmt = "UPDATE edits SET edit_timestamp=now()";
  $params = array();

  foreach (explode (" ", "variant_impact variant_dominance summary_short summary_long talk_text article_pmid") as $field)
    {
      $stmt .= ", $field=?";
      $params[] = $newrow[$field];
    }
  $stmt .= " WHERE edit_id=? AND edit_oid=? AND is_draft=1";
  $params[] = $edit_id;
  $params[] = $_SESSION["user"]["oid"];
  
  if (!theDb()->query ($stmt, $params))
    {
      // TODO: report error
      die ("evidence_save_draft ($edit_id) failed: query failed");
      return false;
    }
  if (theDb()->affectedRows() != 1)
    {
      // TODO: report error
      die ("evidence_save_draft ($edit_id) failed: row not updated");
      return false;
    }
  return true;
}

function evidence_submit ($edit_id)
{
  // Push this edit to the "latest" snapshot (and un-mark its "draft"
  // flag so it shows up in edit history).

  // TODO: Check for conflicts (i.e. "snap_latest" version !=
  // previous_edit_id, or snap_latest has same variant+article+genome
  // key but previous_edit_id is null) -- whether caused by a race, or
  // by editing/saving an old revision) and force the user to
  // explicitly lose the intervening changes.

  theDb()->query ("UPDATE edits SET is_draft=0, edit_timestamp=NOW(), article_pmid=if(article_pmid is null,0,article_pmid), genome_id=if(genome_id is null,0,genome_id) WHERE edit_id=? AND edit_oid=?",
		  array($edit_id, getCurrentUser("oid")));
  theDb()->query ("REPLACE INTO snap_latest SELECT * FROM edits WHERE edit_id=? AND edit_oid=?",
		  array($edit_id, getCurrentUser("oid")));
  theDb()->query ("DELETE FROM snap_latest WHERE is_delete=1");
}

function evidence_signoff ($edit_id)
{
  // Push this edit to the "release" snapshot.

  if (!$_SESSION["user"]["is_admin"])
    {
      // TODO: proper error reporting
      die ("only admin can do this");
    }

  theDb()->query ("UPDATE edits SET signoff_oid=?, signoff_timestamp=NOW()
		   WHERE edit_id=? AND signoff_oid IS NULL",
		  array ($_SESSION["user"]["oid"], $edit_id));
  $latest_signedoff_edit =
    theDb()->getOne ("SELECT edit_id FROM edits
		      WHERE variant_id = (SELECT variant_id FROM edits WHERE edit_id=?)
		      ORDER BY edit_timestamp DESC LIMIT 1",
		     $edit_id);
  if ($latest_signedoff_edit != $edit_id)
    {
      // TODO: proper error reporting
      die ("A more recent edit ($latest_signedoff_edit) has already been signed off.");
    }
  theDb()->query ("REPLACE INTO snap_release SELECT * FROM edits WHERE edit_id=?",
		  $edit_id);
}

function evidence_get_report ($snap, $variant_id)
{
  $flag_edited_id = 0;
  if ($snap == "latest" || $snap == "release") {
    $table = "snap_$snap";
    $and_max_edit_id = "";
  }
  else if (ereg ("^[0-9]+$", $snap)) {
    $flag_edited_id = $snap;
    $table = "edits";
    $variant_id = 0 + $variant_id;
    $and_max_edit_id = "AND $table.edit_id IN (SELECT MAX(edits.edit_id) FROM edits WHERE variant_id=$variant_id AND edit_id<=$snap AND is_draft=0 GROUP BY article_pmid, genome_id) AND ($table.edit_id=$snap OR $table.is_delete=0)";
  }

  // Get all items relating to the given variant

  $v =& theDb()->getAll ("SELECT variants.*, $table.*, genomes.*, datasets.*, variant_occurs.*,
			variants.variant_id AS variant_id,
			$table.genome_id AS genome_id,
			variant_occurs.chr AS chr,
			variant_occurs.chr_pos AS chr_pos,
			variant_occurs.allele AS allele,
			variant_occurs.rsid AS rsid,
			vf.num AS variant_f_num,
			vf.denom AS variant_f_denom,
			vf.f AS variant_f,
			COUNT(datasets.dataset_id) AS dataset_count,
			MAX(zygosity) AS zygosity,
			MAX(dataset_url) AS dataset_url,
			MIN(dataset_url) AS dataset_url_2,
			$table.edit_id=? AS flag_edited_id
			FROM variants
			LEFT JOIN $table
				ON variants.variant_id = $table.variant_id
			LEFT JOIN genomes
				ON $table.genome_id > 0
				AND $table.genome_id = genomes.genome_id
			LEFT JOIN datasets
				ON datasets.genome_id = $table.genome_id
			LEFT JOIN variant_occurs
				ON $table.variant_id = variant_occurs.variant_id
				AND variant_occurs.dataset_id = datasets.dataset_id
			LEFT JOIN variant_frequency vf
				ON vf.variant_id=variants.variant_id
			WHERE variants.variant_id=?
				$and_max_edit_id
			GROUP BY
				$table.genome_id,
				$table.article_pmid
			ORDER BY
				$table.genome_id,
				$table.article_pmid,
				$table.edit_id DESC",
			 array ($flag_edited_id, $variant_id));
  if (theDb()->isError($v)) die ($v->getMessage());
  if (!theDb()->isError($v) && $v && $v[0])
    foreach (array ("article_pmid", "genome_id") as $x)
      if (!$v[0][$x]) $v[0][$x] = 0;
  return $v;
}

function evidence_get_latest_edit ($variant_id, $article_pmid, $genome_id,
				   $create_flag=false)
{
  if (!$variant_id) return null;
  $edit_id = theDb()->getOne
    ("SELECT MAX(edit_id) FROM edits
	WHERE variant_id=? AND article_pmid=? AND genome_id=?
	AND (is_draft=0 OR edit_oid=?)",
     array ($variant_id, $article_pmid, $genome_id, getCurrentUser("oid")));
  if (!$edit_id && $create_flag) {
    theDb()->query
      ("INSERT INTO edits
	SET edit_timestamp=NOW(), edit_oid=?, is_draft=1,
	variant_id=?, article_pmid=?, genome_id=?",
       array (getCurrentUser("oid"), $variant_id, $article_pmid, $genome_id));
    $edit_id = theDb()->getOne ("SELECT LAST_INSERT_ID()");
    evidence_submit ($edit_id);
  }
  return $edit_id + 0;
}

function evidence_render_row (&$row)
{
  $id_prefix = "v_$row[variant_id]__a_$row[article_pmid]__g_$row[genome_id]__p_$row[edit_id]__";
  $title = "";
  $html = "";

  if ($row["is_delete"])
    $html .= "<DIV style=\"outline: 1px dashed #300; background-color: #fdd; color: #300; padding: 20px 20px 0 20px; margin: 0 0 10px 0;\"><P>Deleted in this revision:</P>";
  else if ($row["flag_edited_id"]) {
    if ($row["previous_edit_id"]) $edited = "Edited";
    else $edited = "Added";
    $html .= "<DIV style=\"outline: 1px dashed #300; background-color: #dfd; color: #300; padding: 20px 20px 0 20px; margin: 0 0 10px 0;\"><P>$edited in this revision:</P>";
  }

  if ($row["article_pmid"] != '0' && strlen($row["article_pmid"]) > 0) {
    $html .= "<A name=\"a".htmlentities($row["article_pmid"])."\"></A>\n";
    $summary = article_get_summary ($row["article_pmid"]);
    $html .= editable ("${id_prefix}f_summary_short__70x5__textile",
		       $row[summary_short],
		       $summary . "<BR />",
		       array ("tip" => "Explain this article's contribution to the conclusions drawn in the variant summary above."));
  }

  else if ($row["genome_id"] != '0' && strlen($row["genome_id"]) > 0) {

    $html .= "<A name=\"g".$row["genome_id"]."\"></A>\n";

    // Pick the most human-readable name for this genome/person
    if (!($name = $row["name"]))
      if (!($name = $row["global_human_id"]))
	$name = "[" . $row["genome_id"] . "]";
    $name = htmlspecialchars ($name);

    // Link to the full genome(s)
    if ($row["dataset_count"] > 0)
      $name = "<A href=\"$row[dataset_url]\">$name</A>";
    if ($row["dataset_count"] > 1) {
      $more = $row["dataset_count"] - 1;
      $name .= " (";
      if ($row["dataset_url_2"]) {
	$name .= "<A href=\"$row[dataset_url_2]\">alternate</A>, ";
	--$more;
      }
      if ($more > 1)
	$name .= "plus $more other data sets";
      else if ($more == 1)
	$name .= "plus 1 other data set";
      else
	$name = ereg_replace (", $", "", $name);
      $name .= ")";
    }

    // Indicate the SNP that causes the variant
    if ($row["chr"]) {
      $name .= htmlspecialchars ("\n".substr($row["zygosity"],0,3)." ".$row["allele"]." @ ".$row["chr"].":".$row["chr_pos"]);
      $name = nl2br ($name);
    }

    $html .= editable ("${id_prefix}f_summary_short__70x5__textile",
		       $row[summary_short],
		       $name);
  }
  else {
    $html .= editable ("${id_prefix}f_summary_short__70x5__textile",
		       $row[summary_short],
		       "Summary",
		       array ("tip" => "This is a brief summary of the variant's clinical relevance.<br/><br/>It should be 1-2 lines long -- short enough to include in a tabular report."));
    $html .= editable ("${id_prefix}f_variant_impact__",
		       $row[variant_impact],
		       "Impact",
		       array ("select_options"
			      => array ("pathogenic" => "pathogenic",
					"putative pathogenic" => "putative pathogenic",
					"unknown" => "unknown",
					"putative benign" => "putative benign",
					"benign" => "benign"),
			      "tip" => "Categorize the expected impact of this variant."));
    $html .= editable ("${id_prefix}f_variant_dominance__",
		       $row[variant_dominance],
		       "Inheritance pattern",
		       array ("select_options" => array ("unknown" => "unknown",
							 "dominant" => "dominant",
							 "recessive" => "recessive")));
    $html .= editable ("${id_prefix}f_summary_long__70x5__textile",
		       $row[summary_long],
		       "Clinical significance",
		       array ("tip" => "Describe the clinical significance of this variant."));
  }

  if ($row["is_delete"] || $row["flag_edited_id"])
    $html .= "</DIV>";

  return $html;
}

function evidence_render_history ($variant_id)
{
  $html = "<UL>\n";
  $thisyear = strftime ("%Y", time());
  $today = strftime ("%b %e %Y", time());
  $q = theDb()->query ("
SELECT
 UNIX_TIMESTAMP(edit_timestamp) AS edit_timestamp,
 edit_oid,
 u.fullname AS edit_fullname,
 article_pmid,
 s.genome_id AS genome_id,
 IF(g.name IS NULL OR g.name='',concat('[',global_human_id,']'),g.name) AS genome_name,
 is_delete,
 previous_edit_id,
 edit_id,
 v.*
FROM edits s
LEFT JOIN variants v ON v.variant_id=s.variant_id
LEFT JOIN eb_users u ON u.oid=s.edit_oid
LEFT JOIN genomes g ON g.genome_id=s.genome_id
WHERE s.variant_id=? AND is_draft=0
ORDER BY edit_timestamp DESC, edit_id DESC, previous_edit_id DESC
",
		       array ($variant_id));
  if (theDb()->isError($q)) die ($q->getMessage());
  $lastli = "";
  while ($row =& $q->fetchRow()) {
    $li = "<LI>";

    $nicetime = strftime ("%b %e %Y %l:%M%P", $row["edit_timestamp"]);
    $nicetime = str_replace ("  ", " ", $nicetime);
    $nicetime = str_replace ("$today ", "", $nicetime);
    $nicetime = str_replace ("$thisyear ", "", $nicetime);
    $nicetime = ereg_replace (" [^ ]+$", "", $nicetime);
    $li .= $nicetime;

    $li .= " <A href=\"edits?oid=".urlencode($row["edit_oid"])."\">".htmlspecialchars($row["edit_fullname"])."</A> ";

    $summary = "";
    if ($row["is_delete"]) $li .= "removed ";
    else if ($row["previous_edit_id"]) { $li .= "edited "; $summary = " summary"; }
    else $li .= "added ";

    if ($row["article_pmid"]) $li .= "article ".htmlspecialchars($row["article_pmid"]).$summary;
    else if ($row["genome_id"]) $li .= htmlspecialchars($row["genome_name"]).$summary;
    else $li .= "variant$summary";

    $li .= " <A href=\"".$row["variant_gene"]."-".$row["variant_aa_from"].$row["variant_aa_pos"].$row["variant_aa_to"].";".$row["edit_id"]."\">view</A>";

    $li .= "</LI>\n";

    // Compress sequences of same type of edit (same person, date, etc.)
    if ($li != $lastli)
      $html .= $li;
    else
      // TODO: offer to expand these in case the person wants to
      // recover an intermediate edit
      ;
    $lastli = $li;
  }
  $html .= "</UL>\n";
  return $html;
}

?>
