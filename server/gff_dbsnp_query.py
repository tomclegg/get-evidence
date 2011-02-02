#!/usr/bin/python
# Filename: gff_query_dbsnp.py

"""
usage: %prog gff_file dbsnp_file [output_file]
"""
# Append db_xref (or, for GFF3, Dbxref) attribute with dbSNP information, if available
# ---

import gzip, re
from utils import doc_optparse, gff

class dbSNP:
    def __init__(self, filename):
        self.f = open(filename)
        self.data = self.f.readline().split()
        self.position = (self.data[1], int(self.data[2]))

    def up_to_position(self, position):
        while (self.data and self.comp_position(self.position, position) < 0):
            self.data = self.f.readline().split()
            if self.data:
                self.position = (self.data[1], int(self.data[2]))
            else:
                self.position = None
        return self.position

    def comp_position(self, position1, position2):
        # positions are tuples of chromosome (str) and position (int)
        if (position1[0] != position2[0]):
            return cmp(position1[0],position2[0])
        else:
            return cmp(position1[1],position2[1])

def match2dbSNP(gff_input_file, dbsnp_file):
    # Set up dbSNP input
    dbSNP_input = dbSNP(dbsnp_file)

    # Create genome_file record generator
    gff_data = None
    if isinstance(gff_input_file, str) and (re.match(".*\.gz$", gff_input_file)):
        gff_data = gff.input(gzip.open(gff_input_file))
    else:
        gff_data = gff.input(gff_input_file)

    for record in gff_data:  
        if record.feature == "REF":
            yield str(record)
            continue

        # chromosome prefix not used by dbSNP, so it is removed if present
        if record.seqname.startswith("chr") or record.seqname.startswith("Chr"):
            chromosome = record.seqname[3:]
        else:
            chromosome = record.seqname

        # position is adjusted to match the zero-start used by dbSNP positions
        record_position = (chromosome, record.start - 1)

        dbSNP_position = dbSNP_input.up_to_position(record_position)
        dbSNP_data = dbSNP_input.data

        if (dbSNP_position and dbSNP_input.comp_position(dbSNP_position,record_position) == 0):
            dbSNP_datum = "dbsnp:rs%s" % dbSNP_data[0]
            record_dbxref_data = []
            if record.version >= 3:
                if "Dbxref" in record.attributes:
                    record_dbxref_data = record.attributes["Dbxref"].split(",")
                if not any([re.search(dbSNP_data[0],datum) for datum in record_dbxref_data]):
                    record_dbxref_data.append(dbSNP_datum)
                    record.attributes["Dbxref"] = ",".join(record_dbxref_data)
            else:
                if "db_xref" in record.attributes:
                    record_dbxref_data = record.attributes["db_xref"].split(",")
                if not any([re.search(dbSNP_data[0],datum) for datum in record_dbxref_data]):
                    record_dbxref_data.append(dbSNP_datum)
                    record.attributes["db_xref"] = ",".join(record_dbxref_data)
        yield str(record)

def match2dbSNP_to_file(gff_input_file, dbsnp_file, output_file):
    # Set up output file
    f_out = None
    if isinstance(output_file, str):
        # Treat as path
        if (re.match(".*\.gz", output_file)):
            f_out = gzip.open("f_out", 'w')
        else:
            f_out = open(output_file, 'w')
    else:
        # Treat as writeable file object
        f_out = output_file

    out = match2dbSNP(gff_input_file, dbsnp_file)
    for line in out:
        f_out.write(line + "\n")
    f_out.close()

def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)

    if len(args) < 2:
        doc_optparse.exit()  # Error
    elif len(args) < 3:
        out = match2dbSNP(args[0], args[1])
        for line in out:
            print line
    else:
        match2dbSNP_to_file(args[0], args[1], args[2])

if __name__ == "__main__":
    main()

