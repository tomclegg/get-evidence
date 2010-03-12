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
  variant_impact ENUM('pathogenic','likely pathogenic','unknown','likely benign','benign','likely protective','protective','other','pharmacogenetic','likely pharmacogenetic','none','not reviewed') NOT NULL DEFAULT 'not reviewed',
  variant_dominance ENUM('unknown','dominant','recessive','other','undefined') NOT NULL DEFAULT 'unknown',
  variant_quality CHAR(5),
  variant_quality_text TEXT,
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

  theDb()->query ("CREATE TABLE IF NOT EXISTS flat_summary (
  variant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  updated TIMESTAMP,
  flat_summary TEXT
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
    return theDb()->getOne ("SELECT LAST_INSERT_ID()");
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

  theDb()->query ("UPDATE edits
 SET is_draft=0,
 edit_timestamp=NOW(),
 article_pmid=if(article_pmid is null,0,article_pmid),
 genome_id=if(genome_id is null,0,genome_id),
 disease_id=if(disease_id is null,0,disease_id)
 WHERE edit_id=? AND edit_oid=?",
		  array($edit_id, getCurrentUser("oid")));
  theDb()->query ("REPLACE INTO snap_latest
 SELECT *
 FROM edits
 WHERE edit_id=?
 AND edit_oid=?",
		  array($edit_id, getCurrentUser("oid")));
  theDb()->query ("DELETE FROM snap_latest WHERE is_delete=1");

  $v = theDb()->getOne ("SELECT variant_id FROM snap_latest WHERE edit_id=?",
			array ($edit_id));
  if ($v) {
    theDb()->query ("REPLACE INTO flat_summary SET variant_id=?, flat_summary=?",
		    array ($v, json_encode (evidence_get_assoc_flat_summary ("latest", $v))));
  }
  else {
    $v = theDb()->getOne ("SELECT variant_id FROM edits WHERE edit_id=?",
			  array ($edit_id));
    theDb()->query ("DELETE FROM flat_summary WHERE variant_id=?",
		    array ($v));
  }
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
    $and_max_edit_id = "AND $table.edit_id IN (
 SELECT MAX(edits.edit_id)
 FROM edits
 WHERE variant_id=$variant_id
 AND edit_id<=$snap
 AND is_draft=0
 GROUP BY article_pmid, genome_id, disease_id)
 AND ($table.edit_id=$snap OR $table.is_delete=0
 )";
  }

  // Get all items relating to the given variant

  $v =& theDb()->getAll ("SELECT variants.*, $table.*, genomes.*, datasets.*, variant_occurs.*,
			variants.variant_id AS variant_id,
			$table.genome_id AS genome_id,
			$table.disease_id AS disease_id,
			diseases.disease_name AS disease_name,
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
			LEFT JOIN diseases
				ON $table.disease_id = diseases.disease_id
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
				$table.article_pmid,
				$table.disease_id
			ORDER BY
				$table.genome_id,
				$table.article_pmid,
				diseases.disease_name,
				$table.disease_id,
				$table.edit_id DESC",
			 array ($flag_edited_id, $variant_id));
  if (theDb()->isError($v)) die ($v->getMessage());
  if (!theDb()->isError($v) && $v && $v[0])
    foreach (array ("article_pmid", "genome_id", "disease_id") as $x)
      if (!$v[0][$x])
	$v[0][$x] = 0;

  // Make sure for every pmid>0 row all of the article=A, disease=D
  // rows are there too (and ditto for article=0, genome=0)

  $have_a_d = array();		// will contain one array per article
				// id (incl. "0" for the main variant
				// summary section)
  foreach ($v as $row) {
    if (!$row["genome_id"]) {
      $have_a_d[$row["article_pmid"]][$row["disease_id"]] = 1;
    }
  }

  // Get a list of all the diseases that should be listed in each disease table

  $v_d = theDb()->getAll ("SELECT diseases.* FROM diseases
 WHERE disease_id IN
 (SELECT disease_id
  FROM variant_disease
  WHERE variant_id=?
 UNION
  SELECT disease_id
  FROM gene_disease
 WHERE gene = ?)", array ($variant_id, $v[0]["variant_gene"]));

  // Look for article=A, disease=D rows that should be there but
  // aren't... and add them

  foreach ($v_d as $row) {		   // for each disease...
    foreach ($have_a_d as $a => $have_d) { // for each article...
      $d = $row["disease_id"];
      if (isset ($have_d[$d]))
	// already have a result row for this {article, disease}
	continue;

      // add a row after all of the existing rows pertaining to the
      // target article

      for ($i=0; $i<sizeof($v); $i++) {
	// skip until we reach the target article's row
	if ($v[$i]["article_pmid"] != $a)
	  continue;
	// skip until we reach the last row for the target article
	if ($i<sizeof($v)-1 &&
	    $a != 0 &&
	    $v[$i+1]["article_pmid"] == $a)
	  continue;

	// found the last row for this article.  copy the existing row
	// (minus the editable stuff) and insert after.
	array_splice ($v, $i+1, 0, array($v[$i]));
	$v[$i+1]["disease_id"] = $row["disease_id"];
	$v[$i+1]["disease_name"] = $row["disease_name"];
	$v[$i+1]["summary_short"] = "";
	$v[$i+1]["summary_long"] = "";
	$v[$i+1]["talk_text"] = "";
	$v[$i+1]["edit_id"] = "";
	$v[$i+1]["previous_edit_id"] = "";
	break;
      }
    }
  }

  if ($v && is_array ($v[0])) {
    if (1) {
      // fix up obsolete impacts (until they get fixed in the db, at which
      // point this section can be removed)
      if ($v[0]["variant_impact"] == "unknown" ||
	  $v[0]["variant_impact"] == "none")
	$v[0]["variant_impact"] = "not reviewed";
    }

    $v[0]["certainty"] = evidence_compute_certainty ($v[0]["variant_quality"],
						     $v[0]["variant_impact"]);
    $v[0]["qualified_impact"] = evidence_qualify_impact ($v[0]["variant_quality"],
							 $v[0]["variant_impact"]);
  }

  return $v;
}

