<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

function article_create_tables ()
{
  theDb()->query ("CREATE TABLE IF NOT EXISTS articles (
 article_id VARCHAR(16) PRIMARY KEY,
 article_summary TEXT,
 article_summary_retrieved DATETIME
)");
}

function article_get_summary ($id)
{
  if (ereg ("^[0-9]+$", $id))
    $id = "PMID:$id";
  $html = theDb()->getOne ("SELECT article_summary FROM articles WHERE article_id=?", array ($id));
  if (theDb()->isError($html)) {
    article_create_tables();
    $html = NULL;
  }
  if ($html === NULL) {
    // Fetch summary from external source
    if (ereg ("^PMID:", $id))
      $html = pubmed_get_summary ($id);

    // (Support other article reference types here)

    // Update the cache
    if ($html !== NULL) {
      theDb()->query ("REPLACE INTO articles SET article_id=?, article_summary=?, article_summary_retrieved=NOW()",
		      array ($id, $html));
    }
  }
  if ($html === NULL)
    return $id;
  $html = preg_replace ('{(\. )([^\.]+)(\. )}',
			'$1<strong>$2</strong>$3',
			$html,
			1);
  $html = article_add_external_links ($html);
  return $html;
}

function pubmed_get_summary ($id)
{
  $id = ereg_replace ("^PMID:", "", $id);
  if (!ereg ("^[0-9]+$", $id))
    return NULL;
  $ch = curl_init ();
  if (!$ch) return NULL;

  curl_setopt ($ch, CURLOPT_URL, "http://www.ncbi.nlm.nih.gov/pubmed/$id?report=DocSum&format=text");
  curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
  $html = curl_exec ($ch);
  curl_close ($ch);
  if ($html === false) return NULL;

  if (preg_match ('{<pre\b[^>]*>(\s*1:\s*)?(.*?)</pre\b}is', $html, $regs))
    $html = trim($regs[2]);
  if (!preg_match ('{PMID:\s*[0-9]+}s', $html))
    $html .= " PubMed PMID: $id";
  return $html;
}

function article_add_external_links ($html)
{
  $html = preg_replace
    ('{(PubMed )?PMID:*\s*([0-9]+)}s',
     '<A href="http://www.ncbi.nlm.nih.gov/pubmed/$2">$0</A>',
     $html);
  return $html;
}

?>
