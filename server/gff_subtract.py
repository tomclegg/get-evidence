#!/usr/bin/python
# Filename: gff_subtract.py

"""
usage: %prog gff_file_1 gff_file_2
"""

# Output the subtraction of two GFF files, with attributes taken from the first
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import sys
from utils import gff

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 3:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    g1 = gff.input(sys.argv[1])
    g2 = gff.input(sys.argv[2])
    for line in g1.subtract(g2):
        print line

if __name__ == "__main__":
    main()
