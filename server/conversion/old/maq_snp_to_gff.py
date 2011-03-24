#!/usr/bin/python
# Filename: maq_snp_to_gff.py

"""
usage: %prog cns.final.snp.txt ...
"""

# Output GFF record for each entry in file(s)
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, sys

ambiguous_dna = {
    "M": ["A", "C"],
    "R": ["A", "G"],
    "W": ["A", "T"],
    "S": ["C", "G"],
    "Y": ["C", "T"],
    "K": ["G", "T"],
    "V": ["A", "C", "G"],
    "H": ["A", "C", "T"],
    "D": ["A", "G", "T"],
    "B": ["C", "G", "T"],
    "N": ["A", "C", "G", "T"]
}

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    for line in fileinput.input():
        l = line.strip().split('\t')
        
        if len(l) < 9:
            break
        
        out_line = l[0]
        out_line += "\tmaq\tSNP\t"
        out_line += l[1] + "\t" + l[1]
        out_line += "\t.\t+\t.\t"
        
        consensus = l[3]
        if consensus not in ["A", "C", "G", "T"]:
            out_line += "alleles " + '/'.join(ambiguous_dna[consensus])
        else:
            out_line += "alleles " + l[3]
        
        out_line += ";ref_allele " + l[2]
        out_line += ";read_depth " + l[5]
        
        print out_line

if __name__ == "__main__":
    main()