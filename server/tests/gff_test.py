#!/usr/bin/python
# Filename: gff_test.py

# INPUT: gff
# for each entry in gff:
#    output internal id, attributes
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from utils import gff

def main():
    f = gff.input(sys.argv[1])
    for record in f:
        print record.id, record.attributes

if __name__ == "__main__":
    main()
