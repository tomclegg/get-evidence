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

query_gene = '''
SELECT genetests.testable,
genetests.reviewed
FROM genetests
WHERE (genetests.gene=%s)
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
        dbsnp_ids = []
        if "db_xref" in record.attributes:
            db_xref_entries = [d.strip() for d in record.attributes["db_xref"].split(",")]
            for entry in db_xref_entries:
                data = entry.split(":")
                if re.match('dbsnp',data[0]) and re.match('rs',data[1]):
                    dbsnp_ids.append(data[1])
        elif "Dbxref" in record.attributes:
            db_xref_entries = [d.strip() for d in record.attributes["Dbxref"].split(",")]
            for entry in db_xref_entries:
                data = entry.split(":")
                if re.match('dbsnp',data[0]) and re.match('rs',data[1]):
                    dbsnp_ids.append(data[1])

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
            
        # Store output here, add GET-Evidence or other findings if available
        output = {
                    "chromosome": record.seqname,
                    "coordinates": coordinates,
                    "genotype": genotype,
                    "ref_allele": ref_allele,
                    "GET-Evidence": False
                }
        if dbsnp_ids:
            output["dbsnp"] = ",".join(dbsnp_ids)

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
                aa_from = []
                aa_pos = []
                aa_to = []
                # Parse out the from and to
                (aa_from, aa_pos, aa_to) = re.search(r'([A-Z\*]*)([0-9]*)([A-Z\*]*)', \
                        amino_acid_change_and_position).groups()
                
                # store gene and amino acid change in output_splice_variant
                output_splice_variant["gene"] = gene
                output_splice_variant["amino_acid_change"] = x[1]

                aa_from_long = "".join([codon_123(aa) for aa in list(aa_from)])
                aa_to_long = aa_to
                if (aa_to not in ["Del", "Shift", "Frameshift"]):
                    aa_to_long = "".join([codon_123(aa) for aa in list(aa_to)])
                aa_from_long = re.sub('TERM','Stop',aa_from_long)
                aa_to_long = re.sub('TERM','Stop',aa_to_long)

                # query the GET-Evidence edits database for curated data
                data = []
                cursor.execute(query_aa_freq, (gene, aa_from_long, aa_pos, aa_to_long, "1000g"))
                if cursor.rowcount == 0:
                    cursor.execute(query_aa_freq, (gene, aa_from_long, aa_pos, aa_to_long, "HapMap"))
                if cursor.rowcount == 0:
                    cursor.execute(query_aa_nofreq, (gene, aa_from_long, aa_pos, aa_to_long))
                data = cursor.fetchall()

                # If this gene/AA change caused a hit, report it
                if cursor.rowcount > 0 and data:
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
 
                    output_splice_variant["autoscore"] = autoscore(output_splice_variant, \
                                                            blosum_matrix, aa_from, aa_to)               

                    if (output_splice_variant["autoscore"] >= 2 or suff_eval(output_splice_variant)):
                        print json.dumps(output_splice_variant, ensure_ascii=False)

            # If no splice variants had a GET-Evidence hit, analyze using first splice variant
            if (not output["GET-Evidence"]):
                x = splice_variant_list[0].split(" ")
                gene = x[0]
                amino_acid_change_and_position = x[1]
                # Parse out the from and to
                (aa_from, aa_pos, aa_to) = re.search(r'([A-Za-z\*\-]*)([0-9\-]*)([A-Za-z\-\*]*)', \
                        amino_acid_change_and_position).groups()

                # store gene and amino acid change in output_splice_variant
                output["gene"] = gene
                output["amino_acid_change"] = x[1]
                
                cursor.execute(query_gene, (output["gene"]))
                if cursor.rowcount > 0:
                    #print "Found a gene match for " + output["gene"]
                    data = cursor.fetchall()
                    for key in data[0].keys():
                        output[key] = data[0][key]


                if "dbsnp" in output:
                    dbsnp_ids = output["dbsnp"].split(",")
                    for dbsnp_id in dbsnp_ids:
                        dbsnp_id = dbsnp_id.strip('rs')
                        cursor.execute(query_rsid, (dbsnp_id))
                        data = cursor.fetchall()
                        if cursor.rowcount > 0:
                            print "Found match for rs" + dbsnp_id + "!"
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
                            break;  # quit after first hit

                output["autoscore"] = autoscore(output, blosum_matrix, aa_from, aa_to)

                if (output["autoscore"] >= 2 or suff_eval(output)):
                    print json.dumps(output, ensure_ascii=False)               
                    
        else:
            # Not handling splices for now, but might in the future.
            #   -- Madeleine 11/29/2010
            '''
            if "splice" in record.attributes:
                splice_variants = record.attributes["splice"].split("/")
                output["splice_site"] = splice_variants[0]
                splice_data = splice_variants[0].split(" ")
                output["gene"] = splice_data[0]
            if "gene" in output:
                cursor.execute(query_gene, (output["gene"]))
                if cursor.rowcount > 0:
                    #print "Found a gene match for " + output["gene"]
                    data = cursor.fetchall()
                    for key in data[0].keys():
                        output[key] = data[0][key]
            '''


            if "dbsnp" in output:
                dbsnp_ids = output["dbsnp"].split(",")
                for dbsnp_id in dbsnp_ids:
                    dbsnp_id = dbsnp_id.strip('rs')
                    cursor.execute(query_rsid, (dbsnp_id))
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
                        break;  # quit after first hit

            output["autoscore"] = autoscore(output)

            # Autoscore bar is lower here because you can only get points if 
            # the dbSNP ID is in one of the variant specific databases (max 2)
            if (output["autoscore"] >= 1 or suff_eval(output)):
                print json.dumps(output, ensure_ascii=False)

    cursor.close()
    connection.close()

def autoscore(data, blosum=None, aa_from=None, aa_to=None):
    score_var_database = 0;
    score_gene_database = 0;
    score_comp = 0;
    # Variant specific databases
    if "in_omim" in data and data["in_omim"]:
        score_var_database += 2
    if "in_gwas" in data and data["in_gwas"]:
        score_var_database += 1
    if "in_pharmgkb" in data and data["in_pharmgkb"]:
        score_var_database += 1
    # Gene specific databases
    if "testable" in data and data["testable"] == 1:
        if "reviewed" in data and data["reviewed"] == 1:
            score_gene_database +=2
        else:
            score_gene_database +=1
    # Computational prediction
    #if (aa_from and aa_to):
    #    print "Running autoscore, aa_from: " + aa_from + " aa_to: " + aa_to
    if (aa_to and re.search(r'Del', aa_to)):
        score_comp = 1
        data["indel"] = True
    elif (aa_to and re.search(r'Shift', aa_to)):
        score_comp = 2
        data["frameshift"] = True
    elif aa_from and aa_to and re.match('\*',aa_to):
        score_comp = 2
        data["nonsense"] = True
    elif (aa_from and aa_to and len(aa_from) != len(aa_to)):
        score_comp = 1
    elif (aa_from and aa_to):
        for i in range(len(aa_from)):
            if blosum.value(aa_from[i], aa_to[i]) <= -4:
                score_comp = 1
                data["disruptive"] = True
    # Max all to 2
    score_var_database = min(2,score_var_database)
    score_gene_database = min(2,score_gene_database)
    score_comp = min(2,score_comp)
    return score_var_database + score_gene_database + score_comp

def suff_eval(variant_data):
    quality_scores = ""
    if "variant_quality" in variant_data:
        quality_scores = variant_data["variant_quality"]
    else:
        return False
    impact = variant_data["variant_impact"]
    if (len(quality_scores) < 7):
        return False
    else:
        if (quality_scores[2] == "-" and quality_scores[3] == "-"):
            return False
        else:
            if ( (impact == "benign" or impact == "protective") and num_eval >= 2):
                return True
            else:
                if (quality_scores[4] == "-" or quality_scores[6] == "-"):
                    return False
                else:
                    return True

if __name__ == "__main__":
    main()
