<?php ; // -*- mode: java; c-basic-indent: 4; tab-width: 4; indent-tabs-mode: nil; -*-

// TODO: If I understand the errors correctly, import of packages from 
// within lib directory breaks because the path is wrong. So I've removed
// those imports because they don't seem to work. -- MPB 2011/3/27

class GenomeVariant {
    public $data;

    public function __construct($variant_data=array()) {
        $this->data = array ( 'name' => '',
                              'suff_eval' => false,
                              'clinical' => '',
                              'evidence' => '',
                              'pph2_score' => '',
                              'variant_impact' => '',
                              'allele_freq' => '',
                              'expect_effect' => 0,
                              'zygosity' => '',
                              'phase' => '',
                              'variant_quality' => '',
                              'inheritance_desc' => '',
                              'summary_short' => '',
                              'autoscore' => '',
                              'autoscore_reasons' => '',
                              'n_articles' => '' );
        
        if ($variant_data) {
            $this->add_new_data($variant_data);
        }
    }

    /**
     * Transfer data to store, process to get some needed items if necessary
     *
     * Transfer is conservative -- only occurs if the target array for 
     * storage has the key and the target value evaluates as false.
     */
    public function add_new_data($new_data, &$variant_data=NULL) {
        if (! $variant_data) {
            $variant_data =& $this->data;
        }
        foreach ($variant_data as $key=>$value) {
            if (array_key_exists($key, $new_data) && ! $value) {
                $variant_data[$key] = $new_data[$key];
            }
        }
        // Get name, if needed and possible.
        if (! $variant_data['name']) {
            $has_aa_change = (array_key_exists('gene', $new_data) &&
                              array_key_exists('amino_acid_change', $new_data)
                              && $new_data['gene'] && 
                              $new_data['amino_acid_change']);
            if ($has_aa_change and !$variant_data['name']) {
                $variant_data['name'] = $new_data['gene'] . '-' . 
                    $new_data['amino_acid_change'];
            } else {
                $has_dbsnp = (array_key_exists('dbSNP', $new_data) && 
                              $new_data['dbSNP']);
                if ($has_dbsnp) {
                    $variant_data['name'] = $new_data['dbSNP'];
                }
            }
        }
        // Figure out allele frequency.
        $want_allele_freq = ( (! $variant_data['allele_freq']) && 
                              array_key_exists('num', $new_data) && 
                              array_key_exists('denom', $new_data) && 
                              $new_data['num'] && $new_data['denom']);
        if ($want_allele_freq) {
            $allele_freq = $new_data["num"] / $new_data["denom"];
            $variant_data['allele_freq'] = sprintf("%.3f", $allele_freq);
        }
        // Interpret zygosity if needed.
        $want_eval_zyg = ( (! ($variant_data['expect_effect'] &&
                               $variant_data['zygosity'] &&
                               $variant_data['inheritance_desc'] ) ) &&
                           array_key_exists('genotype', $new_data) &&
                           array_key_exists('ref_allele', $new_data));
        if ($want_eval_zyg) {
            if (array_key_exists('variant_dominance', $new_data))
                $dominance = $new_data['variant_dominance'];
            else
                $dominance = 'unknown';
            $eval_zyg_out = $this->eval_zygosity( $dominance,
                                           $new_data["genotype"],
                                           $new_data["ref_allele"] );
            $variant_data['expect_effect'] = $eval_zyg_out[0];
            $variant_data['zygosity'] = $eval_zyg_out[1];
            $variant_data['inheritance_desc'] = $eval_zyg_out[2];
        }
        // Sufficiently evaluated, and clinical & evidence modifiers.
        $want_suff_eval = ( (! $variant_data['suff_eval']) &&
                            array_key_exists('variant_quality', $new_data) &&
                            array_key_exists('variant_impact', $new_data) &&
                            $new_data['variant_quality'] && 
                            $new_data['variant_impact']);
        if ($want_suff_eval) {
            $variant_data['suff_eval'] = quality_eval_suff($new_data['variant_quality'], $new_data['variant_impact']);
            if ($variant_data['suff_eval']) {
                $variant_data['clinical'] = quality_eval_clinical($new_data['variant_quality']);
                $variant_data['evidence'] = quality_eval_evidence($new_data['variant_quality']);
            }
        }
        // Summarize autoscore reasons
        if (!@$variant_data['autoscore_why']) {
            $variant_data['autoscore_why'] = $this->autoscore_why($new_data);
        }
    }
    
