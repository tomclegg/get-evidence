#!/usr/bin/python
# Filename: gff_snpedia_map.py

"""
usage: %prog gff_file
"""

# Output SNPedia information in JSON format, if available
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, string, sys
import MySQLdb, MySQLdb.cursors
import simplejson as json
from utils import gff
from utils.biopython_utils import reverse_complement
from utils.bitset import *
from config import DB_HOST, DB_READ_USER, DB_READ_PASSWD, DB_READ_DATABASE

location_query = '''
SELECT chr, start, end FROM snpedia LIMIT %s,10000;
'''

query = '''
SELECT phenotype, pubmed_id FROM snpedia WHERE
rs_id=%s AND ((strand="+" AND genotype=%s) OR (strand="-" AND genotype=%s));
'''

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # first, try to connect to the databases
    try:
        location_connection = MySQLdb.connect(cursorclass=MySQLdb.cursors.SSCursor, host=DB_HOST, user=DB_READ_USER, passwd=DB_READ_PASSWD, db=DB_READ_DATABASE)
        location_cursor = location_connection.cursor()
        connection = MySQLdb.connect(host=DB_HOST, user=DB_READ_USER, passwd=DB_READ_PASSWD, db=DB_READ_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        print "Error %d while connecting to database: %s" % (message[0], message[1])
        sys.exit()
    
    # build a dictionary of bitsets from the database (partly based on utility code from bx-python)
    start_record = 0
    last_chromosome = None
    last_bitset = None
    bitsets = dict()
    # do this in 10,000 chunks
    while True:
        location_cursor.execute(location_query, start_record)
        previous_start_record = start_record
        # go through what we retrieved
        for datum in location_cursor:
            start_record += 1
            chromosome = datum[0]
            if chromosome != last_chromosome:
                if chromosome not in bitsets:
                    bitsets[chromosome] = BinnedBitSet(MAX)
                last_chromosome = chromosome
                last_bitset = bitsets[chromosome]
            start, end = datum[1], datum[2]
            last_bitset.set_range(start, end - start)
        # stop if we're done
        if previous_start_record == start_record:
            break
    
    # doing this intersect operation speeds up our task significantly for full genomes
    gff_file = gff.input(sys.argv[1])    
    for line in gff_file.intersect(bitsets):
        # the one drawback is that intersect() was implemented to return strings, so we
        # need to parse it
        record = gff.input([line]).next()
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
            genotype = alleles[0] + ";" + alleles[0]
            reverse_genotype = reverse_complement(alleles[0]) + ";" + reverse_complement(alleles[0])
        else:
            genotype = ';'.join(sorted(alleles))
            reverse_genotype = ';'.join(sorted([reverse_complement(a) for a in alleles]))
        
        # query the database
        cursor.execute(query, (rs, genotype, reverse_genotype))
        data = cursor.fetchall()
        
        # move on if we don't have info
        if cursor.rowcount <= 0:
            continue
            
        for d in data:
            phenotype = d[0]
            pubmed = d[1]
            
            # format for output
            if record.start == record.end:
                coordinates = str(record.start)
            else:
                coordinates = str(record.start) + "-" + str(record.end)
            
            if pubmed != "":
                reference = "dbsnp:" + rs + ",pmid:" + pubmed.replace(",", ",pmid:")
            else:
                reference = "dbsnp:" + rs
            
            output = {
                "chromosome": record.seqname,
                "coordinates": coordinates,
                "genotype": genotype,
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
