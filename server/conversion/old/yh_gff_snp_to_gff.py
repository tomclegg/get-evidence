#!/usr/bin/python
# Filename: yh_gff_to_gff.py

"""
usage: %prog yh_gff
"""

# Output standard GFF2 record for each entry in file
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, sys
from utils import gff

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    yh_gff = gff.input(sys.argv[1], version=3)
    
    for record in yh_gff:
        # SNPs only, please
        if record.feature != "SNP":
            continue
        
        # downgrade
        record.version = 2

        # standardize a few things about the GFF record
        alleles = record.attributes["allele"].split("/")
        if len(alleles) == 2 and alleles[0] == alleles[1]:
            record.attributes["alleles"] = alleles[0]
        else:
            record.attributes["alleles"] = "/".join(alleles)
        del record.attributes["allele"]
        
        record.attributes["ref_allele"] = record.attributes["ref"]
        del record.attributes["ref"]
        
        if record.attributes["alleles"].find("/") == -1:
            record.attributes["counts"] = record.attributes["support1"]
        else:
            record.attributes["counts"] = "%s/%s" % (record.attributes["support1"],
                                                     record.attributes["support2"])
            del record.attributes["support2"]
        del record.attributes["support1"]
        
        print record

if __name__ == "__main__":
    main()