    public function eval_zygosity($variant_dominance, $genotype, $ref_allele = null) {
        // 1 = expected to have effect (het dominant or hom recessive)
        // 0 = unclear ("other" inheritance or possible errors)
        // -1 = no effect expected (recessive carrier) or unknown
        $alleles = preg_split('/\//', $genotype);
        $zygosity = "Heterozygous";
        if (!array_key_exists(1,$alleles) || ($alleles[0] == $alleles[1])) {
            $zygosity = "Homozygous";
        }
        if ($variant_dominance == "dominant") {
            if ( $ref_allele and 
                 $ref_allele != $alleles[0] or $ref_allele != $alleles[1]) {
                return array (1, $zygosity, "Dominant"); // An effect is expected.
            } else {
                return array (0, $zygosity . "(matching ref??)", "Dominant"); // Error? maybe pathogenic ref? 
                // Need to have "pathogenic allele" to know.
            }
        } elseif ($variant_dominance == "other") {
            return array (0, $zygosity, "Complex/Other");
        } elseif ($variant_dominance == "recessive") {
            if ($zygosity == "Homozygous") {
                if ($ref_allele and $ref_allele == $alleles[0]) {
                    return array (0, $zygosity . "(matching ref??)", "Recessive"); // Error or pathogenic ref? see above.
                } else {
                    return array (1, $zygosity, "Recessive"); // Error or pathogenic ref? see above.
                }
            } else {
                return array (-1, "Carrier (" . $zygosity . ")", "Recessive"); // Recessive carrier
            }
        } else {
            return array (-1, $zygosity, "Unknown"); // "unknown" inheritance and other
        }
        return 0;
    }

    public function autoscore_why($data) {
        $items = array();
        if (@$data["in_omim"] && $data["in_omim"] != '-')
            $items[] = "In OMIM";
        if (@$data["in_gwas"] && $data["in_gwas"] != '-')
            $items[] = "In HuGENet GWAS";
        if (@$data["in_pharmgkb"] && $data["in_pharmgkb"] != '-')
            $items[] = "In PharmGKB";
        if (array_key_exists("webscore", $data) && $data["webscore"] != "N") {
            if ($data["webscore"] == "-") {
                $items[] = "Has unevaluated web hits";
            } elseif ($data["webscore"] == "Y") {
                $items[] = "Has confirmed web hits";
            }
        }
        if (array_key_exists("pph2_score", $data) && $data["pph2_score"]) {
            $pph2_string = "Polyphen 2: " . $data["pph2_score"];
            if ($data["pph2_score"] >= 0.85)
                $pph2_string = $pph2_string . " (probably damaging)";
            elseif ($data["pph2_score"] >= 0.2)
                $pph2_string = $pph2_string . " (possibly damaging)";
            else
                $pph2_string = $pph2_string . " (benign)";
            $items[] = $pph2_string;
        }
        if (@$data["aa_to"] || @$data["aa_ins"]) {
            // Live data from database
            if ($data["aa_to"] && ($data["aa_to"] == "X" ||
                                   $data["aa_to"] == "*"))
                $items[] = "Nonsense mutation";
            else if ($data["aa_ins"]) {
                if ($data["aa_ins"] == "Shift" || 
                    $data["aa_ins"] == "Frameshift")
                    $items[] = "Frameshift";
                else if (strlen($data["aa_del"] != strlen($data["aa_ins"])))
                    $items[] = "Frame-preserving indel";
            }
        } else {
            // Stored data from JSON report
            if ( @$data["nonsense"] ||
                 preg_match('/X$/', @$data['amino_acid_change']) || 
                 preg_match('/\*$/', @$data['amino_acid_change']) )
                $items[] = "Nonsense mutation";
            else if ( (array_key_exists("frameshift", $data) && 
                       $data["frameshift"]) ||
                      (preg_match('/Shift$/', @$data['amino_acid_change']) || 
                       preg_match('/Frameshift$/', @$data['amino_acid_change'])) )
                $items[] = "Frameshift";
            else if (! (array_key_exists("pph2_score", $data) && 
                        $data["pph2_score"])) 
                // Remainder: frame-preserving indels, large substitutions, 
                // or otherwise unknown to Polyphen 2.
                $items[] = "Polyphen 2: Unknown";
        }
        if (array_key_exists("testable", $data) && $data["testable"]) {
            if (array_key_exists("reviewed", $data) && $data["reviewed"])
                $items[] = "Testable gene in GeneTests with associated GeneReview";
            else
                $items[] = "Testable gene in GeneTests";
        }
        $returned_text = implode(", ",$items);
        return $returned_text;
    }

}

class GenomeReport {
    public $genomeID;
    public $sourcefile;
    public $processedfile;
    public $variantsfile;
    public $coveragefile;
    public $metadatafile;
    public $lockfile;
    public $logfile;

    public function __construct($genomeID) {
        $this->genomeID = $genomeID;
        $prefix = $GLOBALS['gBackendBaseDir'] . '/upload/' . $genomeID;
        $this->sourcefile = $prefix . '/genotype';
        if (! file_exists($this->sourcefile)) {
            if (file_exists($this->sourcefile . '.gz')) {
                $this->sourcefile = $this->sourcefile . '.gz';
            } else if (file_exists($this->sourcefile . '.bz2')) {
                $this->sourcefile = $this->sourcefile . '.bz2';
            } else if (file_exists($this->sourcefile . '.gff')) {
                $this->sourcefile = $this->sourcefile . '.gff';
            } else if (file_exists($this->sourcefile . '.gff.gz')) {
                $this->sourcefile = $this->sourcefile . '.gff.gz';
            } else if (file_exists($this->sourcefile . '.gff.bz2')) {
                $this->sourcefile = $this->sourcefile . '.gff.bz2';
            }
         }
        $this->processedfile = $prefix . '-out/ns.gff.gz';
        if (! file_exists($this->processedfile)) {
            $this->processedfile = $prefix . '-out/ns.gff';
        }
        $this->variantsfile = $prefix . '-out/get-evidence.json';
        $this->coveragefile = $prefix . '-out/missing_coding.json';
        $this->metadatafile = $prefix . '-out/metadata.json';
        $this->genereportfile = $prefix . '-out/get-ev_genes.json';
        $this->lockfile = $prefix . '-out/lock';
        $this->logfile = $prefix . '-out/log';
    }

