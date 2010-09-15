#!/usr/bin/python
# Filename: gff_omim_map.py

"""
usage: %prog gff_file
"""

# Output OMIM information in JSON format, if available
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, string, sys
from copy import copy
import MySQLdb
import simplejson as json
from utils import gff
from config import DB_HOST, DB_READ_USER, DB_READ_PASSWD, DB_READ_DATABASE

query = '''
SELECT phenotype, amino_acid, word_count, allelic_variant_id FROM omim WHERE
gene=%s AND codon=%s;
'''

three_letter_alphabet = {
    "A": "Ala",
    "R": "Arg",
    "N": "Asn",
    "D": "Asp",
    "C": "Cys",
    "Q": "Gln",
    "E": "Glu",
    "G": "Gly",
    "H": "His",
    "I": "Ile",
    "L": "Leu",
    "K": "Lys",
    "M": "Met",
    "F": "Phe",
    "P": "Pro",
    "S": "Ser",
    "T": "Thr",
    "W": "Trp",
    "Y": "Tyr",
    "V": "Val",
    "B": "Asx",
    "Z": "Glx",
    "X": "Xaa",
    "*": "TERM"
}

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
        
        # determine zygosity
        if len(alleles) == 1:
            zygosity = "hom"
        else:
            zygosity = "het"
        
        # examine each amino acid change
        amino_acid_changes = record.attributes["amino_acid"].strip("\"").split("/")
        for a in amino_acid_changes:
            amino_acid = a.split(" ")
            gene = amino_acid.pop(0) # the first item is always the gene name
            
            # there should be only one amino acid change per coding sequence,
            # because there should be only two alleles, but if there are 3 or
            # more alleles (due to ambiguous sequencing), then we need to look
            # at all of them
            for aa in amino_acid:
                aa_pos = long(aa[1:-1])
                ref_aa = three_letter_alphabet[aa[0]]
                mut_aa = three_letter_alphabet[aa[-1]]
                
                cursor.execute(query, (gene, aa_pos))
                data = cursor.fetchall()
                
                # move on if we don't have info
                if cursor.rowcount <= 0:
                    continue
                
                for d in data:
                    phenotype = d[0]
                    amino_acid_change = d[1]
                    word_count = d[2]
                    if d[3].startswith("omim:"):
                        allelic_variant_id = d[3]
                    else:
                        allelic_variant_id = "omim:" + d[3]
                    
                    # keep, for internal purposes, a list of alleles minus the reference
                    leftover_alleles = copy(alleles)
                    try:
                        leftover_alleles.remove(ref_allele)
                    except ValueError:
                        pass
                    
                    # we see if what we designated the mutant allele is the phenotype-
                    # associated allele, or if what we the reference sequence allele is
                    # the phenotype-associated allele; if the latter, we have to make
                    # sure that the genome we're looking at actually has the reference
                    # allele
                    if string.lower(amino_acid_change).endswith(string.lower(mut_aa)):
                        amino_acid_change_and_position = aa[0] + str(aa_pos) + aa[-1]
                        #TODO: this doesn't work when we have multiple alleles
                        if len(leftover_alleles) == 1:
                            trait_allele = leftover_alleles[0]
                        else:
                            trait_allele = '?'
                    elif (string.lower(amino_acid_change).endswith(string.lower(ref_aa)) and
                      ref_allele in alleles):
                        amino_acid_change_and_position = aa[-1] + str(aa_pos) + aa[0]
                        trait_allele = ref_allele
                    else:
                        continue
                    
                    # format for output
                    if record.start == record.end:
                        coordinates = str(record.start)
                    else:
                        coordinates = str(record.start) + "-" + str(record.end)
                    
                    genotype = "/".join(leftover_alleles)
                    if ref_allele in alleles:
                        genotype = ref_allele + "/" + genotype
                    
                    output = {
                        "chromosome": record.seqname,
                        "coordinates": coordinates,
                        "gene": gene,
                        "amino_acid_change": amino_acid_change_and_position,
                        "genotype": genotype,
                        "ref_allele": ref_allele,
                        "trait_allele": trait_allele,
                        "zygosity": zygosity,
                        "variant": str(record),
                        "phenotype": phenotype,
                        "reference": allelic_variant_id,
                    }
                    print json.dumps(output)
    
    # close database cursor and connection
    cursor.close()
    connection.close()

if __name__ == "__main__":
    main()
