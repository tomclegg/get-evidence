<?php ; // -*- mode: java; c-basic-indent: 4; tab-width: 4; indent-tabs-mode: nil; -*-

// Copyright 2010 Clinical Future, Inc.
// Authors: see git-blame(1)

include "lib/setup.php";

$path_info = "";
if (isset($_SERVER["PATH_INFO"]))
    $path_info = $_SERVER["PATH_INFO"];

$snap = false;
if (@$_GET["version"] == "release" ||
    preg_match ('{/release}', $path_info) ||
    $_SERVER["argc"] > 1 && $_SERVER["argv"][1] == "release")
    $snap = "release";
else if (@$_GET["version"] == "latest" ||
         preg_match ('{/latest}', $path_info) ||
         $_SERVER["argc"] > 1 && $_SERVER["argv"][1] == "latest")
    $snap = "latest";

$need_max_or_or = 0;
if (preg_match ('{/max_or_or}', $path_info) ||
    $_SERVER["argc"] > 3 && $_SERVER["argv"][3] == "max_or_or")
    $need_max_or_or = 1;

if ($snap &&
    ($_GET["type"] == "flat" ||
     preg_match ('{/flat}', $path_info) ||
     $_SERVER["argc"] > 2 && $_SERVER["argv"][2] == "flat")) {
    ini_set ("memory_limit", 67108864);
    $q = theDb()->query ("SELECT s.variant_id, s.summary_short, flat_summary FROM snap_$snap s LEFT JOIN flat_summary fs ON fs.variant_id=s.variant_id WHERE s.article_pmid=0 AND s.genome_id=0 AND s.disease_id=0 ORDER BY s.variant_id");
    $n = 0;
    header ("Content-type: text/tab-separated-values");
    while ($row =& $q->fetchRow()) {
        if ($flat = $row["flat_summary"]) {
            $flat = json_decode ($flat, true);
        }
        else {
            $flat = evidence_get_assoc_flat_summary ($snap, $row["variant_id"]);
            theDb()->query ("REPLACE INTO flat_summary SET variant_id=?, flat_summary=?",
                            array ($row["variant_id"], json_encode($flat)));
        }
        if (array_key_exists ("certainty", $flat)) {
            // split certainty into evidence/importance fields (until
            // they get updated in the db, at which point this section
            // can be removed)
            list ($flat["variant_evidence"], $flat["clinical_importance"])
                = str_split ($flat["certainty"]);
            unset ($flat["certainty"]);
        }
        if ($n == 0) {
            $columns = array_keys ($flat);
            print implode ("\t", $columns);
            print "\tsummary_short\n";
        }
        ++$n;

        if ($need_max_or_or && empty($flat["max_or_or"]))
            continue;

        // fix up obsolete impacts (until they get fixed in the db, at which
        // point this section can be removed)
        if (array_key_exists ("impact", $flat)) {
            if ($flat["impact"] == "unknown" || $flat["impact"] == "none")
                $flat["impact"] = "not reviewed";
            else
                $flat["impact"] = preg_replace ("{^likely }", "", $flat["impact"]);
        }

        foreach ($columns as $c)
            print @$flat[$c]."\t";
        print preg_replace('{[\t\n]}', ' ', $row["summary_short"])."\n";
    }
    exit;
}

// Output text for flat file with json-formatted data for each variant
$want_type_json = ($_SERVER['argc'] > 2 && $_SERVER['argv'][2] == 'json');
if ($snap and $want_type_json) {
    ini_set ('memory_limit', 67108864);
    // Get flat_summary for variants from MySQL, contains most of what we want.
    // variant_quality in flat_summary is missing 'penetrance', get it from snap
    // summary_short isn't in flat_summary, get it too
    // Don't bother getting snapshot rows with article, genome, or disease data.
    $q = theDb()->query (
            "SELECT snap_$snap.variant_id as variant_id, 
                snap_$snap.variant_quality as variant_quality,
                snap_$snap.summary_short as summary_short,
                flat_summary.flat_summary as flat_summary
            FROM snap_$snap 
            LEFT JOIN flat_summary 
                ON flat_summary.variant_id=snap_$snap.variant_id
            WHERE snap_$snap.article_pmid=0 AND snap_$snap.genome_id=0 
                AND snap_$snap.disease_id=0 
            ORDER BY snap_$snap.variant_id"
            );
    header ('Content-type: text/plain');
    while ($row =& $q->fetchRow()) {
        if ($flat_summary = $row['flat_summary']) {
            // Pull JSON formatted data from flat_summary
            $flat_data = json_decode ($flat_summary, true);
            $flat_data['variant_quality'] = $row['variant_quality'];
            if ($row['summary_short']) {
                $flat_data['summary_short'] = $row['summary_short'];
            }
            $flat_data['variant_id'] = $row['variant_id'];
            print json_encode($flat_data) . "\n";
        } 
    }
    exit;  // Break out of this program, we've done what we wanted.
}

