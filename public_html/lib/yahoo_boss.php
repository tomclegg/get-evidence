<?php

  // Copyright 2009 Scalable Computing Experts, Inc.
  // Author: Tom Clegg

require_once ("lib/aa.php");


function yahoo_boss_create_tables ()
{
    theDb()->query ("
CREATE TABLE IF NOT EXISTS yahoo_boss_cache (
 variant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 xml TEXT
)");
    theDb()->query ("ALTER TABLE yahoo_boss_cache ADD hitcount INT UNSIGNED");
    theDb()->query ("ALTER TABLE yahoo_boss_cache ADD retrieved DATETIME");
}

function yahoo_boss_lookup ($variant_id)
{
    $cache = theDb()->getRow ("SELECT * FROM yahoo_boss_cache WHERE variant_id=?",
			      array ($variant_id));
    if (!$cache || theDb()->isError ($cache)) $cache = array();

    $xml = $cache["xml"];
    if (!$xml) {
	if (!getenv("APIKEY"))
	    return FALSE;

	$variant = theDb()->getRow ("SELECT * FROM variants WHERE variant_id=?",
				    array ($variant_id));
	if (!$variant || theDb()->isError ($variant))
	    return FALSE;
	$gene_aa = $variant["variant_gene"] . " "
	    . aa_short_form($variant["variant_aa_from"])
	    . $variant["variant_aa_pos"]
	    . aa_short_form($variant["variant_aa_to"]);
	$ch = curl_init ();
	$url = "http://boss.yahooapis.com/ysearch/web/v1/"
	    . urlencode ($gene_aa)
	    . "?appid="
	    . urlencode(getenv("APIKEY"))
	    . "&format=xml";
	curl_setopt ($ch, CURLOPT_URL, $url);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	$xml = curl_exec ($ch);
	curl_close ($ch);

	$cache["xml"] = $xml;
	if (preg_match ('/<resultset_web\b[^<]*\sdeephits="?(\d+)"?/s', $xml))
	    theDb()->query ("REPLACE INTO yahoo_boss_cache SET variant_id=?, xml=?, retrieved=NOW()",
			    array ($variant_id, $cache["xml"]));
    }
    if (!is_numeric ($cache["hitcount"])) {
	if (preg_match ('/<resultset_web\b[^<]*\sdeephits="?(\d+)"?/s',
			$cache["xml"],
			$regs)) {
	    $cache["hitcount"] = $regs[1];
	    theDb()->query ("UPDATE yahoo_boss_cache SET hitcount=? WHERE variant_id=?",
			    array ($cache["hitcount"], $variant_id));
	}
    }
    return $cache;
}

function yahoo_boss_update_external ($variant_id)
{
    $cache = yahoo_boss_lookup ($variant_id);
    if (!$cache) {
	print "No search results\n";
	return;
    }

    $variant = theDb()->getRow ("SELECT * FROM variants WHERE variant_id=?",
				array ($variant_id));
    if (!$variant || theDb()->isError ($variant)) {
	print "No such variant\n";
	return FALSE;
    }
    $gene_aa = $variant["variant_gene"] . " "
	. aa_short_form($variant["variant_aa_from"])
	. $variant["variant_aa_pos"]
	. aa_short_form($variant["variant_aa_to"]);

    $url = "http://search.yahoo.com/search?p=" . urlencode ($gene_aa);

    $content = $cache["hitcount"]." web search hit".($cache["hitcount"]==1?"":"s").".";

    theDb()->query ("DELETE FROM variant_external WHERE variant_id=? AND tag=?", array ($variant_id, "Yahoo!"));
    $q = theDb()->query ("INSERT INTO variant_external SET variant_id=?, tag=?, content=?, url=?, updated=NOW()",
			 array ($variant_id, "Yahoo!", $content, $url));

    if ($cache["hitcount"] > 0) {

	preg_match_all ('{<result>.*?</result>}is', $cache["xml"], $matches,
			PREG_PATTERN_ORDER);
	foreach ($matches[0] as $result) {
	    if (preg_match ('{<url>(.*?)</url>}i', $result, $regs_url) &&
		preg_match ('{<title>(.*?)</title>}i', $result, $regs_title)) {
		$title = preg_replace ('{<\!\[CDATA\[(.*?)\]\]>}s', '$1', $regs_title[1]);
		$url = $regs_url[1];

		/*
		  $q = theDb()->query ("INSERT INTO variant_external SET variant_id=?, tag=?, content=?, url=?, updated=NOW()",
		  array ($variant_id, "Yahoo!", $title, $regs_url[1]));
		*/
	    }
	}
    }
}

?>