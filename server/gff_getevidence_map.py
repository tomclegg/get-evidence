#!/usr/bin/python
# Filename: gff_getevidence_map.py

"""
usage: %prog nssnp.gff getev_flatfile.tsv [output_file]
"""

# Match to GET-Evidence's JSON flatfile dump, 
# Output data for genome report in JSON format

import simplejson as json
import gzip
import os
import re
import sys
from utils import doc_optparse, gff
from config import GENETESTS_DATA
from utils.substitution_matrix import blosum100

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
    items_wanted = ['gene', 'aa_change_short', 'summary_short', 'impact', \
            'inheritance', 'dbsnp_id', 'in_omim', 'in_gwas', 'in_pharmgkb', \
            'variant_quality', 'overall_frequency_n', 'overall_frequency_d', \
            'n_articles', 'variant_id']

    # Create two dicts to be returned, storing data we want.
    # If possible, getev_by_aa is used with gene and amino acid change as key.
    # Otherwise, if possible getev_by_dbsnp is used with dbSNP ID as key.
    getev_by_aa = dict()
    getev_by_dbsnp = dict()
    for line in f_in:
        data = json.loads(line)
        stored_data = dict()
        for item in items_wanted:
            if item in data and data[item]:
                stored_data[item] = data[item]
        if 'gene' in stored_data and 'aa_change_short' in stored_data:
            # Don't bother storing if we can tell autoscore isn't high enough.
            if 'autoscore' in data and data['autoscore'] < 2:
                continue 
            aa_key = stored_data['gene'] + '-' + stored_data['aa_change_short']
            getev_by_aa[aa_key] = stored_data
        elif 'dbsnp_id' in stored_data:
            if 'autoscore' in data and data['autoscore'] < 1:
                continue
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
                # If data[6] isn't 'na', it's a link to an associated review.
                if data[6] != 'na':
                    genes_rev.add(gene)
    return genes_clin, genes_rev


def copy_output_data(getev_data, output_data):
    """Copy data to output using names recognized by genome_display.php"""
    name_map = {    'overall_frequency_n': 'num',
                    'overall_frequency_d': 'denom',
                    'impact': 'variant_impact',
                    'quality': 'variant_quality',
                    'summary_short': 'summary_short',
                    'variant_quality': 'variant_quality',
                    'inheritance': 'variant_dominance',
                    'variant_id': 'variant_id',
                    'n_articles': 'n_articles'
                    }
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
    elif aa_from and aa_to and re.match('\*', aa_to):
        score_comp = 2
        data["nonsense"] = True
    elif aa_from and aa_to and len(aa_from) != len(aa_to):
        score_comp = 1
        data["indel"] = True
    elif aa_from and aa_to:
        for i in range(len(aa_from)):
            if blosum.value(aa_from[i], aa_to[i]) <= -4:
                score_comp = 1
                data["disruptive"] = True

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
    if "variant_quality" in variant_data:
        quality_scores = variant_data["variant_quality"]
        if (not quality_scores) or len(quality_scores) < 7:
            return False
    else:
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


def match_getev(gff_in, getev_flat, output_file=None, progresstracker=None):
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
    if output_file:
        f_json_out = open(output_file, 'w')

    # Set up BLOSUM100 matrix to score amino acid disruptiveness.
    blosum_matrix = blosum100()

    # Set up GFF data. Can be a string generator, text, or 
    # (if it ends with '.gz') a gzip-compressed text.
    gff_data = None
    if isinstance(gff_in, str) and re.search(r'\.gz$', gff_in):
        gff_data = gff.input(gzip.open(gff_in))
    else:
        gff_data = gff.input(gff_in)

    for record in gff_data:
        # If outputing JSON to file, yield GFF data as it's read.
        if f_json_out:
            yield str(record)

        # Ignore regions called as matching reference.
        if record.feature == 'REF':
            continue
        # Track progress if a ProgressTracker was passed to us
        if progresstracker: 
            progresstracker.saw(record.seqname)

        # Parse GFF attributes to find the alleles and, if present, dbSNP IDs.
        alleles = record.attributes['alleles'].strip('"').split('/')
        ref_allele = record.attributes['ref_allele'].strip('"')
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

        # We wouldn't know what to do with more than 2 alleles, so pass this.
        if len(alleles) > 2:
            continue

        # Store data for JSON output as dict. 
        output = dict()
        
        # Default presence in GET-Evidence is false, set as true later 
        # if a match is found.
        output['GET-Evidence'] = False
        
        # Store position data, genotype (if heterozygous, alleles are separated 
        # by a '/'), ref allele, and dbSNP IDs (if present).
        output['chromosome'] = record.seqname
        if record.start == record.end:
            output['coordinates'] = str(record.start)
        else:
            output['coordinates'] = str(record.start) + "-" + str(record.end)
        if len(alleles) == 1:
            output['genotype'] = alleles[0]
        elif len(alleles) > 2 or len(alleles) < 1:
            # Not sure what to do with >2 or 0 alleles! Skip it.
            continue
        else:
            output['genotype'] = '/'.join(sorted(alleles))
        output['ref_allele'] = ref_allele
        if dbsnp_ids:
            output["dbSNP"] = ",".join(dbsnp_ids)

        # If there is an amino acid change reported, look it up based on this.
        if "amino_acid" in record.attributes:
            # Get gene and amino acid change, store in output.
            # Note: parse_aa_change will call sys.exit() if it's misformatted.
            # TODO: analyze more than the first change, multiple are split by /
            aa_changes = record.attributes['amino_acid'].split('/')
            aa_data = aa_changes[0].split()
            gene, aa_change_and_pos = aa_data[0:2]
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
                            # Quit after first hit passing threshold
                            if output["autoscore"] >= 2 or suff_eval(output):
                                output["dbSNP"] = dbsnp_id
                                break
            # Calculate autoscore, yield json data if at least 2.
            output["autoscore"] = autoscore(output, blosum_matrix, aa_from, aa_to)
            if output["autoscore"] >= 2 or suff_eval(output):
                # This barfs on Unicode sometimes.
                try:
                    json_output = str(json.dumps(output, ensure_ascii=False))
                except:
                    continue
                if f_json_out:
                    f_json_out.write(json_output + '\n')
                else:
                    yield json_output
        else:
            # If no gene data at all, try dbsnp ID.
            if "dbSNP" in output:
                dbsnp_ids = output["dbSNP"].split(",")
                for dbsnp_id in dbsnp_ids:
                    if dbsnp_id in getev_by_dbsnp:
                        output["GET-Evidence"] = True
                        getev_data = getev_by_dbsnp[dbsnp_id]
                        copy_output_data(getev_data, output)
                        output["autoscore"] = autoscore(output)
                        # Quit after first hit passing threshold
                        if output["autoscore"] >= 2 or suff_eval(output):
                            output["dbSNP"] = dbsnp_id
                            break
                    break  # quit after first hit
            output["autoscore"] = autoscore(output)
            # Autoscore bar is lower here because you can only get points if 
            # the dbSNP ID is in one of the variant specific databases (max 2).
            if output["autoscore"] >= 1 or suff_eval(output):
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

def match_getev_to_file(gff_in, getev_flat, output_file, progresstracker=False):
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