    // Log files can have a long series of status lines with only numbers,
    // replace all of these but the last with "[...]".
    private function trim_log(&$log_contents) {
        $log_contents = preg_replace('{(\n#status \d+)+(\n#status \d+\n)}',
                                 "\n[...]\\2",
                                 $log_contents);
    }

    // Check log and lock files, return information on processing status.
    public function status() {
        $ret = array('progress' => 0, 'status' => 'unknown');
        $still_processing = false;
        if (file_exists($this->lockfile)) {
            $ret['logfilename'] = $this->lockfile;
            // If not writable, fuser won't work: assume lock is current.
            $fuser_test = shell_exec("fuser ''" . 
                                     escapeshellarg($this->lockfile) . 
                                     ' >/dev/null && echo -n ok');
            if (!is_writable ($this->lockfile) || "ok" == $fuser_test) {
                $still_processing = true;
                $total_steps = 100;
                foreach (file($this->lockfile) as $logline) {
                    if (preg_match ('{^#status (\d*)/?(\d*)( (.+))?}', 
                                    $logline, 
                                    $regs)) {
                        if ($regs[2] > 0) 
                            $total_steps = $regs[2];
                        $ret['progress'] = $regs[1] / $total_steps;
                        if ($regs[4])
                            $ret['status'] = $regs[4];
                    }
                }
            }
        } elseif (!file_exists($this->sourcefile)) {
            $ret['logfilename'] = '/dev/null';
            $ret['progress'] = 1;
            $ret['status'] = 'finished';
        } else {
            $ret['logfilename'] = $this->logfile;
            if (file_exists($this->logfile) && 
                file_exists($this->variantsfile)) {
                $ret['progress'] = 1;
                $ret['status'] = 'finished';
            }
        }
        if (!file_exists($ret['logfilename']) || 
            !is_readable($ret['logfilename']))
            $ret['logfilename'] = "/dev/null";
        $ret['logmtime'] = filemtime ($ret['logfilename']);
        $ret['log'] = file_get_contents($ret['logfilename']);
        $this->trim_log($ret['log']);
        $ret['log'] .= "\n\nLog file ends: ".date('r',$ret['logmtime']);
        return $ret;
    }

    /**
     * Check if user should have permission to access this genome report
     *
     * Return false if no permission, an openID string if permission.
     * If the user has access to the report because it is a PGP or Public 
     * genome, return the PGP or Public genome openID.
     * @param string $user_oid
     * @param boolean $is_admin (optional, defaults to false)
     * @return boolean|string false, true, or the string containing openID
     */
    public function permission($user_oid, $is_admin=false) {
        global $pgp_data_user, $public_data_user;
        // Currently, admins have access to all genome reports.
        if ($is_admin) 
            return true;
        $db_cmd = "SELECT * FROM private_genomes 
                   WHERE shasum=?";
        $db_query = theDb()->getAll($db_cmd, array($this->genomeID));
        // First check if logged in user has access.
        foreach ($db_query as $result) {
            if ($result['oid'] == $user_oid) {
                return $user_oid;
            }
        }
        // If no user-specific permission, check if PGP or Public data.
        foreach ($db_query as $result) {
            if ($result['oid'] == $pgp_data_user) {
                return $pgp_data_user;
            } elseif ($result['is_public'] > 0) {
                return true;
            } elseif ($result['oid'] == $public_data_user) {
                return $public_data_user;
            }
        }
        // Permission is false unless found by one of the above checks.
        return false;
    }

