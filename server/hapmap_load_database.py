#!/usr/bin/python
# Filename: hapmap_load_database.py

"""
usage: %prog file ...
"""

# Load HapMap data into the database, summing allele counts when necessary
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, math, os, sys
import MySQLdb
from config import DB_HOST, DB_UPDATE_USER, DB_UPDATE_PASSWD, DB_UPDATE_DATABASE

query = '''
INSERT INTO hapmap27
(rs_id, chr, start, end, strand, pop, ref_allele, ref_allele_freq,
ref_allele_count, oth_allele, oth_allele_freq, oth_allele_count, total_count)
VALUES
(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
ON DUPLICATE KEY UPDATE
ref_allele_freq=(ref_allele_count+VALUES(ref_allele_count))/(total_count+VALUES(total_count)),
oth_allele_freq=(oth_allele_count+VALUES(oth_allele_count))/(total_count+VALUES(total_count)),
ref_allele_count=ref_allele_count+VALUES(ref_allele_count),
oth_allele_count=oth_allele_count+VALUES(oth_allele_count),
total_count=total_count+VALUES(total_count);
'''

# ASW (A): African ancestry in Southwest USA
# CEU (C): Utah residents with Northern and Western European ancestry from the CEPH collection
# CHB (H): Han Chinese in Beijing, China
# CHD (D): Chinese in Metropolitan Denver, Colorado
# GIH (G): Gujarati Indians in Houston, Texas
# JPT (J): Japanese in Tokyo, Japan
# LWK (L): Luhya in Webuye, Kenya
# MEX (M): Mexican ancestry in Los Angeles, California
# MKK (K): Maasai in Kinyawa, Kenya
# TSI (T): Toscans in Italy
# YRI (Y): Yoruba in Ibadan, Nigeria (West Africa)

populations = {
    "African": "AFS",
    "Kenyan": "AFS",
    "Yoruba": "AFS",
    "Gujarati": "ASC",
    "Chinese": "ASE",
    "Japanese": "ASE",
    "CEPH": "EUR",
    "Italian": "EUR",
    "Mexican": "MEX"
}

def main():
    # first, try to connect to the database
    try:
        connection = MySQLdb.connect(host=DB_HOST, user=DB_UPDATE_USER, passwd=DB_UPDATE_PASSWD, db=DB_UPDATE_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        print "Error %d while connecting to database: %s" % (message[0], message[1])
        sys.exit()
    
    for line in fileinput.input():
        line = line.strip()
        
        # move on if it's a header row, which can be in the middle of a concatenated file
        if line.startswith("rs#"):
            continue
        
        l = line.split(' ')
        rs_id, chr = l[0], l[1]
        pos = int(l[2])
        start, end = pos - 1, pos
        strand = l[3]
        panel_lsid = l[8] # we use this to figure out which population it belongs to
        for p in populations.keys():
            if p in panel_lsid:
                pop = populations[p]
                break
        ref_allele, oth_allele = l[10], l[13]
        ref_allele_freq, oth_allele_freq = float(l[11]), float(l[14])
        ref_allele_count, oth_allele_count = int(l[12]), int(l[15])
        total_count = int(l[16])
        
        cursor.execute(query, (rs_id, chr, start, end, strand, pop, ref_allele, ref_allele_freq,
          ref_allele_count, oth_allele, oth_allele_freq, oth_allele_count, total_count))    
    
    # close database cursor and connection
    cursor.close()
    connection.close()

if __name__ == "__main__":
    main()
