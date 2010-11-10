#!/usr/bin/python
# Filename: gff_get-evidence_map.py

"""
usage: %prog nssnp.gff non-nssnp.gff
"""

# Output GET-Evidence information in JSON format
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, string, sys, re
import MySQLdb, MySQLdb.cursors
import simplejson as json
from codon import codon_321, codon_123
from copy import copy
from utils import gff
from utils.biopython_utils import reverse_complement
from utils.bitset import *
from config import DB_HOST, GETEVIDENCE_USER, GETEVIDENCE_PASSWD, GETEVIDENCE_DATABASE
from utils.substitution_matrix import blosum100

query_aa_freq = '''
SELECT variants.variant_id,
snap_latest.edit_id,
snap_latest.summary_short,
snap_latest.variant_impact,
snap_latest.variant_dominance,
snap_latest.variant_quality,
variant_external.tag,
genetests.testable,
genetests.reviewed,
allele_frequency.num,
allele_frequency.denom,
allele_frequency.dbtag
FROM variants
LEFT JOIN snap_latest
  ON variants.variant_id = snap_latest.variant_id
LEFT JOIN variant_external
  ON variants.variant_id = variant_external.variant_id
LEFT JOIN genetests
  ON variants.variant_gene = genetests.gene
LEFT JOIN variant_occurs
  ON variants.variant_id = variant_occurs.variant_id
LEFT JOIN allele_frequency
  ON variant_occurs.chr = allele_frequency.chr
    AND variant_occurs.chr_pos = allele_frequency.chr_pos
    AND variant_occurs.allele = allele_frequency.allele
WHERE (
  variants.variant_gene=%s 
  AND variants.variant_aa_from=%s 
  AND variants.variant_aa_pos=%s 
  AND variants.variant_aa_to=%s
  AND snap_latest.article_pmid="" 
  AND snap_latest.genome_id=""
  AND allele_frequency.dbtag=%s
)
ORDER BY snap_latest.edit_id DESC
'''

query_aa_nofreq = '''
SELECT variants.variant_id,
snap_latest.edit_id,
snap_latest.summary_short,
snap_latest.variant_impact,
snap_latest.variant_dominance,
snap_latest.variant_quality,
variant_external.tag,
genetests.testable,
genetests.reviewed
FROM variants
LEFT JOIN snap_latest
  ON variants.variant_id = snap_latest.variant_id
LEFT JOIN variant_external
  ON variants.variant_id = variant_external.variant_id
LEFT JOIN genetests
  ON variants.variant_gene = genetests.gene
WHERE (
  variants.variant_gene=%s 
  AND variants.variant_aa_from=%s 
  AND variants.variant_aa_pos=%s 
  AND variants.variant_aa_to=%s
  AND snap_latest.article_pmid="" 
  AND snap_latest.genome_id=""
)
ORDER BY snap_latest.edit_id DESC
'''

query_var_external = '''
SELECT * FROM variant_external WHERE variant_id=%s'''

