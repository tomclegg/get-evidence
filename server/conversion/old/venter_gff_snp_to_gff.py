#!/usr/bin/python
# Filename: venter_gff_snp_to_gff.py

"""
usage: %prog venter_gff ...
"""

# Output standardized GFF record for each SNP in file(s)
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, sys

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    for line in fileinput.input():
        l = line.strip().split('\t')
        
        if len(l) < 9:
            break
        
        # filter on feature type and method
        if not (l[2].endswith("_SNP") and l[8].startswith("Method1")):
            continue
        
        out_line = "chr" + l[0]
        out_line += "\tCV\tSNP\t"
        out_line += str(int(l[3]) + 1) + "\t" + l[4]
        out_line += "\t.\t+\t.\t"
        
        # append attributes to output
        attributes = l[7].split(';')
        
        alleles = attributes[0].split('/')
        ref = alleles[0]
        
        if l[2].startswith("heterozygous"):
            out_line += "alleles " + "/".join(alleles)
        else:
            out_line += "alleles " + alleles[1]
        
        out_line += ";ref_allele " + ref
        out_line += ";RMR " + attributes[1][-1]
        out_line += ";TR " + attributes[2][-1]
        out_line += ";method " + l[8]
        out_line += ";ID " + l[1]
        
        print out_line

if __name__ == "__main__":
    main()