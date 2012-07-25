#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

'''Takes two amino acids with single letter code,
returns the BLOSUM100 value for the pair.
Uses get-evidence/public_html/lib/blosum100.txt'''

import os, sys, re

class blosum100:
    def __init__(self, filename=""):
        if (len(filename) == 0):
            filename = re.sub(r'/server',r'/public_html/lib/blosum100.txt', os.getenv('CORE'));

        # read in blosum100 file: "../public_html/lib/blosum100.txt"
        f = open(filename)
        aa1 = list()
        self.blosum = dict()
        for line in f:
            if line.startswith('#'):
                continue
            else:
                if (len(aa1) == 0):
                    aa1 = line.strip().split()
                else:
                    data = line.strip().split()
                    aa2 = data.pop(0)
                    for i in range(len(data)):
                        aa_pair = (aa1[i], aa2)
                        self.blosum[aa_pair] = int(data[i])
                    

    def value(self, aa1, aa2):
        try:
            v = self.blosum[(aa1, aa2)]
        except KeyError:
            v = self.blosum[(aa2, aa1)]
        return v

if __name__ == "__main__":
    if (len(sys.argv) > 2):
        blosum_matrix = blosum100()
        value = blosum_matrix.value(sys.argv[1], sys.argv[2])
        print value
    else:
        print __doc__