# Don't bother with rsid allele freq because GET-Ev currently lacks this data
query_rsid = '''
SELECT variants.variant_id,
snap_latest.edit_id,
snap_latest.summary_short,
snap_latest.variant_impact,
snap_latest.variant_dominance,
snap_latest.variant_quality,
variant_external.tag,
genetests.testable,
genetests.reviewed
FROM variants
LEFT JOIN snap_latest
  ON variants.variant_id = snap_latest.variant_id
LEFT JOIN variant_external
  ON variants.variant_id = variant_external.variant_id
LEFT JOIN genetests
  ON variants.variant_gene = genetests.gene
WHERE (variants.variant_rsid=%s)
ORDER BY snap_latest.edit_id DESC
'''

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) != 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # first, try to connect to the databases
    try:
        connection = MySQLdb.connect(cursorclass=MySQLdb.cursors.DictCursor, host=DB_HOST, user=GETEVIDENCE_USER, passwd=GETEVIDENCE_PASSWD, db=GETEVIDENCE_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        sys.stderr.write ("Error %d while connecting to database: %s" % (message[0], message[1]))
        sys.exit()
    
    # make sure the required table is really there
    try:
        cursor.execute ('DESCRIBE snap_latest')
    except MySQLdb.Error:
        sys.stderr.write ("No 'snap_latest' table => empty output")
        sys.exit()

    blosum_matrix = blosum100()    

    gff_file = gff.input(sys.argv[1])
    for record in gff_file:
        # lightly parse to find the alleles and rs number
        alleles = record.attributes["alleles"].strip("\"").split("/")
        ref_allele = record.attributes["ref_allele"].strip("\"")
        xrefs = ()
        try:
            xrefs = record.attributes["db_xref"].strip("\"").split(",")
        except KeyError:
            try:
                xrefs = record.attributes["Dbxref"].strip("\"").split(",")
            except KeyError:
                pass

        # we wouldn't know what to do with this, so pass it up for now
        if len(alleles) > 2:
            continue

        # format coordinates
        if record.start == record.end:
            coordinates = str(record.start)
        else:
            coordinates = str(record.start) + "-" + str(record.end)

        # create the genotype string from the given alleles
        #TODO: do something about the Y chromosome
        if len(alleles) == 1:
            alleles = [alleles[0], alleles[0]]
            genotype = alleles[0]
        else:
            genotype = '/'.join(sorted(alleles))
        rs_number = 0
        for x in xrefs:
            if x.startswith("dbsnp:rs"):
                rs_number = int(x.replace("dbsnp:rs",""))
                break
            
        # Store output here, add GET-Evidence or other findings if available
        output = {
                    "chromosome": record.seqname,
                    "coordinates": coordinates,
                    "genotype": genotype,
                    "ref_allele": ref_allele,
                    "GET-Evidence": False
                }
        if rs_number > 0:
            output["dbSNP"] = rs_number

        # Record found variant_id's here, if any are found
        variants_found = list()

        # If there is an amino acid change, query GET-Evidence using amino acid change data
        if "amino_acid" in record.attributes:
            # splice variants are split by "/" in Trait-o-matic's ns.gff output
            splice_variant_list = record.attributes["amino_acid"].split("/")
            for splice_variant in splice_variant_list:
                # make a local copy of output for each splice variant
                output_splice_variant = output.copy()

                # get gene and amino acid change
                # sometimes x[2] exists, but is almost always a duplicate of x[1] 
                # (reflecting homozygous non-reference), so we are ignoring it
                x = splice_variant.split(" ")
                gene = x[0]
                amino_acid_change_and_position = x[1]
                # convert amino acid single-letter (short) form to three-letter (long) form
                (aa_from_short, aa_pos, aa_to_short) = re.search(r'([A-Z\*])([0-9]*)([A-Z\*])', amino_acid_change_and_position).groups()
                aa_from = codon_123(aa_from_short)
                aa_to = codon_123(aa_to_short)
                aa_to = re.sub(r'TERM', 'Stop', aa_to)
                # store gene and amino acid change in output_splice_variant
                output_splice_variant["gene"] = gene
                output_splice_variant["amino_acid_change"] = amino_acid_change_and_position

                # query the GET-Evidence edits database for curated data
                cursor.execute(query_aa_freq, (gene, aa_from, aa_pos, aa_to, "1000g"))
                if cursor.rowcount == 0:
                    cursor.execute(query_aa_freq, (gene, aa_from, aa_pos, aa_to, "HapMap"))
                if cursor.rowcount == 0:
                    cursor.execute(query_aa_nofreq, (gene, aa_from, aa_pos, aa_to))
                data = cursor.fetchall()

                # If this gene/AA change caused a hit, report it
                if cursor.rowcount > 0:
                    for key in data[0].keys():
                        if key != "tag":
                            output_splice_variant[key] = data[0][key]
                    output_splice_variant["GET-Evidence"] = output["GET-Evidence"] = True
                    # query to see if it's found in external databases
                    cursor.execute(query_var_external, (output_splice_variant["variant_id"]))
                    data = cursor.fetchall()
                    for d in data:
                        if d["tag"] == "OMIM":
                            output_splice_variant["in_omim"] = True
                        if d["tag"] == "GWAS":
                            output_splice_variant["in_gwas"] = True
                        if d["tag"] == "PharmGKB":
                            output_splice_variant["in_pharmgkb"] = True

                output_splice_variant["autoscore"] = autoscore(output_splice_variant, blosum_matrix, aa_from_short, aa_to_short)
                if "variant_quality" in output_splice_variant:
                    output_splice_variant["suff_eval"] = suff_eval(output_splice_variant)
                else:
                    output_splice_variant["suff_eval"] = False

                if (output_splice_variant["autoscore"] >= 2 or output_splice_variant["suff_eval"]):
                    print json.dumps(output_splice_variant, ensure_ascii=False)

            # If no splice variants had a GET-Evidence hit, analyze using first splice variant
            if (not output["GET-Evidence"]):
                x = splice_variant_list[0].split(" ")
                gene = x[0]
                amino_acid_change_and_position = x[1]
                # Get amino acid change for first splice variant
                (aa_from_short, aa_pos, aa_to_short) = re.search(r'([A-Z\*])([0-9]*)([A-Z\*])', amino_acid_change_and_position).groups()
                if "dbSNP" in output:
                    cursor.execute(query_rsid, (output["dbSNP"]))
                    data = cursor.fetchall()
                    if cursor.rowcount > 0:
                        for key in data[0].keys():
                            if key != "tag":
                                output[key] = data[0][key]
                        output["GET-Evidence"] = True
                        for d in data:
                            if d["tag"] == "OMIM":
                                output["in_omim"] = True
                            if d["tag"] == "GWAS":
                                output["in_gwas"] = True
                            if d["tag"] == "PharmGKB":
                                output["in_pharmgkb"] = True
                output["autoscore"] = autoscore(output, blosum_matrix, aa_from_short, aa_to_short)
                if "variant_quality" in output:
                    output["suff_eval"] = suff_eval(output)
                else:
                    output["suff_eval"] = False

                if (output["autoscore"] >= 2 or output["suff_eval"]):
                    print json.dumps(output, ensure_ascii=False)               
                    
        else:
            if "dbSNP" in output:
                cursor.execute(query_rsid, (output["dbSNP"]))
                data = cursor.fetchall()
                if cursor.rowcount > 0:
                    for key in data[0].keys():
                        if key != "tag":
                            output[key] = data[0][key]
                output["GET-Evidence"] = True
                for d in data:
                    if d["tag"] == "OMIM":
                        output["in_omim"] = True
                    if d["tag"] == "GWAS":
                        output["in_gwas"] = True
                    if d["tag"] == "PharmGKB":
                        output["in_pharmgkb"] = True
            output["autoscore"] = autoscore(output)
            if "variant_quality" in output:
                output["suff_eval"] = suff_eval(output)
            else:
                output["suff_eval"] = False

            # Autoscore bar is lower here because you can only get points if 
            # the dbSNP ID is in one of the variant specific databases (max 2)
            if (output["autoscore"] >= 1 or output["suff_eval"]):
                print json.dumps(output, ensure_ascii=False)

    cursor.close()
    connection.close()

def autoscore(data, blosum=None, aa_from=None, aa_to=None):
    score_var_database = 0;
    score_gene_database = 0;
    score_comp = 0;
    if "in_omim" in data and data["in_omim"]:
        score_var_database += 2
    if "in_gwas" in data and data["in_gwas"]:
        score_var_database += 1
    if "in_pharmgkb" in data and data["in_pharmgkb"]:
        score_var_database += 1
    if (score_var_database > 2):
        score_var_database = 2
    if "testable" in data and data["testable"] == 1:
        if "reviewed" in data and data["reviewed"] == 1:
            score_gene_database +=2
        else:
            score_gene_database +=1
    if (blosum and blosum.value(aa_from, aa_to) <= -4):
        if aa_to == "X" or aa_to == "*":
            score_comp = 2
            data["nonsense"] = True
        else:
            score_comp = 1
            data["disruptive"] = True
    return score_var_database + score_gene_database + score_comp

def suff_eval(variant_data):
    quality_scores = variant_data["variant_quality"]
    impact = variant_data["variant_impact"]
    if (len(quality_scores) < 5):
        return False
    else:
        if (quality_scores[2] == "-" and quality_scores[3] == "-"):
            return False
        else:
            num_eval = 0
            for score in quality_scores:
                if (score != "-"):
                    num_eval += 1
            if ( (impact == "benign" or impact == "protective") and num_eval >= 2):
                return True
            else:
                if (quality_scores[4] == "-" and quality_scores[5] == "-"):
                    return False
                else:
                    if num_eval >= 4:
                        return True
                    else:
                        return False

if __name__ == "__main__":
    main()
