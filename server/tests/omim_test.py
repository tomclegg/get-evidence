#!/usr/bin/python
# Filename: omim_test.py

# INPUT: OMIM (txt)
# for each entry in txt:
#    count allelic variants
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, sys
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from utils import omim

def main():
    f = omim.input(sys.argv[1])
    av_count = 0
    for record in f:
        if record.allelic_variants is not None:
            av_count += len(record.allelic_variants)
    print av_count

if __name__ == "__main__":
    main()
