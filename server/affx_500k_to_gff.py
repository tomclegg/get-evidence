#!/usr/bin/python
# Filename: affx_500k_to_gff.py

"""
usage: %prog affx_500k_file_1 affx_500k_file_2 ...
"""

# Output GFF for each entry in file(s)
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, sys, MySQLdb
from utils.biopython_utils import reverse_complement
from config import DB_HOST, DB_READ_USER, DB_READ_PASSWD, DB_READ_DATABASE

probe_set_id_select_query = '''
SELECT dbsnp_rs_id, chr, pos, strand, allele_a, allele_b FROM affx_mapping250k_nsp
WHERE probe_set_id=%s
UNION SELECT dbsnp_rs_id, chr, pos, strand, allele_a, allele_b FROM affx_mapping250k_sty
WHERE probe_set_id=%s;
'''

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # first, try to connect to the databases
    try:
        conn = MySQLdb.connect(host=DB_HOST, user=DB_READ_USER, passwd=DB_READ_PASSWD, db=DB_READ_DATABASE)
        cursor = conn.cursor()
    except MySQLdb.OperationalError, message:
        print "Error %d while connecting to database: %s" % (message[0], message[1])
        sys.exit()

    # read our affy data
    for line in fileinput.input():
        # skip header
        if line.startswith('Mapping') or line.startswith('\t'):
            continue
        
        # parse values
        values = line.strip().split('\t');
        probe_set_id = values[1]
        ras_1 = values[7]
        ras_2 = values[8]
        call = values[9]
        zone = values[10]
        
        # move on while we're ahead if it's a NoCall
        if call == "NoCall":
            continue
        
        # search for info about each item
        cursor.execute(probe_set_id_select_query, [probe_set_id, probe_set_id])
        data = cursor.fetchone()
        
        # this should never happen!
        # after all, we're looking at stuff from a chip that's paired
        # to the data in the database
        if not data:
            raise ValueError
        
        # move on if there's no info on where on the genome it falls
        if data[1] == "--":
            continue
        
        # figure out the chromosome name
        chr = "chr%s" % (data[1])
        
        # figure out what those A's and B's mean
        # we must replace the "A" designation first, then "B", because
        # "A" is also an actual unambiguous nucleotide, while "B" is not
        actual_call = call.replace("A", data[4]).replace("B", data[5])
        if data[1] != "--" and data[3] == "-":
            actual_call = reverse_complement(actual_call)
        
        # prepare output
        out_line = chr
        out_line += "\taffx\tsnp\t"
        out_line += "%s\t%s" % (data[2], data[2]) # info from the affx databases is 1-based
        out_line += "\t.\t+\t.\t"
        
        if actual_call[0] != actual_call[1]:
            out_line += "alleles " + "/".join(sorted(list(actual_call)))
        else:
            out_line += "alleles " + actual_call[0]
        
        out_line += ";db_xref affx:%s dbsnp:%s" % (probe_set_id, data[0])
        out_line += ";ras_1 " + ras_1
        out_line += ";ras_2 " + ras_2
        out_line += ";call_zone " + zone
        
        # now actually print it
        print out_line
        
    # close database cursor and connection
    cursor.close()
    conn.close()

if __name__ == "__main__":
    main()
