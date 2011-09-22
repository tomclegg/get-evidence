<?php
    ;

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

require_once ("lib/aa.php");


function yahoo_boss_lookup ($variant_id)
{
    $cache = theDb()->getRow ("SELECT * FROM yahoo_boss_cache WHERE variant_id=?",
			      array ($variant_id));
    if (!$cache || theDb()->isError ($cache)) $cache = array();

    $xml = isset($cache["xml"]) ? $cache["xml"] : null;
    if (!$xml) {
	if (!getenv("APIKEY"))
	    return FALSE;

	$variant = theDb()->getRow ("SELECT v.*, vo.rsid rsid FROM variants v
				 LEFT JOIN variant_occurs vo
				  ON v.variant_id=vo.variant_id
				  AND vo.rsid IS NOT NULL
				 WHERE v.variant_id=?
				 GROUP BY v.variant_id",
				    array ($variant_id));
	if (!$variant || theDb()->isError ($variant))
	    return FALSE;
	if ($variant["variant_gene"]) {
	    $gene_aa_long = $variant["variant_gene"] . " "
		. $variant["variant_aa_from"]
		. $variant["variant_aa_pos"]
		. $variant["variant_aa_to"];
	    $gene_aa_short = $variant["variant_gene"] . " "
		. aa_short_form($variant["variant_aa_from"])
		. $variant["variant_aa_pos"]
		. aa_short_form($variant["variant_aa_to"]);
	    $search_string = "$gene_aa_long OR $gene_aa_short";
	    if (($rsid = $variant["variant_rsid"]) ||
		($rsid = $variant["rsid"]))
		$search_string .= " OR rs$rsid";
	} else
	    $search_string = "rs" . $variant["variant_rsid"];
	$ch = curl_init ();
	$url = "http://boss.yahooapis.com/ysearch/web/v1/"
	    . urlencode ($search_string)
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
	else
	    return null;
    }
    if (!isset ($cache["hitcount"]) || !is_numeric ($cache["hitcount"])) {
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

    $variant = theDb()->getRow ("SELECT v.*, vo.rsid rsid FROM variants v
				 LEFT JOIN variant_occurs vo
				  ON v.variant_id=vo.variant_id
				  AND vo.rsid IS NOT NULL
				 WHERE v.variant_id=?
				 GROUP BY v.variant_id",
				array ($variant_id));
    if (!$variant || theDb()->isError ($variant)) {
	print "No such variant\n";
	return FALSE;
    }
    if ($variant["variant_gene"]) {
	$gene_aa_long = $variant["variant_gene"] . " "
	    . $variant["variant_aa_from"]
	    . $variant["variant_aa_pos"]
	    . $variant["variant_aa_to"];
	$gene_aa_short = $variant["variant_gene"] . " "
	    . aa_short_form($variant["variant_aa_from"])
	    . $variant["variant_aa_pos"]
	    . aa_short_form($variant["variant_aa_to"]);
	$search_string = "$gene_aa_long OR $gene_aa_short";
	if (($rsid = $variant["variant_rsid"]) ||
	    ($rsid = $variant["rsid"]))
	    $search_string .= " OR rs$rsid";
    } else
	$search_string = "rs" . $variant["variant_rsid"];

    $user_url = "http://search.yahoo.com/search?p=" . urlencode ($search_string);
    $content = "";
    $skipped_hits = 0;

    if (isset($cache["hitcount"]) && $cache["hitcount"] > 0) {
	preg_match_all ('{<result>.*?</result>}is', $cache["xml"], $matches,
			PREG_PATTERN_ORDER);
	foreach ($matches[0] as $result) {
	    $resulttag = array();
	    foreach (array ("url", "dispurl", "abstract", "title") as $t)
		if (preg_match ("{<$t>(.*?)</$t>}i", $result, $regs))
		    $resulttag[$t] = preg_replace ('{<\!\[CDATA\[(.*?)\]\]>}s',
						   '$1',
						   $regs[1]);
		else {
		    $resulttag = FALSE;
		    continue;
		}
	    if (preg_match ('{snp\.med\.harvard\.edu|snp-dev.*\.freelogy\.org|evidence\.personalgenomes\.org}',
			    $resulttag["url"])) {
		$skipped_hits++;
		continue;
	    }
	    if ($resulttag) {
		$content .= "<LI><A href=\""
		    . $resulttag["url"]
		    . "\">"
		    . $resulttag["title"]
		    . "</A><BR />"
		    . $resulttag["abstract"]
		    . "<BR /><DIV class=\"searchurl\">"
		    . $resulttag["dispurl"]
		    . "</DIV></LI>\n";
	    }
	}
    }

    // If we skipped some hits (because they point to this page or
    // Trait-o-matic), subtract them from the cached hitcount so "no
    // web results except this page" gets counted as 0 for
    // statistics/display.

    if ($skipped_hits > 0 &&
	preg_match ('/<resultset_web\b[^<]*\sdeephits="?(\d+)"?/s',
		    $cache["xml"],
		    $regs) &&
	$regs[1] >= $skipped_hits) {
	$hitcount = $regs[1] - $skipped_hits;
	if ($hitcount != $cache["hitcount"]) {
	    $cache["hitcount"] = $hitcount;
	    theDb()->query ("UPDATE yahoo_boss_cache SET hitcount=? WHERE variant_id=?",
			    array ($hitcount, $variant_id));
	}
    }

    // Build html display for variant page

    $content = "<UL><STRONG>Web search results ("
	. $cache["hitcount"]
	. " hit"
	. ($cache["hitcount"]==1?"":"s")
	. " -- <A href=\""
	. $user_url
	. "\">see all</A>)</STRONG>"
	. $content
	. "</UL>";

    theDb()->query ("DELETE FROM variant_external WHERE variant_id=? AND tag=?", array ($variant_id, "Yahoo!"));
    $q = theDb()->query ("INSERT INTO variant_external SET variant_id=?, tag=?, content=?, url=NULL, updated=NOW()",
			 array ($variant_id, "Yahoo!", $content));
}

?>