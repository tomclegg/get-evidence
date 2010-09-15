#!/usr/bin/python
# Filename: snpinduse_to_gff.py

"""
usage: %prog *.snpinduse.txt ...
"""

# Output GFF record for each entry in file(s)
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, sys

# based on <http://www.peterbe.com/plog/uniqifiers-benchmark>
def unique(seq): # not order preserving, but that's OK; we can sort it later
    return {}.fromkeys(seq).keys()

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    for line in fileinput.input():
        if not line.startswith("SNP:"):
            continue
        l = line.strip().split("|")
        if l[2] != "SS_STRAND_FWD":
            print >> sys.stderr, l[1]
            continue
        
        snp = l[1].split(":")
        details = snp[0].split("_")
        alleles = snp[1].split("/")
        
        out_line = details[3]
        out_line += "\t"
        out_line += details[0]
        out_line += "\tSNP\t"
        out_line += details[4] + "\t" + details[4]
        out_line += "\t.\t+\t.\t"
        
        out_line += "alleles " + '/'.join(unique(alleles))
        out_line += ";id " + details[1]
        
        print out_line

if __name__ == "__main__":
    main()