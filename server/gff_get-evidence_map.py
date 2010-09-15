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

location_query = '''
SELECT chrom, chromStart, chromEnd FROM latest LEFT JOIN caliban.snp129 dbsnp ON dbsnp.name = concat('rs',latest.rsid) WHERE rsid>0 AND chrom IS NOT NULL LIMIT %s,10000;
'''

query_aa = '''
SELECT inheritance,
 impact,
 summary_short,
 concat(gene,'-',aa_change) as variant_name,
 impact NOT IN ('unknown','none','not reviewed','benign','likely benign','uncertain benign')
  AND 0 < LENGTH(summary_short)
  AS worth_displaying
FROM latest
WHERE (gene=%s AND aa_change=%s)
'''

query_rsid = '''
SELECT inheritance,
 impact,
 summary_short,
 concat('rs',rsid) as variant_name,
 impact NOT IN ('unknown','none','not reviewed','benign','likely benign','uncertain benign')
  AND 0 < LENGTH(summary_short)
  AS worth_displaying
FROM latest
WHERE rsid=%s
'''

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) != 3:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # first, try to connect to the databases
    try:
        location_connection = MySQLdb.connect(cursorclass=MySQLdb.cursors.SSCursor, host=DB_HOST, user=GETEVIDENCE_USER, passwd=GETEVIDENCE_PASSWD, db=GETEVIDENCE_DATABASE)
        location_cursor = location_connection.cursor()
        connection = MySQLdb.connect(host=DB_HOST, user=GETEVIDENCE_USER, passwd=GETEVIDENCE_PASSWD, db=GETEVIDENCE_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        sys.stderr.write ("Error %d while connecting to database: %s" % (message[0], message[1]))
        sys.exit()
    
    # make sure the required table is really there
    try:
        cursor.execute ('DESCRIBE latest')
    except MySQLdb.Error:
        sys.stderr.write ("No 'latest' table => empty output")
        sys.exit()

    found_aa_for_rsid = dict()
    
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

        if "amino_acid" not in record.attributes:
            continue

        if rs_number > 0:
            found_aa_for_rsid[rs_number] = 1

        for gene_acid_base in record.attributes["amino_acid"].split("/"):

            # get amino acid change
            x = gene_acid_base.split(" ",1)
            gene = x[0]
            amino_acid_change_and_position = x[1]

            # convert to long form

            acid_change = re.sub(r' .*',r'', amino_acid_change_and_position)
            acid_change = re.sub(r'[A-Z]', lambda x: codon_123(x.group(0)), acid_change)
            acid_change = re.sub(r'TERM', 'Stop', acid_change)

            # query the database
            cursor.execute(query_aa, (gene, acid_change))
            data = cursor.fetchall()

            # if this gene/AA change caused a hit, stop here and report it
            if cursor.rowcount > 0:
                break

        if cursor.rowcount > 0:

            for d in data:
                inheritance = d[0]
                impact = d[1]
                if len(d[2]) > 0:
                    notes = d[2] + " ("
                    if impact == "not reviewed" or impact == "none" or impact == "unknown":
                        notes = notes + "impact not reviewed"
                    else:
                        notes = notes + impact
                    if inheritance == "dominant" or inheritance == "recessive":
                        notes = notes + ", " + inheritance + ")"
                    else:
                        notes = notes + ", inheritance pattern " + inheritance + ")"
                else:
                    notes = ""
                variant_name = d[3]
                display_flag = d[4]

                # format for output
                if record.start == record.end:
                    coordinates = str(record.start)
                else:
                    coordinates = str(record.start) + "-" + str(record.end)

                reference = "http://evidence.personalgenomes.org/" + variant_name

                output = {
                    "chromosome": record.seqname,
                    "coordinates": coordinates,
                    "gene": gene,
                    "amino_acid_change": amino_acid_change_and_position,
                    "amino_acid": record.attributes["amino_acid"],
                    "genotype": genotype,
                    "variant": str(record),
                    "phenotype": notes,
                    "reference": reference,
                    "display_flag": display_flag
                }
                print json.dumps(output)

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
    
    gff_file = gff.input(sys.argv[2])
    for line in gff_file.intersect(bitsets):
        record = gff.input([line]).next()
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
        rs_number = 0
        for x in xrefs:
            if x.startswith("dbsnp:rs"):
                rs_number = int(x.replace("dbsnp:rs",""))
                break

        # we wouldn't know what to do with this, so pass it up for now
        if len(alleles) > 2:
            continue

        # create the genotype string from the given alleles
        #TODO: do something about the Y chromosome
        trait_allele = None;
        if len(alleles) == 1:
            zygosity = "hom"
            trait_allele = alleles[0]
            alleles = [alleles[0], alleles[0]]
            genotype = alleles[0]
        else:
            zygosity = "het"
            genotype = '/'.join(sorted(alleles))

        if not (rs_number > 0) or rs_number in found_aa_for_rsid:
            continue

        cursor.execute(query_rsid, rs_number)
        data = cursor.fetchall()

        if ref_allele in alleles:
            leftover_alleles = copy(alleles)
            leftover_alleles.remove(ref_allele)
            genotype = ref_allele + "/" + "/".join(leftover_alleles)
            if not trait_allele and len(leftover_alleles) == 1:
                trait_allele = leftover_alleles[0]

        if cursor.rowcount > 0:
            for d in data:
                inheritance = d[0]
                impact = d[1]
                if len(d[2]) > 0:
                    notes = d[2] + " ("
                    if impact == "not reviewed" or impact == "none" or impact == "unknown":
                        notes = notes + "impact not reviewed"
                    else:
                        notes = notes + impact
                    if inheritance == "dominant" or inheritance == "recessive":
                        notes = notes + ", " + inheritance + ")"
                    else:
                        notes = notes + ", inheritance pattern " + inheritance + ")"
                else:
                    notes = ""
                variant_name = d[3]
                display_flag = d[4]

                # format for output
                if record.start == record.end:
                    coordinates = str(record.start)
                else:
                    coordinates = str(record.start) + "-" + str(record.end)

                reference = "http://evidence.personalgenomes.org/" + variant_name

                output = {
                    "chromosome": record.seqname,
                    "coordinates": coordinates,
                    "genotype": genotype,
                    "variant": str(record),
                    "phenotype": notes,
                    "reference": reference,
                    "ref_allele": ref_allele,
                    "trait_allele": trait_allele,
                    "zygosity": zygosity,
                    "display_flag": display_flag
                }
                print json.dumps(output)
    
    # close database cursor and connection
    cursor.close()
    connection.close()
    location_cursor.close()
    location_connection.close()

if __name__ == "__main__":
    main()
