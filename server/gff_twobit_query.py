#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""
usage: %prog gff_file twobit_file [output_file]
"""

# Append ref_allele attribute with information from the 2bit file
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import datetime, gzip, re
from utils import doc_optparse, gff, twobit
import sys

def match2ref(gff_input, twobit_filename):

    # Iff gff_filename is a string ending with ".gz", assume gzip compressed
    gff_file = None
    if isinstance(gff_input, str) and (re.match(".*\.gz$", gff_input)):
        gff_file = gff.input(gzip.open(gff_input))
    else:
        # GFF will interpret if gff_filename is string containing path 
        # to a GFF-formatted text file, or a string generator 
        # (e.g. file object) with GFF-formatted strings
        gff_file = gff.input(gff_input)
    
    twobit_file = twobit.input(twobit_filename)

    header_done = False
    
    # Process input data to get ref allele
    for record in gff_file:
        # Have to do this after calling the first record to
        # get the iterator to read through the header data
        if not header_done:
            yield "##gff-version " + gff_file.data[0]
            yield "##genome-build " + gff_file.data[1]
            yield "# Produced by: gff_twobit_query.py"
            yield "# Date: " + datetime.datetime.now().isoformat(' ')
            header_done = True
        
        # Skip REF lines
        if record.feature == "REF":
            yield str(record)
            continue

        # Add "chr" to chromosome ID if missing
        if record.seqname.startswith("chr"):
            chr = record.seqname
        else:
            chr = "chr" + record.seqname

        ref_seq = "-"  # represents variant with length zero
        if (record.end - (record.start - 1)) > 0:
            ref_seq = twobit_file[chr][(record.start - 1):record.end]
        if ref_seq == '':
            sys.stderr.write ("ERROR: this location does not exist in the reference genome. Start: %d, end: %d. Perhaps the input is aligned against a different reference genome?\n" % (record.start, record.end))
            sys.exit() 

        if record.attributes:
            # If reference at this pos, note this and remove attributes data.
            if ("alleles" in record.attributes and 
                record.attributes["alleles"] == ref_seq.upper()):
                record.feature = "REF"
                record.attributes = None
            else:
                record.attributes["ref_allele"] = ref_seq.upper()
            yield str(record)

def match2ref_to_file(gff_input, twobit_filename, output_file):
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

    out = match2ref(gff_input, twobit_filename)
    for line in out:
        f_out.write(line + "\n")
    f_out.close()

def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)
    
    if len(args) < 2:
        doc_optparse.exit()  # Error
    elif len(args) < 3:
        out = match2ref(args[0], args[1])
        for line in out:
            print line
    else:
        match2ref_to_file(args[0], args[1], args[2])

if __name__ == "__main__":
    main()