if ($snap) {

  $q = theDb()->query ("SELECT v.*, s.*,
                        if(vo.rsid,concat('rs',vo.rsid),null) dbsnp_id,
                        COUNT(vo.dataset_id) genome_hits,
                        y.hitcount web_hits,
                        vf.num overall_frequency_n,
                        vf.denom overall_frequency_d,
                        vf.f overall_frequency
                        FROM variants v
                        LEFT JOIN snap_$snap s ON s.variant_id=v.variant_id
                        LEFT JOIN variant_frequency vf ON v.variant_id=vf.variant_id
                        LEFT JOIN variant_occurs vo ON v.variant_id=vo.variant_id
                        LEFT JOIN yahoo_boss_cache y ON v.variant_id=y.variant_id
                        WHERE s.variant_id IS NOT NULL
                        AND s.article_pmid=0
                        AND s.genome_id=0
                        AND s.disease_id=0
                        GROUP BY v.variant_id");
  if (theDb()->isError($q)) {
    header ("HTTP/1.1 500 Internal server error");
    die ("Database error: " . $q->getMessage());
  }

  header ("Content-type: text/tab-separated-values");

  $fieldlist = array ("variant_gene",
                      "variant_aa_change",
                      "variant_dominance",
                      "variant_impact",
                      "dbsnp_id",
                      "overall_frequency_n",
                      "overall_frequency_d",
                      "overall_frequency",
                      "gwas_max_or",
                      "genome_hits",
                      "web_hits",
                      "summary_short");
  print preg_replace ('{\tvariant_}', "\t",
                      preg_replace ('{variant_dominance}',
                                    'variant_inheritance',
                                    implode ("\t", $fieldlist)));
  print "\n";

  ini_set ("output_buffering", true);
  while ($row =& $q->fetchRow()) {
    $out = "";
    $row["variant_aa_change"]
      = $row["variant_aa_from"].$row["variant_aa_pos"].$row["variant_aa_to"];
    foreach ($fieldlist as $field) {
      $v = $row[$field];
      if (strlen($out)) $out .= "\t";
      $out .= preg_replace ('{[\t\n]}', ' ', $v);
    }
    print $out;
    print "\n";
  }
  exit;

}
$gOut["content_textile"] = <<<EOF
h1. Download

h2. Genome data

Genome data for PGP participants and some other public genomes are available on this website for download. To get data for a particular genome, go to the "Genomes":genomes page and click on the "Get Report" button for an individual. The following data files are linked at the top of the report page:
* source data: the original genome data uploaded to our processing system. Currently this is either Complete Genomics var files or GFF files. 
* dbSNP and nsSNP report: the processed genome data in GFF format, with dbSNP and nonsynonymous amino acid change information added.

You may also want the "description of the GFF format":guide_upload_and_annotated_file_formats used for the dbSNP and nsSNP report and for a subset of the uploaded data.

h2. GET-Evidence variant information

You can download the *latest* snapshot of the database in TSV format.

* "latest-flat.tsv":/download/latest/flat/latest-flat.tsv includes gene, AA change, dominance, impact, #genomes, #haplomes, #articles, case/control figures for disease with max OR, etc.

You can also download the database in a more complete, but less easy-to-use, MySQL dump format.

* "get-evidence.sql.gz":get-evidence.sql.gz is a nightly MySQL dump of the entire database _including_ edit history but _excluding_ users, sessions, dbSNP, and raw web search results.

h2. BioNotate annotations

* "bionotate-history.csv.gz":bionotate-history.csv.gz is a nightly dump of the BioNotate edit history only.

h2. Source code

The GET-Evidence source code is available with git:

* git://trac.scalablecomputingexperts.com/get-evidence.git

EOF
;
go();
?>
