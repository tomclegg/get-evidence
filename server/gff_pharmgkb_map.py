#!/usr/bin/python
# Filename: gff_pharmgkb_map.py

"""
usage: %prog gff_file
"""

# Output PharmGKB information in JSON format, if available
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
from config import DB_HOST, PHARMGKB_USER, PHARMGKB_PASSWD, PHARMGKB_DATABASE, DB_READ_DATABASE

query = '''
SELECT p.pubmed_id,
  p.webresource,
  if(p.drugs<>"",concat('[',p.drugs,'] ',p.annotation),p.annotation),
  if(p.genotype<>"",p.genotype,concat(p.gene," ",p.amino_acid_change)),
  p.name
FROM pharmgkb p
WHERE (p.rsid=%s and (p.genotype=%s or p.genotype=%s))
 OR (p.gene=%s and p.amino_acid_change in (%s,%s,%s,%s))
 ;
'''

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # first, try to connect to the databases
    try:
        location_connection = MySQLdb.connect(cursorclass=MySQLdb.cursors.SSCursor, host=DB_HOST, user=PHARMGKB_USER, passwd=PHARMGKB_PASSWD, db=PHARMGKB_DATABASE)
        location_cursor = location_connection.cursor()
        connection = MySQLdb.connect(host=DB_HOST, user=PHARMGKB_USER, passwd=PHARMGKB_PASSWD, db=PHARMGKB_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        sys.stderr.write ("Error %d while connecting to database: %s" % (message[0], message[1]))
        sys.exit()
    
    # make sure the required table is really there
    try:
        cursor.execute ('DESCRIBE pharmgkb')
    except MySQLdb.Error:
        sys.stderr.write ("No pharmgkb table => empty output")
        sys.exit()
    
    # doing this intersect operation speeds up our task significantly for full genomes
    gff_file = gff.input(sys.argv[1])    
    for record in gff_file:
        # lightly parse to find the alleles and rs number
        alleles = record.attributes["alleles"].strip("\"").split("/")
        ref_allele = record.attributes["ref_allele"].strip("\"")
        try:
            xrefs = record.attributes["db_xref"].strip("\"").split(",")
        except KeyError:
            try:
                xrefs = record.attributes["Dbxref"].strip("\"").split(",")
            except KeyError:
                continue
        for x in xrefs:
            if x.startswith("dbsnp:rs"):
                rs = x.replace("dbsnp:", "")
                break

        # quit if we don't have an rs number
        if not rs:
            continue
        # we wouldn't know what to do with this, so pass it up for now
        if len(alleles) > 2:
            continue

        # create the genotype string from the given alleles
        #TODO: do something about the Y chromosome
        if len(alleles) == 1:
            alleles = [alleles[0], alleles[0]]
            genotype = alleles[0]
        else:
            genotype = '/'.join(sorted(alleles))

        #reverse_alleles = (reverse_complement(alleles[0]),
        #           reverse_complement(alleles[1]))

        for gene_acid_base in record.attributes["amino_acid"].split("/"):

            # get amino acid change
            x = gene_acid_base.split(" ",1)
            gene = x[0]
            amino_acid_change_and_position = x[1]

            # starting with F123V, build a list of ways
            # this might appear in the PharmGKB data,
            # ie. F123V 123F/V Phe123Val 123Phe>Val

            acid_changes = []
            acid_changes.append(re.sub(r' .*',r'', amino_acid_change_and_position))
            acid_changes.append(re.sub(r'([A-Z])(\d+)([A-Z]+)', r'\2\1/\3', acid_changes[0]))
            for x in range(2):
                acid_changes.append(re.sub(r'[A-Z]', lambda x: codon_123(x.group(0)), acid_changes[x]))
            acid_changes[3] = re.sub(r'/', r'>', acid_changes[3])

            # query the database
            cursor.execute(query, (rs, alleles[0], alleles[1],
                           gene,
                           acid_changes[0],
                           acid_changes[1],
                           acid_changes[2],
                           acid_changes[3]))
            data = cursor.fetchall()

            # if this gene/AA change caused a hit, stop here and report it
            if cursor.rowcount > 0:
                break

        if cursor.rowcount > 0:
            for d in data:
                pubmed = d[0]
                webresource = d[1].replace(",","%2c")
                phenotype = d[2]
                trait_allele = d[3]

                # format for output
                if record.start == record.end:
                    coordinates = str(record.start)
                else:
                    coordinates = str(record.start) + "-" + str(record.end)

                reference = ""
                if pubmed != "":
                    reference = "pmid:" + pubmed.replace(",", ",pmid:")
                if webresource == "http://www.genome.gov/gwastudies/":
                    reference = "gwas:" + rs + "," + reference
                elif webresource != "":
                    reference = webresource + "," + reference

                output = {
                    "chromosome": record.seqname,
                    "coordinates": coordinates,
                    "gene": gene,
                    "amino_acid_change": amino_acid_change_and_position,
                    "amino_acid": record.attributes["amino_acid"],
                    "genotype": genotype,
                    "trait_allele": trait_allele,
                    "variant": str(record),
                    "phenotype": phenotype,
                    "reference": reference
                }
                print json.dumps(output)
    
    # close database cursor and connection
    cursor.close()
    connection.close()
    location_cursor.close()
    location_connection.close()

if __name__ == "__main__":
    main()