$gWantKeysForAssoc = array
    ("all" => "edit_id previous_edit_id editor_name edit_timestamp signoff_oid signoff_timestamp",
     "disease" => "disease_id disease_name case_pos case_neg control_pos control_neg",
     "article" => "article_pmid summary_long",
     "genome" => "genome_id global_human_id name sex zygosity dataset_id rsid chr chr_pos allele summary_long",
     "variant" => "variant_id:id variant_gene:gene aa_change aa_change_short variant_impact:impact qualified_impact variant_dominance:inheritance quality_scores quality_comments variant_f_num variant_f_denom variant_f gwas_max_or nblosum100 disease_max_or certainty");

function evidence_get_assoc ($snap, $variant_id)
{
  $rows =& evidence_get_report ($snap, $variant_id);

  global $gWantKeysForAssoc;
  if (!is_array ($gWantKeysForAssoc["variant"])) {
    foreach ($gWantKeysForAssoc as $k => &$v) {
      if ($k == "all") continue;
      $v = explode (" ", $gWantKeysForAssoc["all"] . " " . $v);
    }
  }

  $variant = array ("genomes" => array(),
		    "articles" => array(),
		    "diseases" => array());
  for ($i=0; $i<sizeof($rows); $i++) {
    $row =& $rows[$i];

    $editor = user::lookup ($row["edit_oid"]);
    $row["editor_name"] = $editor->get("fullname");

    if (strlen($row["summary_long"]) == 0)
      $row["summary_long"] = $row["summary_short"];

    if ($row["article_pmid"] > 0) {
      $section =& $variant["articles"]["".$row["article_pmid"]];
      $want_keys =& $gWantKeysForAssoc["article"];
    }
    else if ($row["genome_id"] > 0) {
      $section =& $variant["genomes"]["".$row["genome_id"]];
      $want_keys =& $gWantKeysForAssoc["genome"];
    }
    else {
      $section =& $variant;
      $want_keys =& $gWantKeysForAssoc["variant"];
      $row["aa_change"]
	  = $row["variant_aa_from"]
	  . $row["variant_aa_pos"]
	  . $row["variant_aa_to"];
      $row["aa_change_short"] = aa_short_form ($row["aa_change"]);

      // TODO: combine these into one array and add labels
      $row["quality_scores"] = str_split (str_pad ($row["variant_quality"], 6, "-"));
      $row["quality_comments"] = $row["variant_quality_text"] ? json_decode ($row["variant_quality_text"], true) : array();
      $row["nblosum100"] = 0-blosum100($row["variant_aa_from"], $row["variant_aa_to"]);
      $diseases = evidence_get_all_oddsratios ($rows);
      unset ($max_or_id);
      foreach ($diseases as $id => &$d) {
	if (!isset ($max_or_id) ||
	    $diseases[$max_or_id]["figs"]["or"] < $d["figs"]["or"])
	  $max_or_id = $id;
      }
      if (isset ($max_or_id))
	$row["disease_max_or"] = $diseases[$max_or_id];
    }

    if ($row["disease_id"] > 0) {
      $section =& $section["diseases"]["".$row["disease_id"]];
      if (ereg ('^\[', $row["summary_short"]))
	$row = array_merge (json_decode ($row["summary_short"], true), $row);
      $want_keys =& $gWantKeysForAssoc["disease"];
    }

    foreach ($want_keys as $k) {
      list ($inkey, $outkey) = explode (":", $k);
      if (!$outkey) $outkey = $inkey;
      $section[$outkey] = $row[$inkey];
    }

    unset ($section);
  }

  foreach (array ("articles", "genomes") as $section) {
    $variant[$section] = array_values ($variant[$section]);
    foreach ($variant[$section] as &$x) {
      if (is_array ($x["diseases"]))
	$x["diseases"] = array_values ($x["diseases"]);
    }
  }

  return $variant;
}

