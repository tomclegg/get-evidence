#!/usr/bin/python
# Filename: gff_query_dbsnp_from_file.py

"""
usage: %prog gff_file
"""

# Append db_xref (or, for GFF3, Dbxref) attribute with dbSNP information, if available
# ---

import os, sys
from utils import gff
from config import DBSNP_SORTED

dbSNP_file = open(os.getenv('DATA') + "/" + DBSNP_SORTED)
dbSNP_data = dbSNP_file.readline().split()

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))

    # now read the file and loop through
    genome_file = gff.input(sys.argv[1])
    dbSNP_file = open(os.getenv('DATA') + "/" + DBSNP_SORTED)
    dbSNP_data = dbSNP_file.readline().split()

    for record in genome_file:
        if record.seqname.startswith("chr"):
            chromosome = record.seqname[3:]
        else:
            chromosome = record.seqname

        while (dbSNP_data and (dbSNP_data[1] < chromosome or \
                (dbSNP_data[1] == chromosome and int(dbSNP_data[2]) < record.start - 1))):
            dbSNP_data = dbSNP_file.readline().split()
            
        if (dbSNP_data and int(dbSNP_data[2]) == record.start - 1):
            if record.version >= 3:
                record.attributes["Dbxref"] = "dbsnp:rs%s" % dbSNP_data[0]
            else:
                record.attributes["db_xref"] = "dbsnp:rs%s" % dbSNP_data[0]
        print record



if __name__ == "__main__":
    main()

