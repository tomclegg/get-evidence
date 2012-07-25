#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""
usage: %prog gff_file
"""

# Read SNPs in GFF format, output in JSON format
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, string, sys
from copy import copy
import simplejson as json
from utils import gff

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    gff_file = gff.input(sys.argv[1])    
    for record in gff_file:
        # lightly parse alleles
        alleles = record.attributes["alleles"].strip("\"").split("/")
        ref_allele = record.attributes["ref_allele"].strip("\"")

        # compress identical alleles like "A/A" into just "A"
        while len(alleles) > 1 and alleles[0].upper() == alleles[1].upper():
            alleles.pop(0)

        trait_allele = None;

        # determine zygosity
        if len(alleles) == 1:
            zygosity = "hom"
            trait_allele = alleles[0]
        else:
            zygosity = "het"

        genotype = "/".join(alleles)
        if ref_allele in alleles:
            leftover_alleles = copy(alleles)
            leftover_alleles.remove(ref_allele)
            genotype = ref_allele + "/" + "/".join(leftover_alleles)
            if not trait_allele and len(leftover_alleles) == 1:
                trait_allele = leftover_alleles[0]

        # get dbSNP ID
        if "db_xref" in record.attributes:
            dbSNP_ID = record.attributes["db_xref"].lstrip("dbsnp:")
        else:
            dbSNP_ID = None

        # examine each amino acid change
        if "amino_acid_changes" in record.attributes:
            amino_acid_changes = record.attributes["amino_acid"].strip("\"").split("/")
        else:
            amino_acid_changes = list()
        for a in amino_acid_changes:
            amino_acid = a.split(" ")
            gene = amino_acid.pop(0) # the first item is always the gene name

            aa_done = {}
            for amino_acid_change_and_position in amino_acid:

                if amino_acid_change_and_position in aa_done:
                    continue
                aa_done[amino_acid_change_and_position] = 1

                if record.start == record.end:
                    coordinates = str(record.start)
                else:
                    coordinates = str(record.start) + "-" + str(record.end)

                output = {
                    "chromosome": record.seqname,
                    "coordinates": coordinates,
                    "gene": gene,
                    "amino_acid_change": amino_acid_change_and_position,
                    "genotype": genotype,
                    "ref_allele": ref_allele,
                    "trait_allele": trait_allele,
                    "zygosity": zygosity,
                    "variant": str(record),
                }
                if (dbSNP_ID):
                    output["dbSNP"] = dbSNP_ID

                print json.dumps(output)

        # Print one json line if there were no amino acid changes
        if (not amino_acid_changes):
            if record.start == record.end:
                coordinates = str(record.start)
            else: 
                coordinates = str(record.start) + "-" + str(record.end)
            output = {
                "chromosome": record.seqname,
                "coordinates": coordinates,
                "genotype": genotype,
                "ref_allele": ref_allele,
                "trait_allele": trait_allele,
                "zygosity": zygosity,
                "variant": str(record)
            }
            if (dbSNP_ID):
                output["dbSNP"] = dbSNP_ID
            print json.dumps(output)    



if __name__ == "__main__":
    main()