function evidence_get_assoc_flat_summary ($snap, $variant_id)
{
  $nonflat =& evidence_get_assoc ($snap, $variant_id);
  $flat = array ();
  foreach (array ("gene", "aa_change", "aa_change_short", "impact", "qualified_impact", "inheritance") as $k)
      $flat[$k] = $nonflat[$k];
  $flat["dbsnp_id"] = "";
  foreach ($nonflat["genomes"] as &$g) {
    if ($g["rsid"] > 0) {
      $flat["dbsnp_id"] = "rs".$g["rsid"];
      break;
    }
  }
  $flat["overall_frequency_n"] = $nonflat["variant_f_num"];
  $flat["overall_frequency_d"] = $nonflat["variant_f_denom"];
  $flat["overall_frequency"] = $nonflat["variant_f"];
  $flat["gwas_max_or"] = $nonflat["gwas_max_or"];
  $flat["n_genomes"] = sizeof ($nonflat["genomes"]);
  $flat["n_genomes_annotated"] = 0;
  $flat["n_haplomes"] = 0;
  foreach ($nonflat["genomes"] as &$g) {
    if (strlen ($g["summary_long"]) > 0)
      $flat["n_genomes_annotated"] ++;

    if ($g["zygosity"] != "homozygous" ||
	($g["sex"] == "M" && ($g["chr"] == "chrX" || $g["chr"] == "chrY")))
      $flat["n_haplomes"] ++;
    else
      $flat["n_haplomes"] += 2;
  }
  $flat["n_articles"] = sizeof ($nonflat["articles"]);
  $flat["n_articles_annotated"] = 0;
  foreach ($nonflat["articles"] as &$g) {
    if (strlen ($g["summary_long"]) > 0)
      $flat["n_articles_annotated"] ++;
  }
  $i = -1;
  foreach (array ("in_silico", "in_vitro", "case_control", "familial", "severity", "treatability") as $scoreaxis) {
    ++$i;
    if (sizeof ($nonflat["quality_scores"]) >= $i+1) {
      $flat["qualityscore_".$scoreaxis] = $nonflat["quality_scores"][$i];
    }
    else
      $flat["qualityscore_".$scoreaxis] = "-";
    if (sizeof ($nonflat["quality_scores"]) >= $i+1 &&
	strlen ($nonflat["quality_comments"][$i]["text"]) > 0) {
      $flat["qualitycomment_".$scoreaxis] = "Y";
    }
    else
      $flat["qualitycomment_".$scoreaxis] = "-";
  }
  $flat["gene_in_genetests"]
      = theDb()->getOne ("SELECT 1 FROM gene_disease WHERE gene=? LIMIT 1",
			 array ($flat["gene"])) ? 'Y' : '-';
  $flat["in_omim"]
      = theDb()->getOne ("SELECT 1 FROM variant_external WHERE variant_id=? AND tag='OMIM' LIMIT 1",
			 array ($nonflat["id"])) ? 'Y' : '-';
  $flat["in_gwas"]
      = theDb()->getOne ("SELECT 1 FROM variant_external WHERE variant_id=? AND tag='GWAS' LIMIT 1",
			 array ($nonflat["id"])) ? 'Y' : '-';
  $flat["nblosum100>2"] = $nonflat["nblosum100"] > 2 ? 'Y' : '-';
  if ($nonflat["disease_max_or"]) {
    $flat["max_or_disease_name"] = $nonflat["disease_max_or"]["disease_name"];
    foreach (array ("case_pos", "case_neg", "control_pos", "control_neg", "or")
	     as $f)
      $flat["max_or_".$f] = $nonflat["disease_max_or"]["figs"][$f];
  }
  else {
    $flat["max_or_disease_name"] = "";
    foreach (array ("case_pos", "case_neg", "control_pos", "control_neg", "or")
	     as $f)
      $flat["max_or_".$f] = "";
  }
  $flat["certainty"] = $nonflat["certainty"];
  return $flat;
}

