#!/usr/bin/python
# Filename: gff_morbid_map.py

"""
usage: %prog gff_file
"""

# Output Morbid Map information in JSON format, if available
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, string, sys
import MySQLdb
import simplejson as json
from utils import gff, substitution_matrix
from config import DB_HOST, DB_READ_USER, DB_READ_PASSWD, DB_READ_DATABASE

query = '''
SELECT disorder, omim FROM morbidmap WHERE
(symbols LIKE %s OR symbols LIKE %s OR symbols LIKE %s OR symbols LIKE %s);
'''
# the symbols column is comma separated, so in order to be sure to get all the
# possible positions for a gene name, it's necessary to supply four patterns,
# namely:
# 1) "foo"
# 2) "foo,%"
# 3) "% foo,%"
# 4) "% foo"

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # first, try to connect to the databases
    try:
        connection = MySQLdb.connect(host=DB_HOST, user=DB_READ_USER, passwd=DB_READ_PASSWD, db=DB_READ_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        print "Error %d while connecting to database: %s" % (message[0], message[1])
        sys.exit()
    
    gff_file = gff.input(sys.argv[1])    
    for record in gff_file:
        # lightly parse alleles
        alleles = record.attributes["alleles"].strip("\"").split("/")
        ref_allele = record.attributes["ref_allele"].strip("\"")
        
        # examine each amino acid change (this takes care of alternative splicings)
        amino_acid_changes = record.attributes["amino_acid"].strip("\"").split("/")
        
        # make sure not to duplicate what we print because of multiple alternative
        # splicings; so, initialize an empty list to hold previous tuples of gene
        # names, variant records, and phenotype strings, so that we can compare
        previous_gene_variant_phenotype = []
        
        # examine each alternative splicing
        for a in amino_acid_changes:
            gene_variant_phenotype = []
            output_strings = []
            
            amino_acid = a.split(" ")
            gene = amino_acid.pop(0) # the first item is always the gene name
            
            # there should be only one amino acid change per coding sequence,
            # because there should be only two alleles, but if there are 3 or
            # more alleles (due to ambiguous sequencing), or if both alleles
            # are different from the reference sequence, then we need to look
            # at all of them
            for aa in amino_acid:
                ref_aa = aa[0]
                mut_aa = aa[-1]
                
                if ref_aa == "*" or mut_aa == "*":
                    score = 10
                else:
                    score = -1 * substitution_matrix.blosum_value(100, ref_aa, mut_aa)
                    if score <= 2:
                        # right now, we don't really consider conservative changes...
                        continue

                cursor.execute(query, (gene, gene + ",%", "% " + gene + ",%", "% " + gene))
                data = cursor.fetchall()
                
                # move on if we don't have info
                if cursor.rowcount <= 0:
                    continue
                
                for d in data:
                    disorder = d[0].strip()
                    omim = d[1]
                    gene_variant_phenotype.append((gene, str(record), disorder))
                    
                    # format for output
                    if record.start == record.end:
                        coordinates = str(record.start)
                    else:
                        coordinates = str(record.start) + "-" + str(record.end)
                    
                    genotype = "/".join(alleles)
                    
                    output = {
                        "chromosome": record.seqname,
                        "coordinates": coordinates,
                        "gene": gene,
                        "amino_acid_change": aa,
                        "genotype": genotype,
                        "ref_allele": ref_allele,
                        "variant": str(record),
                        "phenotype": disorder,
                        "reference": "omim:" + str(omim),
                        "score": score
                    }
                    output_strings.append(json.dumps(output))
            
            # actually only output what's not duplicating previous 
            if gene_variant_phenotype != previous_gene_variant_phenotype:
                previous_gene_variant_phenotype = gene_variant_phenotype
                for o in output_strings:
                    print o

    # close database cursor and connection
    cursor.close()
    connection.close()

if __name__ == "__main__":
    main()
