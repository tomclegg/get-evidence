#!/usr/bin/python
# Filename: cgi_to_gff.py

"""
usage: %prog CGI-Variations-Compact.csv ...
"""

# Output GFF record for each entry in file(s)
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, sys

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    for line in fileinput.input():
        if line.startswith("#"):
            continue
        l = line.strip().split(",")
        if len(l) < 10 or l[4] not in ["=", "snp"] or l[5] not in ["=", "snp"]:
            continue
        
        chr = l[1]
        # for now, we treat PAR1 and PAR2 normally, with chrX coordinates
        if chr in ["PAR1", "PAR2", "chrXnonPAR"]:
            chr = "chrX"
        elif chr == "chrYnonPAR":
            chr = "chrY"
        
        out_line = chr
        out_line += "\tcgi\tSNP\t"
        out_line += str(long(l[2]) + 1) + "\t" + l[3]
        out_line += "\t.\t+\t.\t"
        
        if l[7] == l[8]:
            out_line += "alleles " + l[7]
        else:
            out_line += "alleles " + l[7] + "/" + l[8]
        
        out_line += ";ref_allele " + l[6]
        out_line += ";total_score " + l[9]
        
        print out_line

if __name__ == "__main__":
    main()