function evidence_get_latest_edit ($variant_id,
				   $article_pmid, $genome_id, $disease_id,
				   $create_flag=false)
{
  if (!$variant_id) return null;
  $edit_id = theDb()->getOne
    ("SELECT MAX(edit_id) FROM edits
	WHERE variant_id=? AND article_pmid=? AND genome_id=? AND disease_id=?
	AND (is_draft=0 OR edit_oid=?)",
     array ($variant_id, $article_pmid, $genome_id, $disease_id,
	    getCurrentUser("oid")));

  if ($edit_id &&
      theDb()->getOne ("SELECT is_delete FROM edits WHERE edit_id=?",
		       array ($edit_id)))
    $edit_id = FALSE;

  if (!$edit_id && $create_flag) {
    theDb()->query
      ("INSERT INTO edits
	SET edit_timestamp=NOW(), edit_oid=?, is_draft=1,
	variant_id=?, article_pmid=?, genome_id=?, disease_id=?",
       array (getCurrentUser("oid"),
	      $variant_id, $article_pmid, $genome_id, $disease_id));
    $edit_id = theDb()->getOne ("SELECT LAST_INSERT_ID()");
    evidence_submit ($edit_id);
  }
  return $edit_id + 0;
}

class evidence_row_renderer {
    protected $lastrow = FALSE;
    protected $rownumber = FALSE;
    protected $html = "";
    protected $starttable = "";

    protected function row_transition (&$row)
    {
	if ($this->lastrow &&
	    $this->lastrow["disease_id"] &&
	    $this->rownumber > 0 &&
	    (!$row ||
	     $this->lastrow["article_pmid"] != $row["article_pmid"] ||
	     $this->lastrow["genome_id"] != $row["genome_id"]))
	  $this->html .= "</TABLE><P>&nbsp;</P>\n";
	if ($row &&
	    $row["disease_id"] &&
	    (!$this->lastrow ||
	     !$this->lastrow["disease_id"] ||
	     $row["article_pmid"] != $this->lastrow["article_pmid"] ||
	     $row["genome_id"] != $this->lastrow["genome_id"])) {
	  $class2 = "";
	  $title = "Cases/controls";
	  if ($row["article_pmid"] === "*" &&
	      $row["genome_id"] === "*") {
	    $class2 = " disease_totals";
	    $title = "<STRONG>Total cases/controls</STRONG>";
	  }
	  else if ($row["article_pmid"] == "0" &&
		   $row["genome_id"] == "0") {
	    $title = "Unpublished cases/controls";
	  }
	  $class3 = " delete_with_v{$row[variant_id]}_a{$row[article_pmid]}_g{$row[genome_id]}";
	  $this->starttable = "<TABLE $id class=\"disease_table$class2$class3\">\n";
	  $this->starttable .= "<TR><TH class=\"rowlabel\">$title</TH>";
	  foreach (array ("case+", "case&ndash;", "control+", "control&ndash;", "odds&nbsp;ratio") as $x)
	    $this->starttable .= "<TH width=\"60\">&nbsp;$x</TH>";
	  $this->starttable .= "</TR>\n";
	  $this->rownumber = 0;
	}
	$this->lastrow = $row;
    }

    function &html ()
    {
	$this->row_transition($x=FALSE);
	return $this->html;
    }

