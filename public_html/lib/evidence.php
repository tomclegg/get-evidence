<?php

require_once ("lib/article.php");

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
  article_pmid INT UNSIGNED,
  genome_id BIGINT UNSIGNED NOT NULL,
  
  INDEX (variant_id,edit_timestamp),
  INDEX (edit_oid, edit_timestamp),
  INDEX (previous_edit_id, edit_oid),
  INDEX (variant_id, article_pmid, genome_id, edit_timestamp),
  INDEX (is_draft, edit_timestamp)
)");

  theDb()->query ("CREATE TABLE IF NOT EXISTS snap_latest LIKE edits");
  theDb()->query ("ALTER TABLE snap_latest ADD UNIQUE snap_key (variant_id, article_pmid, genome_id)");
  theDb()->query ("CREATE TABLE IF NOT EXISTS snap_release LIKE edits");
  theDb()->query ("ALTER TABLE snap_release ADD UNIQUE snap_key (variant_id, article_pmid, genome_id)");

  theDb()->query ("CREATE TABLE IF NOT EXISTS genomes (
  genome_id SERIAL,
  global_human_id VARCHAR(16) NOT NULL,
  name VARCHAR(128),
  UNIQUE(global_human_id))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS datasets (
  dataset_id VARCHAR(16) NOT NULL,
  genome_id BIGINT UNSIGNED NOT NULL,
  dataset_url VARCHAR(255),
  INDEX(genome_id,dataset_id),
  UNIQUE(dataset_id))");

  theDb()->query ("CREATE TABLE IF NOT EXISTS variant_occurs (
  variant_id BIGINT UNSIGNED NOT NULL,
  rsid BIGINT UNSIGNED NOT NULL,
  dataset_id VARCHAR(16) NOT NULL,
  UNIQUE(variant_id,dataset_id,rsid))");
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
  // Get all items relating to the given variant

  $v =& theDb()->getAll ("SELECT *,
			variants.variant_id AS variant_id,
			snap_$snap.genome_id AS genome_id,
			COUNT(datasets.dataset_id) AS dataset_count,
			MAX(dataset_url) AS dataset_url,
			MIN(dataset_url) AS dataset_url_2
			FROM variants
			LEFT JOIN snap_$snap
				ON variants.variant_id = snap_$snap.variant_id
			LEFT JOIN genomes
				ON snap_$snap.genome_id > 0
				AND snap_$snap.genome_id = genomes.genome_id
			LEFT JOIN variant_occurs
				ON snap_$snap.variant_id = variant_occurs.variant_id
			LEFT JOIN datasets
				ON variant_occurs.dataset_id = datasets.dataset_id
				AND datasets.genome_id = snap_$snap.genome_id
			WHERE variants.variant_id=?
				AND (datasets.genome_id = snap_$snap.genome_id OR datasets.genome_id IS NULL)
			GROUP BY snap_$snap.genome_id, snap_$snap.article_pmid, snap_$snap.genome_id
			ORDER BY
			snap_$snap.genome_id,
			article_pmid,
			edit_timestamp",
			 array ($variant_id));
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

  if ($row["article_pmid"] != '0' && strlen($row["article_pmid"]) > 0) {
    $summary = article_get_summary ($row["article_pmid"]);
    $html .= editable ("${id_prefix}f_summary_short__70x5__textile",
		       $row[summary_short],
		       $summary . "<BR />",
		       array ("tip" => "Explain this article's contribution to the conclusions drawn in the variant summary above."));
  }

  else if ($row["genome_id"] != '0' && strlen($row["genome_id"]) > 0) {

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

  return $html;
}

?>
