#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""
usage: %prog fastq ...
"""

# Output FASTA record for each FASTQ record in file(s)
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, sys

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # we drop all quality lines
    is_quality_line = None
    sequence_length = None
    quality_length = None
    
    for line in fileinput.input():
        if line.startswith("@") and sequence_length == quality_length:
            is_quality_line = False
            sequence_length = 0
            quality_length = 0
            print ">" + line[1:].rstrip()
            continue
        elif line.startswith("+") and not is_quality_line:
            is_quality_line = True
            continue
        elif not is_quality_line:
            l = line.rstrip()
            sequence_length += len(l)
            print l
        else:
            quality_length += len(line.rstrip())
    
if __name__ == "__main__":
    main()