    function render_row (&$row)
    {
	$html = "";

	$this->row_transition ($row);
	$id_prefix = "v_$row[variant_id]__a_$row[article_pmid]__g_$row[genome_id]__d_$row[disease_id]__p_$row[edit_id]__";
	$title = "";

	foreach (array ("article_pmid", "genome_id", "disease_id") as $keyfield) {
	  if (strlen ($row[$keyfield]) == 0)
	    $row[$keyfield] = 0;
	}

	if ($row["disease_id"] != "0") {
	  $tr = editable ("${id_prefix}f_summary_short__8x1__oddsratio",
			  $row["summary_short"],
			  $row["disease_name"] . "<BR />",
			  array ("rownumber" => $this->rownumber,
				 "tip" => "Indicate the contribution of this article to OR statistics for ".htmlspecialchars($row["disease_name"])."."));
	  if ($tr != "") {
	    if ($this->rownumber == 0)
	      $html .= $this->starttable;
	    $html .= $tr;
	    ++$this->rownumber;
	  }
	}

	else if ($row["article_pmid"] != "0") {
	  $html .= "<A name=\"a".htmlentities($row["article_pmid"])."\"></A>\n";
	  $summary = article_get_summary ($row["article_pmid"]);
	  $html .= editable ("${id_prefix}f_summary_short__70x5__textile",
			     $row[summary_short],
			     $summary . "<BR />",
			     array ("tip" => "Explain this article's contribution to the conclusions drawn in the variant summary above."));

	}

	else if ($row["genome_id"] != "0") {

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

	else if ($row["disease_id"] != "0") {
	  // Disease summary not attached to any particular publication
	}

	else {
	  $html .= editable ("${id_prefix}f_summary_short__70x5__textile",
			     $row[summary_short],
			     "Short summary",
			     array ("tip" => "Provide a one line summary of clinical action to be undertaken given this variant (possibly modified by known phenotypes)."));

	  $html .= editable ("${id_prefix}f_variant_quality",
			     $row,
			     "Variant quality");

	  global $gImpactOptions;
	  $opts =& $gImpactOptions;
	  $qualified_impact = evidence_qualify_impact ($row["variant_quality"],
						       $row["variant_impact"]);
	  $html .= editable ("${id_prefix}f_variant_impact__",
			     $row["variant_impact"],
			     "Impact",
			     array ("select_options"
				    => $opts,
				    "previewtextile" => $qualified_impact,
				    "tip" => "Categorize the expected impact of this variant."));

	  if ($qualified_impact != $row["variant_impact"]) {
	    $html .= "<P><I>(The \"".ereg_replace (" ".$row["variant_impact"], "", $qualified_impact)."\" qualifier is assigned automatically based on the above evidence and importance scores.)</I></P>";
	  }

	  global $gInheritanceOptions;
	  $html .= editable ("${id_prefix}f_variant_dominance__",
			     $row[variant_dominance],
			     "Inheritance pattern",
			     array ("select_options" => $gInheritanceOptions));
	  $html .= editable ("${id_prefix}f_summary_long__70x5__textile",
			     $row[summary_long],
			     "Summary of published research, and additional commentary",
			     array ("tip" => "Provide a comprehensive review of the variant including youngest age of onset, oldest age of onset and oldest asymptomatic individual."));
	}

	if ($html == "")
	  return;

	if (ereg ('^<(TABLE|TR)', $html)) {
	  // TODO: handle is_delete and flag_edited_id for table rows
	  // somehow; for now just don't indicate them at all
	  if ($row["is_delete"]) return;
	  if ($row["flag_edited_id"]) { $this->html .= $html; return; }
	}

	if ($row["is_delete"])
	  $html .= "<DIV style=\"outline: 1px dashed #300; background-color: #fdd; color: #300; padding: 20px 20px 0 20px; margin: 0 0 10px 0;\"><P>Deleted in this revision:</P>$html</DIV>";
	else if ($row["flag_edited_id"]) {
	  if ($row["previous_edit_id"])
	    $edited = "Edited";
	  else
	    $edited = "Added";
	  $html = "<DIV style=\"outline: 1px dashed #300; background-color: #dfd; color: #300; padding: 20px 20px 0 20px; margin: 0 0 10px 0;\"><P>$edited in this revision:</P>\n$html</DIV>";
	}
	$this->html .= $html;
    }
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
 s.disease_id AS disease_id,
 d.disease_name AS disease_name,
 IF(g.name IS NULL OR g.name='',concat('[',global_human_id,']'),g.name) AS genome_name,
 is_delete,
 previous_edit_id,
 edit_id,
 v.*
FROM edits s
LEFT JOIN variants v ON v.variant_id=s.variant_id
LEFT JOIN eb_users u ON u.oid=s.edit_oid
LEFT JOIN genomes g ON g.genome_id=s.genome_id
LEFT JOIN diseases d ON d.disease_id=s.disease_id
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

    if ($row["disease_id"]) $li .= "OR figures for ".htmlspecialchars($row["disease_name"])." from ".($row["article_pmid"] ? "article ".$row["article_pmid"] : "unpublished research section");
    else if ($row["article_pmid"]) $li .= "article ".htmlspecialchars($row["article_pmid"]).$summary;
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


function evidence_get_all_oddsratios ($report)
{
  $disease = array ();
  foreach ($report as $row) {
    if (!(($id = $row["disease_id"]) > 0 &&
	  ereg ('^{', $row["summary_short"])))
      continue;
    $figs = json_decode ($row["summary_short"], true);
    if (!strlen ($figs["case_pos"]) &&
	!strlen ($figs["case_neg"]) &&
	!strlen ($figs["control_pos"]) &&
	!strlen ($figs["control_neg"]))
      continue;
    $disease[$id]["figs"]["case_pos"] += $figs["case_pos"];
    $disease[$id]["figs"]["case_neg"] += $figs["case_neg"];
    $disease[$id]["figs"]["control_pos"] += $figs["control_pos"];
    $disease[$id]["figs"]["control_neg"] += $figs["control_neg"];
    $disease[$id]["disease_id"] = $row["disease_id"];
    $disease[$id]["disease_name"] = $row["disease_name"];
    $disease[$id]["article_pmid"] = "*";
    $disease[$id]["genome_id"] = "*";
    $disease[$id]["figs"]["or"] = oddsratio_compute ($disease[$id]["figs"]);
  }
  return $disease;
}

function evidence_render_oddsratio_summary_table ($report)
{
  $disease =& evidence_get_all_oddsratios ($report);

  if (!sizeof ($disease))
    return "";

  global $gDisableEditing;
  $gDE_was = $gDisableEditing;
  $gDisableEditing = true;
  $renderer = new evidence_row_renderer;
  foreach ($disease as $id => &$row) {
    $row["summary_short"] = json_encode ($row["figs"]);
    $renderer->render_row ($row);
  }
  $gDisableEditing = $gDE_was;
  return $renderer->html();
}


function evidence_compute_certainty ($scores, $impact)
{
  // Summarize the given quality scores (in the context of the given
  // impact category) as a two-character string, the first character
  // representing variant evidence and the second character
  // representing clinical importance.  Each can be 0
  // (uncertain/unimportant), 1 (likely/important), 2 (well
  // established/very important), or "-" (not applicable).

  if ($impact == "not reviewed" || $impact == "unknown" || $impact == "none")
    return "--";

  $scores = str_split (str_pad ($scores, 6, "-"));
  foreach ($scores as $i => &$score)
      if ($score === "-") $score = 0;
      else if ($score === "!") $score = -1;

  $score_evidence = $scores[0]+$scores[1]+$scores[2]+$scores[3];
  if (($scores[2] >= 4 || $scores[3] >= 4) && $score_evidence >= 8)
    $certainty = "2";
  else if (($scores[2] >= 3 || $scores[3] >= 3) && $score_evidence >= 5)
    $certainty = "1";
  else
    $certainty = "0";

  if ($impact == "benign" || $impact == "protective")
    $certainty .= "-";
  else if ($scores[4] >= 4 || ($scores[4] >= 3 && $scores[5] >= 4))
    $certainty .= "2";
  else if ($scores[4] >= 3 || ($scores[4] >= 2 && $scores[5] >= 3))
    $certainty .= "1";
  else
    $certainty .= "0";

  return $certainty;
}


function evidence_qualify_impact ($scores, $impact)
{
  $c = str_split (evidence_compute_certainty ($scores, $impact));
  if ($c[0] == "-") return $impact;
  if ($c[1] >= 1) $impact = "important $impact";
  if ($c[1] >= 2) $impact = "very $impact";
  if ($c[0] == 0) return "uncertain $impact";
  if ($c[0] == 1) return "likely $impact";
  return $impact;
}


$gInheritanceOptions = array
    ("dominant" => "dominant",
     "recessive" => "recessive",
     "other" => "other (e.g., modifier, co-dominant, incomplete penetrance)",
     "undefined" => "undefined in the literature",
     "unknown" => "unknown (literature unavailable or not yet reviewed)");

$gImpactOptions = array
    ("pathogenic" => "pathogenic",
     "benign" => "benign",
     "protective" => "protective",
     "pharmacogenetic" => "pharmacogenetic",
     "not reviewed" => "not reviewed");
?>
