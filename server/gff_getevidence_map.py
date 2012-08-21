#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""
usage: %prog nssnp.gff getev_flatfile.json[.gz] [output_file]
"""

# Match to GET-Evidence's JSON flatfile dump, 
# Output data for genome report in JSON format

import simplejson as json
import gzip
import os
import re
import sys
import datetime
from utils import autozip, doc_optparse, gff
from config_names import GENETESTS_DATA
from utils.substitution_matrix import blosum100

def get_name_map():
    """Return mapping of GET-Evidence data

    Keys are keys for data in the /public/getev-latest.json flat file.
    Values are keys to used in the get-evidence.json file outputed for a given 
    genome, to be interpreted by genome_display.php.
    """
    name_map = { 'overall_frequency_n': 'num',
                 'overall_frequency_d': 'denom',
                 'pph2_score': 'pph2_score',
                 'impact': 'variant_impact',
                 'summary_short': 'summary_short',
                 'quality_scores': 'quality_scores',
                 'variant_quality': 'variant_quality',
                 'inheritance': 'variant_dominance',
                 'variant_id': 'variant_id',
                 'n_articles': 'n_articles',
                 'n_web_pos': 'n_web_pos',
                 'n_web_uneval': 'n_web_uneval',
                 'n_web_neg': 'n_web_neg',
                 'suff_eval': 'suff_eval',
                 }
    return name_map

def read_getev_flat(getev_flatfile):
    """Load GET-Evidence data into two dicts, which are returned.
 
    Read in a JSON-formatted file containing GET-Evidence data (uncompressed 
    or gzip-compressed). Return two dicts containing variant data needed for 
    making a genome report: the first with gene and amino acid change as key, 
    the second with dbSNP ID as key.
    """
    # Set up input. Default assumes str generator.
    f_in = getev_flatfile
    if isinstance(getev_flatfile, str):
        if re.search(r'\.gz$', getev_flatfile):
            f_in = gzip.open(getev_flatfile)
        else:
            f_in = open(getev_flatfile)

    # Pull only these items from the GET-Evidence json data:
    items_wanted = get_name_map().keys() + ['gene', 
                                            'aa_change_short', 
                                            'dbsnp_id']

    # Create two dicts to be returned, storing data we want.
    # If possible, getev_by_aa is used with gene and amino acid change as key.
    # Otherwise, if possible getev_by_dbsnp is used with dbSNP ID as key.
    getev_by_aa = dict()
    getev_by_dbsnp = dict()
    for line in f_in:
        data = json.loads(line)

        # Fallback to get 'quality_scores' array, if not in loaded data.
        if not 'quality_scores' in data:
            data['quality_scores'] = []
            for qtype in ['in_silico', 'in_vitro', 'case_control', 'familial', 
                      'severity', 'treatability', 'penetrance']:
                qkey = 'qualityscore_' + qtype
                if (qkey) in data:
                    data['quality_scores'].append(data[qkey])
                else:
                    data['quality_scores'].append('-')

        # Store GET-Evidence data by amino acid change and dbSNP ID.
        stored_data = dict()
        for item in items_wanted:
            if item in data and data[item]:
                stored_data[item] = data[item]
        if 'gene' in stored_data and 'aa_change_short' in stored_data:
            aa_key = stored_data['gene'] + '-' + stored_data['aa_change_short']
            getev_by_aa[aa_key] = stored_data
        elif 'dbsnp_id' in stored_data:
            dbsnp_key = stored_data['dbsnp_id']
            getev_by_dbsnp[dbsnp_key] = stored_data
    return getev_by_aa, getev_by_dbsnp


def read_genetests(genetests_file):
    """Return two sets of genes with clinical testing and associated review

    Read in a text file with GeneTests data, return two sets of gene names.
    The first set is all gene names with clinical testing available.
    The second is a subset of the first, which also have an associated review.
    """
    genes_clin = set()
    genes_rev = set()
    f_in = open(genetests_file)
    for line in f_in:
        data = line.rstrip('\n').split('\t')

        # data[5] can contain 'Clinical', 'Research', 'Research|Clinical', etc.
        if re.search('Clinical', data[5]):
            # data[4] contains gene names, split by a '|' character
            genes = data[4].strip().split('|')
            for gene in genes: 
                genes_clin.add(gene)
    f_in.close()
    # Check for review separately: sometimes these are on different lines.
    f_in = open(genetests_file)
    for line in f_in:
        data = line.rstrip('\n').split('\t')
        # If data[6] isn't 'na', it's a link to an associated review.
        if data[6] != 'na':
            genes = data[4].strip().split('|')
            for gene in genes:
                if gene in genes_clin:
                    genes_rev.add(gene)
    return genes_clin, genes_rev

def read_transcripts(transcript_file):
    """Return a dict containing transcript data with UCSC ID as key"""
    transcripts = dict()
    f_in = open(transcript_file)
    for line in f_in:
        data = line.rstrip('\n').split()
        transcript_data = { 'chrom': data[2],
                            'start': int(data[4]),
                            'end': int(data[5]) }
        transcripts[data[1]] = transcript_data
    return transcripts

def copy_output_data(getev_data, output_data):
    """Copy data to output using names recognized by genome_display.php"""
    name_map = get_name_map()
    for name in name_map:
        if name in getev_data:
            output_data[name_map[name]] = getev_data[name]
    in_database = [ 'in_omim', 'in_gwas', 'in_pharmgkb' ]
    for item in in_database:
        if item in getev_data and getev_data[item] == 'Y':
            output_data[item] = True


def parse_aa_change(aa_change):
    """Parse an amino acid change to get aa before, position, and aa after

    Amino acid changes are a concatenation of amino acid "before" 
    (or "from", matching reference), followed by codon position, finishing 
    with the amino acid "after" (or "to", describing the variant).

    Amino acids must be single letter code format, with X or * representing
    a stop codon. "Shift" and "Del" are also accepted in for the aa after.
    Examples: 
        A13T (single aa substitution)
        CP42WT (two aa sub)
        F508Del (single aa deletion)
        F508FC (single aa insertion)
        M4* (nonsense mutation)
        C232Shift (frameshift)
    """
    aa_from = aa_pos = aa_to = None
    re_aa_pos = r'([0-9]+)'
    re_aa_from = r'([ACDEFGHIKLMNPQRSTVWXY\*]+)'
    re_aa_to = r'([ACDEFGHIKLMNPQRSTVWXY\*]+|Shift|Del)'
    re_aa = r'^' + re_aa_from + re_aa_pos + re_aa_to + r'$'
    if re.search(re_aa, aa_change): 
        (aa_from, aa_pos, aa_to) = re.search(re_aa, aa_change).groups()
    else:
        sys.exit("ERROR! No match for: " + aa_change)
    return aa_from, aa_pos, aa_to


def autoscore(data, blosum=None, aa_from=None, aa_to=None):
    """Calculate autoscore from data

    This function looks at the input, which should be a dict, and adds points
    to autoscore if that input indicates the variant was found in a database.
    If optional blosum, aa_from, and aa_to arguments are given, these are 
    examined and score is added based on a prediction of disruptive effect.
    """
    # Set up three different autoscore categories, each will have a max of 2.
    score_var_database = 0
    score_gene_database = 0
    score_comp = 0
    
    # Add scores from variant specific databases.
    if "in_omim" in data and data["in_omim"]:
        score_var_database += 2
    if "in_gwas" in data and data["in_gwas"]:
        score_var_database += 1
    if "in_pharmgkb" in data and data["in_pharmgkb"]:
        score_var_database += 1
    if ('n_web_pos' in data or 'n_web_uneval' in data):
        score_var_database += 1

    # Add scores from gene specific databases.
    if "testable" in data and data["testable"] == 1:
        if "reviewed" in data and data["reviewed"] == 1:
            score_gene_database += 2
        else:
            score_gene_database += 1
    
    # Add scores for computational prediction of disruptive effect if possible.
    if aa_to and re.search(r'Del', aa_to):
        score_comp = 1
        data["indel"] = True
    elif aa_to and re.search(r'Shift', aa_to):
        score_comp = 2
        data["frameshift"] = True
    elif aa_from and aa_to and (re.match('\*', aa_to) or re.match('X', aa_to)):
        score_comp = 2
        data["nonsense"] = True
    elif aa_from and aa_to and len(aa_from) != len(aa_to):
        score_comp = 1
        data["indel"] = True
    elif aa_from and aa_to:
        if ('pph2_score' in data and data['pph2_score'] and 
            data['pph2_score'] != '-'):
            if float(data['pph2_score']) >= 0.85:
                score_comp = 2
            elif float(data['pph2_score']) >= 0.2:
                score_comp = 1
        else:
            score_comp = 1
    # Less one point if allele frequency is greater than 5%
    if ('num' in data and 'denom' in data and 
        ((int(data['num']) * 1.0) / int(data['denom']) > 0.05) and
        score_comp > 0):
        score_comp = score_comp - 1

    # Make sure none exceed 2, then return the sum (which is the autoscore).
    score_var_database = min(2, score_var_database)
    score_gene_database = min(2, score_gene_database)
    score_comp = min(2, score_comp)
    return score_var_database + score_gene_database + score_comp


def suff_eval(variant_data):
    """Return a boolean testing if GET-Evidence entry is sufficiently evaluated

    Scores should be a seven character string, each position as follows:
    0 - computational evidence
    1 - functional evidence
    2 - case/control evidence
    3 - familial evidence
    4 - clinical severity
    5 - clinical treatability
    6 - disease penetrance
    """
    # Check that we have the data we need, else return "False"
    if not ("variant_impact" in variant_data):
        return False
    quality_scores = variant_data["quality_scores"]
    if (not quality_scores) or len(quality_scores) < 7:
        return False

    impact = variant_data["variant_impact"]
    # Must have either case_control or familial data
    if quality_scores[2] == "-" and quality_scores[3] == "-":
        return False

    # Last three scores don't matter if variant is benign or protective.
    is_not_disease = (impact == "benign" or impact == "protective")
    num_evidence_eval = 4 - quality_scores[0:4].count('-')
    num_total_eval = 7 - quality_scores[0:7].count('-')
    if is_not_disease and num_evidence_eval >= 2:
        return True
    # Otherwise needs at least 4 categories including severity and penetrance.
    else:
        if num_total_eval < 4:
            return False
        if quality_scores[4] == "-" or quality_scores[6] == "-":
            return False
        return True

def eval_scores(quality_scores):
    scores = list(quality_scores)
    for i in range(len(scores)):
        if scores[i] == '-':
            scores[i] = 0
        elif scores[i] == '!':
            scores[i] = -1
        else:
            scores[i] = int(scores[i])
    if (scores[6] >= 4 and (scores[4] >= 4 or
                            (scores[4] >= 3 and scores[5] >= 4))):
        clin_importance = "High"
    elif (scores[6] >= 3 and (scores[4] >= 3 or
                              (scores[4] >= 2 and scores[5] >= 3))):
        clin_importance = "Moderate"
    else:
        clin_importance = "Low"
    evidence_sum = scores[0] + scores[1] + scores[2] + scores[3]
    if ( (scores[2] >= 4 or scores[3] >= 4) and evidence_sum >= 8 ):
        evidence_eval = "Well-established"
    elif ( (scores[2] >= 3 or scores[3] >= 3) and evidence_sum >= 5 ):
        evidence_eval = "Likely"
    else:
        evidence_eval = "Uncertain";
    return (evidence_eval, clin_importance)

def impact_rank(variant):
    # Impact rank is my vague attempt to sort evaluated and autoscored
    # variants together. I'm not happy with it, but needed something to
    # combine for gene level ranking.  -- MPB 5/11
    impact_rank = 0
    if suff_eval(variant):
        evidence_eval, clin_importance = eval_scores(variant['quality_scores'])
        if evidence_eval == 'Well-established':
            impact_rank += 2
        elif evidence_eval == 'Likely':
            impact_rank += 1
        if clin_importance == 'High':
            impact_rank += 2
        if clin_importance == 'Moderate':
            impact_rank += 1
        if variant['variant_impact'] == 'pathogenic':
            impact_rank += 2
        elif (variant['variant_impact'] == 'protective' or
              variant['variant_impact'] == 'pharmacogenetic'):
            impact_rank += 1
    else:
        if 'variant_impact' in variant:
            if variant['variant_impact'] == 'pathogenic':
                impact_rank += 3
            elif (variant['variant_impact'] == 'protective' or
                  variant['variant_impact'] == 'pharmacogenetic'):
                impact_rank += 1
            elif (variant['variant_impact'] == 'benign'):
                impact_rank = 0
                return impact_rank
        if variant['autoscore'] >= 4:
            impact_rank += 2
        elif variant['autoscore'] >= 2:
            impact_rank += 1
        if ('num' in variant and 'denom' in variant and variant['denom'] > 0):
            freq = int(variant['num']) * 1.0 / int(variant['denom'])
            if freq > 0.1:
                impact_rank = impact_rank * 0.5
    return impact_rank

def gene_report(f_out, gene, gene_data):
    # Make all variants have 'phase' info
    for variant in gene_data:
        variant['impact_rank'] = impact_rank(variant)
        if not 'phase' in variant:
            if len(variant['genotype'].split('/')) > 1:
                variant['phase'] = 'het unknown'
            else:
                variant['phase'] = 'homozygous'

    # Figure out how interesting the gene is.
    # Effect rank is my vague attempt to sort out variants with necessary 
    # zygosities (any dominant, homozygous recessive) in a way that includes 
    # compound hets. It's gene level, the max of the combined set of: 
    # (1) all homozygous impact_ranks
    # (2) all dominant het impact_ranks
    # (3) 50% of non-recessive, non-dominant impact_ranks
    # (3) average of compound het 'impact_rank'
    # (4) 50% of average of potentially compound (unphased) 'impact_rank'
    # Note that 50% is used as a compromise for unknown variants.
    #    -- MPB 5/11
    effect_ranks = [ 0 ]
    for variant in gene_data:
        if variant['phase'] == 'homozygous':
            effect_ranks.append(impact_rank(variant))                     # (1)
        else:
            if ('variant_dominance' in variant and
                variant['variant_dominance'] == 'dominant'):
                effect_ranks.append(impact_rank(variant))                 # (2)
            else:
                if ('variant_dominance' in variant and
                    variant['variant_dominance'] != 'recessive'):
                    effect_ranks.append(impact_rank(variant) * 0.5)
                if variant['phase'] == 'het unknown':
                    for variant2 in gene_data:
                        if variant == variant2:
                            continue
                        avg_impact = (impact_rank(variant) + 
                                      impact_rank(variant2)) / 2.0
                        effect_ranks.append(avg_impact * 0.5)             # (4)
                else:
                    for variant2 in gene_data:
                        if variant['phase'] == variant2['phase']:
                            continue
                        avg_impact = (impact_rank(variant) +
                                      impact_rank(variant2)) / 2.0
                        var1_phase_data = variant['phase'].split('-')
                        var2_phase_data = variant['phase'].split('-')
                        if (var1_phase_data[0] == var2_phase_data[0]):
                            effect_ranks.append(avg_impact)               # (3)
                        else:
                            effect_ranks.append(avg_impact * 0.5)         # (4)
    max_impact = max([v['impact_rank'] for v in gene_data])
    effect_rank = max(effect_ranks)
    if (max_impact > 1 or effect_rank > 0):
        output = { 'gene': gene,
                   'data': gene_data,
                   'effect_rank': max(effect_ranks) }
        f_out.write(json.dumps(output) + '\n')

def match_getev(gff_in, getev_flat, transcripts_file=None,
                gene_out_file=None, output_file=None, 
                progresstracker=None):
    """String generator returning JSON-formatted data from GET-Evidence

    Required inputs:
    gff_in: GFF-formated string generator, text, or .gz gzip-compressed
    getev_flat: JSON-formated text, or .gz gzip-compressed
    
    Optional inputs:
    output_file: if set, print to this & generator instead yields GFF lines
    progress_tracker: ProgressTracker object from progresstracker.py

    Each output line yielded is JSON-formatted and corresponds to data for a
    particular variant. It will always contain 'chr', 'coordinates', 
    'GET-Evidence', 'genotype', 'autoscore' and at least one of these two 
    possibilities: (1) 'gene' and 'amino_acid_change' or (2) 'dbsnp'. It may 
    also contain 'testable', 'reviewed', and items copied by copy_output_data.
    """
    # Load data from GET-Evidence and Genetests files.
    getev_by_aa, getev_by_dbsnp = read_getev_flat(getev_flat)
    genetests_filepath = os.path.join(os.getenv('DATA'), GENETESTS_DATA)
    genetests_clin, genetests_rev = read_genetests(genetests_filepath)

    # Set up optional output, will not be compressed.
    f_json_out = None
    f_gene_out = None
    if output_file:
        f_json_out = open(output_file, 'w')
    if gene_out_file and transcripts_file:
        gene_data = dict()
        f_gene_out = open(gene_out_file, 'w')
        transcripts = read_transcripts(transcripts_file)

    # Set up BLOSUM100 matrix to score amino acid disruptiveness.
    blosum_matrix = blosum100()

    # Set up GFF data. Can be a string generator, text, or 
    # (if it ends with '.gz') a gzip-compressed text.
    gff_data = None
    if isinstance(gff_in, str) and re.search(r'\.gz$', gff_in):
        gff_data = gff.input(gzip.open(gff_in))
    else:
        gff_data = gff.input(gff_in)

    header_done = False

    for record in gff_data:
        # Have to do this after calling the first record to
        # get the iterator to read through the header data 
        if (not header_done) and f_json_out:
            yield "##genome-build " + gff_data.data[1]
            yield "# File creation date: " + datetime.datetime.now().isoformat(' ')
            header_done = True

        # If outputing JSON to file, yield GFF data as it's read.
        if f_json_out:
            yield str(record)

        # Ignore regions called as matching reference.
        if record.feature == 'REF':
            continue

        # If producing a gene report, output finished genes
        if f_gene_out:
            to_remove = []
            for gene in gene_data:
                if not gene in transcripts:
                    # Remove genes we don't recognize
                    to_remove.append(gene)
                else:
                    if transcripts[gene]['end'] < record.end:
                        gene_report(f_gene_out, gene, gene_data[gene])
                        to_remove.append(gene)
            for gene in to_remove:
                gene_data.pop(gene)

        # Track progress if a ProgressTracker was passed to us
        if progresstracker: 
            progresstracker.saw(record.seqname)

        # Store data for JSON output as dict.
        output = dict()

        # Parse GFF attributes to find the alleles, reference allele, phase, and dbSNP.
        alleles = record.attributes['alleles'].strip('"').split('/') # don't sort!
        if len(alleles) == 1:
            output['genotype'] = alleles[0]
        elif len(alleles) > 2 or len(alleles) < 1:
            # Not sure what to do with >2 or 0 alleles! Skip it.
            continue
        else:
            output['genotype'] = '/'.join(sorted(alleles))
        ref_allele = record.attributes['ref_allele'].strip('"')
        output['ref_allele'] = ref_allele
        if 'phase' in record.attributes:
            # Add phase attribute for the non-reference allele;
            # if both non-reference, treat as unphased.
            phase_data = record.attributes['phase'].strip().split('/')
            if len(alleles) == 2 and len(phase_data) == 2:
                if alleles[0] == ref_allele:
                    output['phase'] = phase_data[1]
                elif alleles[1] == ref_allele:
                    output['phase'] = phase_data[0]
        dbsnp_ids = []
        if 'db_xref' in record.attributes or 'Dbxref' in record.attributes:
            if 'db_xref' in record.attributes:
                entries = [d.strip() for d in record.attributes['db_xref'].split(',')]
            else:
                entries = [d.strip() for d in record.attributes['Dbxref'].split(',')]
            for entry in entries:
                data = entry.split(':')
                if re.match('dbsnp', data[0]) and re.match('rs', data[1]):
                    dbsnp_ids.append(data[1])
            if dbsnp_ids:
                output["dbSNP"] = ",".join(dbsnp_ids)
        
        # Default presence in GET-Evidence is false, set as true later 
        # if a match is found.
        output['GET-Evidence'] = False
        
        # Store position data
        output['chromosome'] = record.seqname
        if record.start == record.end:
            output['coordinates'] = str(record.start)
        else:
            output['coordinates'] = str(record.start) + "-" + str(record.end)

        aa_changes = []
        # If there are any amino acid changes reported, look them up
        if "amino_acid" in record.attributes:
            for gene_aa_aa in record.attributes['amino_acid'].split('/'):
                aas = gene_aa_aa.split()
                gene = aas.pop(0)
                aa_seen = {}
                for aa in aas:
                    if aa in aa_seen: continue
                    aa_seen[aa] = 1
                    aa_changes.append([gene, aa])
        for aa_data in aa_changes:
            # Get gene and amino acid change, store in output.
            # Note: parse_aa_change will call sys.exit() if it's misformatted.
            gene, aa_change_and_pos = aa_data
            # "X" is preferred for stop, "*" can break things like URLs.
            aa_change_and_pos = re.sub(r'\*', r'X', aa_change_and_pos)
            (aa_from, aa_pos, aa_to) = parse_aa_change(aa_change_and_pos)
            output["gene"] = gene
            output["amino_acid_change"] = aa_data[1]

            # Check if the gene is in Genetests. If so, store result.
            if gene in genetests_clin:
                output["testable"] = True
                if gene in genetests_rev:
                    output["reviewed"] = True

            # Try to look up in GET-Evidence by amino acid change.
            aa_key = gene + "-" + aa_change_and_pos
            if aa_key in getev_by_aa:
                getev_data = getev_by_aa[aa_key]
                copy_output_data(getev_data, output)
                output["GET-Evidence"] = True
            else:
                # If not in GET-Evidence by aa, try dbsnp ID.
                if "dbSNP" in output:
                    dbsnp_ids = output["dbSNP"].split(",")
                    for dbsnp_id in dbsnp_ids:
                        if dbsnp_id in getev_by_dbsnp:
                            getev_data = getev_by_dbsnp[dbsnp_id]
                            output["GET-Evidence"] = True
                            copy_output_data(getev_data, output)
                            output["autoscore"] = autoscore(output, 
                                                            blosum_matrix,
                                                            aa_from, aa_to)
                            output["suff_eval"] = suff_eval(output)
                            output["dbSNP"] = dbsnp_id
                            # Quit after first hit passing threshold
                            if output["autoscore"] >= 2 or output["suff_eval"]:
                                break

            # Calculate autoscore, if not already done during dbSNP selection process
            if not ("autoscore" in output):
                output["autoscore"] = autoscore(output, blosum_matrix, aa_from, aa_to)
                if output["GET-Evidence"]:
                    output["suff_eval"] = suff_eval(output)

            # This barfs on Unicode sometimes.
            try:
                json_output = str(json.dumps(output, ensure_ascii=False))
            except:
                output['summary_short'] = ('Summary for this variant not ' +
                            'displayed. It may contain a Unicode character ' +
                            'preventing it from being properly processed.')
                json_output = str(json.dumps(output, ensure_ascii=False))

            if f_json_out:
                f_json_out.write(json_output + '\n')
            else:
                yield json_output

            # TODO: print when beyond end of gene, not when new one seen
            if f_gene_out and 'ucsc_trans' in record.attributes:
                # We take 1st & ignore multiple transcripts (which are rare)
                gene = record.attributes['ucsc_trans'].split(',')[0]
                if gene in gene_data:
                    gene_data[gene].append(output)
                else:
                    gene_data[gene] = [ output ]

        if len(aa_changes) == 0:
            # If no gene data at all, try dbsnp ID.
            if "dbSNP" in output:
                dbsnp_ids = output["dbSNP"].split(",")
                for dbsnp_id in dbsnp_ids:
                    if dbsnp_id in getev_by_dbsnp:
                        output["GET-Evidence"] = True
                        getev_data = getev_by_dbsnp[dbsnp_id]
                        copy_output_data(getev_data, output)
                        output["autoscore"] = autoscore(output)
                        output["suff_eval"] = suff_eval(output)
                        output["dbSNP"] = dbsnp_id
                        # Quit after first hit passing threshold
                        if output["autoscore"] >= 2 or output["suff_eval"]:
                            break

            # If no gene data and dbSNP id is not listed in
            # GET-Evidence, don't output.
            if "autoscore" in output:
                # This barfs on Unicode sometimes.
                try:
                    json_output = str(json.dumps(output, ensure_ascii=False))
                except:
                    continue
                if f_json_out:
                    f_json_out.write(json_output + '\n')
                else:
                    yield json_output
    if f_json_out:
        f_json_out.close()
    if f_gene_out:
        f_gene_out.close()

def match_getev_to_file(gff_in, getev_flat, output_file, transcripts_file=None, 
                        gene_out_file=None, progresstracker=False):
    """Outputs JSON-formatted data from GET-Evidence to output_file

    This calls match_getev (a string generator) and writes results to file.
    
    Required inputs:
    gff_in: GFF-formated string generator, text file, or .gz gzip-compressed
    getev_flat: JSON-formated text, or .gz gzip-compressed
    output_file: path to location for output file that will be written.
    
    Optional input:
    progress_tracker: ProgressTracker object from progresstracker.py
    """

    # Set up output file.
    f_out = None
    if isinstance(output_file, str):
        # Treat as path
        if re.match(".*\.gz", output_file):
            f_out = gzip.open("f_out", 'w')
        else:
            f_out = open(output_file, 'w')
    else:
        # Treat as writeable file object
        f_out = output_file

    if transcripts_file and gene_out_file:
        out = match_getev(gff_in, getev_flat, transcripts_file=transcripts_file,
                          gene_out_file=gene_out_file, 
                          progresstracker=progresstracker)
    else:
        out = match_getev(gff_in, getev_flat, progresstracker=progresstracker)
    for line in out:
        f_out.write(line + "\n")
    f_out.close()


def main():
    """Match a GFF file against JSON-formatted GET-Evidence data"""
    # parse options
    option, args = doc_optparse.parse(__doc__)

    if len(args) < 2:
        doc_optparse.exit()  # Error
    elif len(args) < 3:
        out = match_getev(args[0], args[1])
        for line in out:
            print line
    else:
        match_getev_to_file(args[0], args[1], args[2])

if __name__ == "__main__":
    main()
