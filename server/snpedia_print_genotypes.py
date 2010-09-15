#!/usr/bin/python
# Filename: snpedia_print_genotypes.py

"""
usage: %prog snpedia.txt [options]
  -r, --reference=PATH: output only homozygous genotypes found in the given reference file (2bit)
"""

# Output tab-separated genotype information for each entry in SNPedia
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, sys, math, re
import MySQLdb
from utils import doc_optparse, twobit
from utils.biopython_utils import reverse_complement
from config import DB_HOST, DB_READ_USER, DB_READ_PASSWD, DB_READ_DATABASE

query = '''
SELECT chrom, chromStart, chromEnd, strand FROM `snp129`
WHERE name=%s;
'''

def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)
    
    if len(args) < 1:
        doc_optparse.exit()
    
    # first, try to connect to the database
    try:
        connection = MySQLdb.connect(host=DB_HOST, user=DB_READ_USER, passwd=DB_READ_PASSWD, db=DB_READ_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        print "Error %d while connecting to database: %s" % (message[0], message[1])
        sys.exit()
    
    if option.reference:
        twobit_file = twobit.input(option.reference)
    
    for line in fileinput.input(args[0]):
        l = line.strip().split('\t')
        if len(l) < 5:
            print >> sys.stderr, l
        
        # input lines are in the form:
        # chromosome, position, rs, genotype, phenotype, pubmed (optional)
        if l[0].startswith("chr") or l[0] == "None":
            chr = l[0]
        else:
            chr = "chr" + l[0]
        try:
            pos = int(l[1])
        except ValueError:
            pos = None
        rs = l[2]
        genotype = l[3]
        phenotype = l[4]
        if len(l) > 5:
            pubmed = l[5].replace("pmid:", "")
        else:
            pubmed = ""
        
        cursor.execute(query, rs)
        datum = cursor.fetchone()
        
        # go away if we don't know this rs number
        if not datum:
            print >> sys.stderr, "# not found:"
            print >> sys.stderr, line
            continue
        # this would be very strange
        if chr != datum[0] and chr != "None":
            print >> sys.stderr, "# not on expected chromosome %s:" % datum[0]
            print >> sys.stderr, line
            continue
        else:
            chr = datum[0]
        
        strand = datum[3]
        
        # filter out genotypes that don't match the reference allele, if asked to
        if option.reference:
            ref = twobit_file[chr][datum[1]:datum[2]]
            if strand == "-":
                ref = reverse_complement(ref)
            if genotype != (ref + ";" + ref):
                continue
        
        print "%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s" % (phenotype, chr, datum[1], datum[2],
          strand, genotype, pubmed, rs)
    
    # close database cursor and connection
    cursor.close()
    connection.close()

if __name__ == "__main__":
    main()
