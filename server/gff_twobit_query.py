#!/usr/bin/python
# Filename: gff_query_twobit.py

"""
usage: %prog gff_file twobit_file [options]
  -d, --diff: output only records where the ref_allele attribute did not exist or has been changed
"""

# Append ref_allele attribute with information from the 2bit file
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import sys
from utils import doc_optparse, gff, twobit

def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)
    
    if len(args) < 2:
        doc_optparse.exit()
    
    # try opening the file both ways, in case the arguments got confused
    try:
        gff_file = gff.input(args[1])
        twobit_file = twobit.input(args[0])
    except Exception:
        gff_file = gff.input(args[0])
        twobit_file = twobit.input(args[1])
    
    for record in gff_file:
        if record.seqname.startswith("chr"):
            chr = record.seqname
        else:
            chr = "chr" + record.seqname
        
        ref_seq = twobit_file[chr][(record.start - 1):record.end]
        
        if option.diff:
            if record.attributes.has_key("ref_allele"):
                if record.attributes["ref_allele"].strip("\"") == ref_seq.upper():
                    continue
        
        record.attributes["ref_allele"] = ref_seq.upper()
        print record

if __name__ == "__main__":
    main()
