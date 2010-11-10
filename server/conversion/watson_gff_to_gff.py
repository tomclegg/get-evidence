#!/usr/bin/python
# Filename: watson_gff_to_gff.py

"""
usage: %prog watson_gff
"""

# Output standardized GFF record for each entry in file
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, sys
from utils import gff

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    watson_gff = gff.input(sys.argv[1])
    
    for record in watson_gff:
        # standardize feature name
        record.feature = "SNP"
        
        # double check alleles and allele counts
        alleles = record.attributes["alleles"]
        ref_allele = record.attributes["ref_allele"]
        ref_counts = int(record.attributes["ref_counts"])
        oth_counts = int(record.attributes["oth_counts"])
        
        # if we're homozygous for the other allele, then we exclude
        # the reference allele from the list of alleles
        if ref_counts == 0:
            if alleles.startswith(ref_allele):
                alleles = alleles[-1]
            else:
                alleles = alleles[0]
            counts = str(oth_counts)
        # otherwise, we make sure that the first allele listed is the
        # reference allele, and create the counts attribute accordingly
        elif alleles.startswith(ref_allele):
            counts = "%s/%s" % (ref_counts, oth_counts)
        # this shouldn't happen, but in case, we do it the other way
        # if necessary
        else:
            counts = "%s/%s" % (oth_counts, ref_counts)
        
        # now we modify the record and output
        record.attributes["alleles"] = alleles
        record.attributes["counts"] = counts
        del record.attributes["ref_counts"]
        del record.attributes["oth_counts"]
        print record

if __name__ == "__main__":
    main()