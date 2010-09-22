#!/usr/bin/python
# Filename: 23andme_to_gff.py

"""
usage: %prog genome_Foo_Bar.txt
"""

# Output GFF record for each entry in file(s)
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, sys

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))

    for line in fileinput.input():
        if line.startswith("#"):
            continue
        fields = line.strip().split("\t")
        if len(fields) != 4:
            raise AssertionError("Input line is malformed: " + line)

        rsid, chromosome, position, genotype = fields

        # We don't currently know how to handle either insertions/deletions
        # or "D" alleles, so ignore those for now.
        if genotype in ("II", "--", "DD", "DI"):
            continue

        # Mitochondrial DNA -- 23andme uses "MT", trait-o-matic expects "chrM"
        if chromosome == "MT":
            chromosome = "M"

        if not chromosome.startswith("chr"):
            chromosome = "chr" + chromosome

        # .gff field 1 = chromosome
        out_line = chromosome

        # .gff field 2 = data source
        out_line += "\t23andme"

        # .gff field 3 = feature type
        out_line += "\tSNP"

        # .gff field 4 = start position
        out_line += "\t" + str(position)

        # .gff field 5 = end position
        out_line += "\t" + str(position)

        # .gff field 6 = score
        out_line += "\t."

        # .gff field 7 = strand
        out_line += "\t+"

        # .gff field 8 = frame
        out_line += "\t."

        # .gff field 9 = attributes
        out_line += "\t"
        if genotype[0] == genotype[1]:
            out_line += "alleles " + genotype[0]
        else:
            out_line += "alleles " + genotype[0] + "/" + genotype[1]

        print out_line

if __name__ == "__main__":
    main()
