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

class dbSNP:
    def __init__(self, filename):
        self.f = open(filename)
        self.data = self.f.readline().split()
        self.position = (self.data[1], int(self.data[2]))

    def up_to_position(self, position):
        while (self.data and self.comp_position(self.position, position) < 0):
            self.data = self.f.readline().split()
            self.position = (self.data[1], int(self.data[2]))
        return self.position

    def comp_position(self, position1, position2):
        # positions are tuples of chromosome (str) and position (int)
        if (position1[0] != position2[0]):
            return cmp(position1[0],position2[0])
        else:
            return cmp(position1[1],position2[1])
            

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))

    # Set up dbSNP input
    dbSNP_input = dbSNP(os.getenv('DATA') + "/" + DBSNP_SORTED)

    # Loop through genome file
    genome_file = gff.input(sys.argv[1])
    for record in genome_file:
        # chromosome prefix not used by dbSNP, so it is removed if present
        if record.seqname.startswith("chr") or record.seqname.startswith("Chr"):
            chromosome = record.seqname[3:]
        else:
            chromosome = record.seqname

        # position is adjusted to match the zero-start used by dbSNP positions
        record_position = (chromosome, record.start - 1)

        dbSNP_position = dbSNP_input.up_to_position(record_position)
        dbSNP_data = dbSNP_input.data
    
        if (dbSNP_input.comp_position(dbSNP_position,record_position) == 0):
            if record.version >= 3:
                record.attributes["Dbxref"] = "dbsnp:rs%s" % dbSNP_data[0]
            else:
                record.attributes["db_xref"] = "dbsnp:rs%s" % dbSNP_data[0]
        print record



if __name__ == "__main__":
    main()