    /**
     * Return info and links to put at top of genome report
     * @return array
     */
    public function header_data() {
        $db_cmd = "SELECT * FROM private_genomes 
                   WHERE shasum=?";
        $db_query = theDb()->getAll ($db_cmd, array($this->genomeID));
        $head_data = array("Name" => false,
                           "Public profile" => false);

        if ($db_query[0]['is_public']) {
            $query_string = $this->genomeID;
        } else {
            $access_token = hash_hmac('md5', $this->genomeID, $GLOBALS['gSiteSecret']);
            $query_string = 'display_genome_id=' . $this->genomeID . '&access_token=' . $access_token;
        }
        $head_data['This report'] = "<a href=\"/genomes?$query_string\">" . 
            "{$_SERVER['HTTP_HOST']}/genomes?$query_string</a>";

        if ($db_query[0]["nickname"]) {
            $realname = $db_query[0]["nickname"];
            if (preg_match ('{^PGP\d+ \((.+?)\)}', $realname, $regs))
                $realname = $regs[1];
            $head_data["Name"] = htmlspecialchars($realname, ENT_QUOTES, 
                                                  "UTF-8");
        }
        $global_human_id = $db_query[0]['global_human_id'];
        if (preg_match ('{^hu[A-F0-9]+$}', $global_human_id)) {
            $hu = false;
            // $hu = json_decode(file_get_contents("http://my.personalgenomes.org/api/get/$global_human_id"), true);
            if ($hu && isset($hu["realname"]))
                $head_data["Name"] = $hu["realname"];
            $url = "https://my.personalgenomes.org/profile/$global_human_id";
            $head_data['public profile'] = "<a href=\"" . 
                htmlspecialchars($url) . "\">" . 
                preg_replace('{^https?://}', '', $url) . "</a>";
        }
        $data_size = @filesize ($this->sourcefile);
        if ($data_size) {
            $head_data["Download"] = "<a href=\"/genome_download.php?" . 
                "download_genome_id=" . $this->genomeID . 
                "&amp;download_nickname=" . urlencode($realname) . 
                "\">source data</a> (" . humanreadable_size($data_size) . ")";
        }
        $outdir = $GLOBALS["gBackendBaseDir"]."/upload/" . $this->genomeID . 
            "-out";
        if (file_exists ($this->processedfile)) {
            if (isset($head_data["Download"]))
                $head_data["Download"] .= ", ";
            else $head_data["Download"] = "";
            $access_token_if_needed = isset($access_token) ? ('&amp;access_token=' . $access_token) : '';
            $head_data["Download"] .= "<a href=\"/genome_download.php?" .
                "download_type=ns&amp;download_genome_id=" . $this->genomeID . 
                "&amp;download_nickname=" . urlencode($realname) .
                $access_token_if_needed .
                "\">dbSNP and nsSNP report</a> (" . 
                humanreadable_size(@filesize($this->processedfile)) . ")";
        }
        return $head_data;
    }

    /**
     * Read file and return metadata for genome
     * @result array
     */
    public function &metadata() {
        $fh = @fopen($this->metadatafile, 'r');
        if (!$fh) { $out = false; return $out; }
        $line = fgets($fh);
        $metadata = json_decode($line, true);
        fclose ($fh);
        return $metadata;
    }

    /**
     * Read gene report file for genome and return data
     * @result array
     */
    public function &gene_report() {
        $fh = @fopen($this->genereportfile, 'r');
        if (!$fh) { $out = false; return $out; }
        $gene_data = array();
        while (($genedataline = fgets($fh)) !== false) {
            $gene = json_decode($genedataline, true);
            $variants = $gene['data'];
            $gene['data'] = array();
            foreach ($variants as $vardata) {
                $vardata = new GenomeVariant($vardata);
                $gene['data'][] = $vardata->data;
            }
            $gene_data[] = $gene;
        }
        fclose ($fh);
        usort($gene_data, array($this, "cmp_gene_data"));
        $gene_data = array_reverse($gene_data);
        return $gene_data;
    }

    /**
     * Sort function for gene report data
     * @result int
     */
    private function cmp_gene_data($genedataA, $genedataB) {
        if ($genedataA['effect_rank'] < $genedataB['effect_rank']) {
            return -1;
        } elseif ($genedataA['effect_rank'] > $genedataB['effect_rank']) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Read file and return data for missing coding regions
     * @result array
     */
    public function &coverage_data() {
        $fh = @fopen($this->coveragefile, 'r');
        if (!$fh) { $out = false; return $out; }
        $missing = 0;
        $length = 0;
        $Ymissing = 0;
        $Ylength = 0;
        $out = array();
        while (($injson = fgets($fh)) !== false) {
            $gene = json_decode($injson, true);
            // In case _random etc. are not already filtered out upstream...
            if (strpos($gene['chr'], '_') !== false)
                continue;

            $length += $gene['length'];
            if ($gene['chr'] == 'chrY') {
                $Ylength += $gene['length'];
                $Ymissing += $gene['missing'];
            }
            if ($gene['missing'] == 0)
                continue;
            $missing += $gene['missing'];
            if (!@$gene['clin_test'])
                continue;
            $out[] = $gene;
        }
        fclose ($fh);
        // TODO: use metadata at hand to tell us whether this person
        // has a chrY. We're assuming that "zero coverage of Y chromosome"
        // means "no Y chromosome".     
        if ($Ymissing == $Ylength) {
            $missing -= $Ymissing;
            $length -= $Ylength;
            $i=count($out);
            while ($i>0)
                if ($out[--$i]['chr'] == 'chrY')
                    array_splice ($out, $i, 1);
        }
        $out = array ('genes' => $out,
                      'missing' => $missing,
                      'length' => $length);
        return $out;
    }

    /**
     * Read list of variant_ids, look up in db, return list of data arrays
     * @param array keys are variant_ids, values an array containing some var data
     * @return array List of GenomeVariant objects
     */
    public function variants_lookup($variants_data) {
        // Look up data in database
        $variant_ids = array_keys($variants_data);
        $combine_for_db_query = "(" . join(',', $variant_ids) . ")";
        $db_cmd = "SELECT flat_summary.variant_id as variant_id,
                       snap_latest.variant_quality as variant_quality,
                       snap_latest.summary_short as summary_short,
                       flat_summary.flat_summary as flat_summary
                   FROM flat_summary
                   LEFT JOIN snap_latest
                   ON flat_summary.variant_id=snap_latest.variant_id
                   AND snap_latest.article_pmid=0 AND snap_latest.genome_id=0
                   AND snap_latest.disease_id=0
                   WHERE flat_summary.variant_id IN $combine_for_db_query";
        $db_query = theDb()->getAll($db_cmd);
        // Store data to return when done.
        // Each value in variant_data should already contain (if exists): 
        // 'genotype', 'ref_allele', 'chromosome', and 'coordinates'
        $name_map = array ('gene' => 'gene',
                           'aa_change_short' => 'amino_acid_change',
                           'impact' => 'variant_impact',
                           'inheritance' => 'variant_dominance',
                           'pph2_score' => 'pph2_score',
                           'genetests_testable' => 'testable',
                           'genetests_reviewed' => 'reviewed',
                           'autoscore' => 'autoscore',
                           'aa_to' => 'aa_to',
                           'aa_ins' => 'aa_ins',
                           'dbsnp_id' => 'dbSNP',
                           'webscore' => 'webscore',
                           'overall_frequency' => 'allele_freq',
                           'overall_frequency_n' => 'num',
                           'overall_frequency_d' => 'denom',
                           'in_omim' => 'in_omim',
                           'in_pharmgkb' => 'in_pharmgkb',
                           'in_gwas' => 'in_gwas',
                           'n_articles' => 'n_articles',
                           );
        $ret = array();
        foreach ($db_query as $result) {
            if ($flat_summary = $result['flat_summary']) {
                $vardata = $variants_data[$result['variant_id']];
                $vardata['variant_quality'] = $result['variant_quality'];
                if ($result['summary_short']) {
                    $vardata['summary_short'] = $result['summary_short'];
                }
                // Pull data from flat_summary if not empty
                $flatdata = json_decode ($flat_summary, true);
                foreach ($flatdata as $key => $value) {
                    if (array_key_exists($key, $name_map) && 
                        $value && ! ($value == "-" && $key != 'webscore')) {
                        // note -- "-" is meaningful for webscore, empty elsewhere
                        if ($value == "Y" && 
                            ($key == 'genetests_testable' || 
                             $key == 'genetests_reviewed')) {
                            $vardata[$name_map[$key]] = true;
                        } else {
                            $vardata[$name_map[$key]] = $value;
                        }
                    }
                }
                $variant = new GenomeVariant($vardata);
                $ret[] = $variant;
            }
        }
        return $ret;
    }

    /**
     * Read file containing GET-Evidence hits and other significant variants
     * Return an array containing two keys:
     * 'suff' => list of arrays each w/ sufficiently-evaluated variant data
     * 'insuff' => list of arrays each w/ insufficient-evaluated variant data
     * @return array
     */
    public function &variants($options=array()) {
        if (!file_exists($this->variantsfile))
            return null;
        $no_insuff = array_key_exists('no_insuff', $options);
        $variants = array('suff' => array(), 'insuff' => array());
        $getev_variants = array();
        if ($no_insuff)
            exec("fgrep -v '\"suff_eval\":false' " .
                 escapeshellarg($this->variantsfile),
                 $lines);
        else
            $lines = file($this->variantsfile);
        foreach ($lines as $line) {
            $variant_data = json_decode($line, true);
            if (!is_array($variant_data))
                // sometimes we can't read python's json??
                continue;
            // If variant_id exists, variant is in GET-Evidence - 
            // store necessary data and look up later.
            if (array_key_exists('variant_id', $variant_data)) {
                if (!($variant_data['autoscore'] > 0 ||
                      $variant_data['suff_eval']))
                    continue;
                $want = array('genotype', 'ref_allele', 'chromosome',
                              'coordinates');
                $vardata_to_pass = array();
                foreach ($want as $key) {
                    if (array_key_exists($key, $variant_data)) {
                        $vardata_to_pass[$key] = $variant_data[$key];
                    }
                }
                $getev_variants[$variant_data['variant_id']] = $vardata_to_pass;
                continue;
            }
            // Otherwise process & store. Var is necessarily insuff if not in GET-Ev.
            if (!$no_insuff) {
                $variant = new GenomeVariant($variant_data);
                $variants['insuff'][] =& $variant->data;
            }
        }
        // Grab data for GET-Evidence variants from the database.
        $getev_var_matched = $this->variants_lookup($getev_variants);
        foreach ($getev_var_matched as $variant) {
            if ($variant->data['suff_eval']) {
                $variants['suff'][] =& $variant->data;
            } else if (!$no_insuff && $variant->data['autoscore'] > 0) {
                $variants['insuff'][] =& $variant->data;
            }
        }
        return $variants;
    }    
}

function genome_display($shasum, $oid, $is_admin=false, $options=array()) {
    $genome_report = new GenomeReport($shasum);
    $results = $genome_report->status();
    $permission = $genome_report->permission($oid, $is_admin);
    if (!$permission) {
        $returned_text = "<p>Sorry, you don't seem to have permission to " .
            "view this genome report. Perhaps you got logged off?</p>";
        return $returned_text;
    } 
    $header_data = $genome_report->header_data();
    $qrealname = htmlspecialchars($header_data["Name"], ENT_QUOTES, "UTF-8");
    $GLOBALS["gOut"]["title"] = $qrealname." - GET-Evidence variant report";
    $returned_text = "<h1>Variant report for ".htmlspecialchars($qrealname,ENT_QUOTES,"UTF-8")."</h1><ul>";
    $header_data = $genome_report->header_data();
    foreach ($header_data as $k => $v)
        if ($v)
            $returned_text .= "<li>$k: $v</li>\n";
    if ($results["progress"] < 1) {
        $returned_text .= "<li>Processing status: &nbsp; <div style='margin:3px 0 -3px 0;display:inline-block;height:12px;' id='variant_report_progress' initial-value='{$results['progress']}'></div> &nbsp; <div style='display:inline' id='variant_report_status'>{$results['status']}</div><input type='hidden' id='display_genome_id' value='{$shasum}' /><input type='hidden' id='access_token' value='{$_REQUEST[access_token]}' /></li>\n";
    }
    $logfile = $results["logfilename"];
    $log = $results["log"];
    if ($logfile != '/dev/null') {
        $returned_text .= "<li><A id=\"showdebuginfo\" href=\"#\" onclick=\"jQuery('#debuginfo').toggleClass('ui-helper-hidden');jQuery('#showdebuginfo').html('Show/hide debugging info');return false;\">Show debugging info</A></li>\n";
    }
    $returned_text .= "</ul>\n";
    $returned_text .= "<DIV id='debuginfo' class='ui-helper-hidden'><BLOCKQUOTE><PRE id='debuginfotext'>Log file: ".$logfile."\n\n".htmlspecialchars($log,ENT_QUOTES,"UTF-8")."\n\n</PRE></BLOCKQUOTE></DIV>\n";

    $variants =& $genome_report->variants($options);
    if (is_array($variants)) {
        $coverage =& $genome_report->coverage_data();
        $metadata =& $genome_report->metadata();
        $gene_report =& $genome_report->gene_report();

        $returned_text .= "<div id='variant_table_tabs'><ul>\n"
            . "<li><A href='#variant_table_tab_0'>Genome report</A></li>\n"
            . "<li><A href='#variant_table_tab_1'>Insufficiently evaluated variants<div class='ajax_loader_image ui-helper-hidden' style='margin-left: 3px'></div></a></li>\n";
        if ($coverage)
            $returned_text .=
                "<li><A href='#variant_table_tab_2'>Coverage</A></li>\n";
        if ($gene_report)
            $returned_text .=
                "<li><A href='#variant_table_tab_3'>Gene Report</A></li>\n";
        if ($metadata)
            $returned_text .=
                "<li><A href='#variant_table_tab_4'>Metadata</A></li>\n";
        $returned_text .=
            "</ul>\n"
            . "<div id='variant_table_tab_0'>";

        $returned_text .= "<div style='float:right; margin-bottom: 3px' id='variant_filter_radio'>
<input type='radio' name='variant_filter_radio' id='variant_filter_radio0' checked /><label for='variant_filter_radio0'>Show rare (<i>f</i><10%) pathogenic variants</label>
<input type='radio' name='variant_filter_radio' id='variant_filter_radio1' /><label for='variant_filter_radio1'>Show all</label>
</div><br clear=all />";

        usort($variants['suff'], "sort_variants");
        $returned_text .= "<TABLE class='report_table variant_table datatables_please' datatables_name='variant_table'><THEAD><TR>"
            . "<TH class='Invisible ui-helper-hidden'>Row number</TH>"
            . "<TH>Variant</TH>"
            . "<TH class='SortImportance SortDescFirst'>Clinical<BR />Importance</TH>"
            . "<TH class='SortEvidence Invisible'>Evidence</TH>"
            . "<TH class='SortDescFirst'>Impact</TH>"
            . "<TH class='RenderFreq'>Allele<BR />freq</TH>"
            . "<TH class='Unsortable'>Summary</TH>"
            . "<TH class='Invisible ui-helper-hidden'>Sufficient</TH>"
            . "</TR></THEAD><TBODY>\n";
        $rownumber = 0;
        foreach ($variants['suff'] as $variant) {
            ++$rownumber;
            $returned_text .= "<TR><TD class='ui-helper-hidden'>$rownumber</TD>"
                . "<TD><A HREF=\""
                . $variant["name"] . "\">" . $variant["name"] . "</A></TD><TD>"
                . $variant["clinical"] . "</TD><TD>"
                . $variant["evidence"] . "</TD><TD>"
                . $variant["evidence"]
                . " " . $variant["variant_impact"] . "<br /><br />"
                . $variant["inheritance_desc"] . ", " . $variant["zygosity"] . "</TD><TD>"
                . $variant["allele_freq"] . "</TD><TD>"
                . $variant["summary_short"] . "</TD><TD class='ui-helper-hidden'>"
                . $variant["suff_eval"] . "</TD></TR>\n";
        }
        $returned_text .= "</TBODY></TABLE>\n";

        $returned_text .= "</div>\n<div id='variant_table_tab_1'>\n";

        usort($variants['insuff'], "sort_variants");
        $insuff_table = "<TABLE class='report_table variant_table datatables_please' datatables_name='variant_table_insuff' ajax_source='/genomes?display_genome_id=$shasum&access_token=$_REQUEST[access_token]&part=insuff'><THEAD><TR>"
            . "<TH class='Invisible ui-helper-hidden'>Row number</TH>"
            . "<TH>Variant</TH>"
            . "<TH class='SortNumeric SortDescFirst'>Prioritization score</TH>"
            . "<TH class='RenderFreq'>Allele<BR />freq</TH>"
            . "<TH class='Unsortable'>Num of<BR />articles</TH>"
            . "<TH class='Unsortable'>Zygosity and Prioritization Score Reasons</TH>"
            . "<TH class='Invisible ui-helper-hidden'>Sufficient</TH>"
            . "</TR></THEAD><TBODY>\n";
        $rownumber = 0;
        foreach ($variants['insuff'] as $variant) {
            ++$rownumber;
            $insuff_table .= "<TR><TD class='ui-helper-hidden'>$rownumber</TD>"
                . "<TD><A HREF=\""
                . $variant["name"] . "\">" . $variant["name"] . "</A></TD><TD>"
                . $variant["autoscore"]. "</TD><TD>"
                . $variant["allele_freq"] . "</TD><TD>"
                . $variant["n_articles"] . "</TD><TD>"
                . $variant["zygosity"] . ". "
                . $variant["autoscore_why"] 
                . "</TD><TD class='ui-helper-hidden'>"
                . $variant["suff_eval"] . "</TD></TR>\n";
        }
        $insuff_table .= "</TBODY></TABLE>\n";

        if (array_key_exists('only_insuff', $options))
            return $insuff_table;

        $returned_text .= $insuff_table;

        if ($coverage) {
            $returned_text .= "</div>\n<div id='variant_table_tab_2'>\n";

            $returned_text .= '<P>Exome coverage: '
                . ($coverage['length'] - $coverage['missing'])
                . ' / '
                . $coverage['length']
                . ' = '
                . sprintf ('%.2f', 100*(1-($coverage['missing'] / $coverage['length'])))
                . '%</P>';
            $returned_text .= "<TABLE class='report_table variant_table datatables_please' datatables_name='variant_table_coverage' style='width: 100%'><THEAD><TR>"
                . "<TH class='Invisible ui-helper-hidden'>Row number</TH>"
                . "<TH>Gene</TH>"
                . "<TH class='SortChromosome'>Chromosome</TH>"
                . "<TH class='RenderFreq'>Coverage</TH>"
                . "<TH class='SortNumeric'>Missing</TH>"
                . "<TH class='SortNumeric'>Length</TH>"
                . "<TH class='Unsortable'>Missing regions</TH>"
                . "</TR></THEAD><TBODY>\n";
            $rownumber = 0;
            foreach ($coverage['genes'] as $gene) {
                ++$rownumber;
                $returned_text .= '<TR><TD class="ui-helper-hidden">'
                    . $rownumber . '</TD><TD><A HREF="report?type=search&q='
                    . $gene['gene'] . '">' . $gene['gene'] . '</A></TD><TD>'
                    . str_replace('chr','',$gene['chr']) . '</TD><TD>'
                    . ($gene['length']>0 ? (1-($gene['missing']/$gene['length'])) : '-')
                    . '</TD><TD>'
                    . $gene['missing'] . '</TD><TD>'
                    . $gene['length'] . '</TD><TD>'
                    . preg_replace('{\b(\d+)-(\1)\b}', '\1', $gene['missing_regions']) . '</TD></TR>' . "\n";
            }
            $returned_text .= '</TBODY></TABLE>' . "\n";
        }
        if ($gene_report) {
            $returned_text .= "</div>\n<div id='variant_table_tab_3'>\n";
            $returned_text .= "<TABLE class='report_table variant_table datatables_please' datatables_name='variant_table_genereport' style='width: 100%'><THEAD><TR>" .
                "<TH class='Invisible ui-helper-hidden'>Effect rank</TH>" .
                "<TH class='Unsortable'>Variant</TH>" .
                "<TH class='Unsortable'>Phase/<BR />Zygosity</TH>" .
                "<TH class='Unsortable'>Allele freq</TH>" .
                "<TH class='Unsortable'>Impact</TH>" .
                "<TH class='Unsortable'>Evaluation</TH>" .
                "<TH class='Unsortable'>Summary / Info</TH>" .
                "</TR></THEAD><TBODY>\n";
            foreach ($gene_report as $gene_data) {
                foreach ($gene_data['data'] as $variant) {
                    if ($variant['phase'] != 'homozygous' && 
                        $variant['phase'] != 'het unknown')
                        $variant['phase'] = 'het ' . $variant['phase'];
                    $returned_text .= "<TR>" . 
                        '<TD class="ui-helper-hidden">' . 
                            $gene_data['effect_rank'] . '</TD>' .
                        '<TD><A HREF="' . $variant['name'] . '">' . 
                            $variant['name'] . '</A></TD>' .
                        '<TD>' . $variant['phase'] . '</TD>' .
                        '<TD>' . $variant['allele_freq'] . '</TD>' .
                        '<TD>' . $variant['inheritance_desc'] . '<BR />' .
                            $variant["variant_impact"] . '</TD>';
                    if ($variant['clinical'] and $variant['evidence']) {
                        $returned_text .= "<TD>" . $variant["clinical"] . 
                               " clinical importance, <BR />" . 
                               strtolower($variant["evidence"]) . '</TD>' .
                            '<TD>' . $variant['summary_short'] . '</TD>';
                    } else {
                        $returned_text .= '<TD>Insufficiently evaluated</TD>' .
                            '<TD>Prioritization score: ' . $variant['autoscore'] . 
                            '<BR />' . $variant['autoscore_why'] . '</TD>';
                    }
                }
            }
            $returned_text .= "</TBODY></TABLE>\n";
        }
        if ($metadata) {
            $returned_text .= "</div>\n<div id='variant_table_tab_4'>\n";
            if (array_key_exists('input_type', $metadata)) {
                $returned_text .= '<p>Input file format: ' .
                    $metadata['input_type'] . '</p>' . "\n";
            }
            if (array_key_exists('genome_build', $metadata)) {
                $returned_text .= '<p>Genome build: ' .
                    $metadata['genome_build'] . '</p>' . "\n";
            }
            if (array_key_exists('called_num', $metadata) &&
                array_key_exists('ref_nogap_num', $metadata) &&
                array_key_exists('ref_all_num', $metadata)) {
                $perc_callable = $metadata['called_num'] * 100.0 / 
                    $metadata['ref_nogap_num'];
                $perc_total = $metadata['called_num'] * 100.0 / 
                    $metadata['ref_all_num'];
                $returned_text .= '<p>Genome coverage: ' .
                    number_format($metadata['called_num']) .
                    ' bases (' . number_format($perc_callable, 1) .
                    '% of callable positions, ' . 
                    number_format($perc_total, 1) .
                    '% of total positions)</p>' . "\n";
            }
            if (array_key_exists('called_coding_n', $metadata) &&
                array_key_exists('ref_coding_n', $metadata)) {
                $perc_coding = $metadata['called_coding_n'] * 100.0 /
                    $metadata['ref_coding_n'];
                if (array_key_exists('called_coding_clintest_n', $metadata) &&
                    array_key_exists('ref_coding_clintest_n', $metadata)) {
                    $perc_clintest = $metadata['called_coding_clintest_n'] * 
                        100.0 / $metadata['ref_coding_clintest_n'];
                    $returned_text .= '<p>Coding region coverage: ' .
                        number_format($metadata['called_coding_n']) .
                        ' bases (' . number_format($perc_coding, 1) .
                        '% of all genes, ' . number_format($perc_clintest, 1) .
                        '% of genes with clinical testing available)</p>' . 
                        "\n";
                } else {
                    $returned_text .= '<p>Coding region coverage: ' .
                        number_format($metadata['called_coding_n']) .
                        ' bases (' . number_format($perc_coding, 1) .
                        '% of all genes)</p>' . "\n";
                }
            }
            if (array_key_exists('chromosomes', $metadata)) {
                $returned_text .= '<p>Chromosomes: ' . 
                    join(', ', $metadata['chromosomes']) . '</p>' . "\n";
            }
            $returned_text .= "\n";
        }
        $returned_text .= "</div></div>\n";
    }
    return($returned_text);
}

function sort_variants($a, $b) {
    if ($a['suff_eval'] && $b['suff_eval'])
        return sort_reviewed ($a, $b);
    if ($a['suff_eval'])
        return -1;
    if ($b['suff_eval'])
        return 1;
    return sort_by_autoscore ($a, $b);
}

function sort_reviewed($a, $b) {
    $impact_sort_order = array("pathogenic", "pharmacogenetic",
                                "protective", "benign");
    $clinical_sort_order = array("High", "Moderate", "Low");
    $evidence_sort_order = array("Well-established", "Likely", "Uncertain");
    $cmpa = array_search($a['variant_impact'], $impact_sort_order);
    $cmpb = array_search($b['variant_impact'], $impact_sort_order);
    if ($cmpa < $cmpb) { return -1; }
    if ($cmpa > $cmpb) { return 1; }
    $cmpa = array_search($a['clinical'], $clinical_sort_order);
    $cmpb = array_search($b['clinical'], $clinical_sort_order);
    if ($cmpa < $cmpb) { return -1; }
    if ($cmpa > $cmpb) { return 1; }
    $cmpa = array_search($a['evidence'], $evidence_sort_order);
    $cmpb = array_search($b['evidence'], $evidence_sort_order);
    if ($cmpa < $cmpb) { return -1; }
    if ($cmpa > $cmpb) { return 1; }
    if ($a['expect_effect'] > $b['expect_effect']) { return -1; }
    if ($a['expect_effect'] < $b['expect_effect']) { return 1; }
    return 0;
}

function sort_by_autoscore($a, $b) {
    if ($a['autoscore'] == $b['autoscore']) {
        if (!array_key_exists('gene',$a)) $a['gene'] = 0;
        if (!array_key_exists('gene',$b)) $b['gene'] = 0;
        if (!array_key_exists('amino_acid_change',$a)) $a['amino_acid_change'] = 0;
        if (!array_key_exists('amino_acid_change',$b)) $b['amino_acid_change'] = 0;
        return strnatcmp($a['gene']."-".$a['amino_acid_change'],
                        $b['gene']."-".$a['amino_acid_change']);
    } else {
        return ($a['autoscore'] > $b['autoscore']) ? -1: 1;
    }
}


?>
