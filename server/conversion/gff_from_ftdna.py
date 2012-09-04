#!/usr/bin/python
# Filename: gff_from_ftdna.py
"""Conversion of Family Tree DNA data to GFF for genome processing

The files should be interpretable by GET-Evidence's genome processing system.                                                                                 
To see command line usage, run with "-h" or "--help".
"""

import os
import re
import sys
import csv
from optparse import OptionParser

GETEV_MAIN_PATH = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
if not GETEV_MAIN_PATH in sys.path:
    sys.path.insert(1, GETEV_MAIN_PATH)
del GETEV_MAIN_PATH

from utils import autozip

DEFAULT_BUILD = "b36"


def convert(genotype_input):
    """Take in Family Tree genotype data, yield GFF formatted lines"""
    genotype_data = genotype_input
    if isinstance(genotype_input, str):
        genotype_data = csv.reader(autozip.file_open(genotype_input, 'r'))
    else:
        genotype_data = csv.reader(genotype_input)

    # Currently Family Tree DNA appears to only be in build 36 format. 
    # There doesn't appear to be any record in the files regarding which 
    # build was used.
    build = "b36"
    yield "##genome-build " + build

    header_row = genotype_data.next()
    col = dict()
    for i in range(len(header_row)):
        col[header_row[i]] = i

    for row in genotype_data:
        variants = list(row[col['RESULT']])
        if variants[0] == '-' or variants[0] == 'I' or variants[0] == 'D':
            continue
        chromosome = 'chr' + row[col['CHROMOSOME']]
        pos_start = row[col['POSITION']]
        pos_end = pos_start

        attributes = ''
        if variants[0] == variants[1]:
            attributes = 'alleles ' + variants[0]
        else:
            attributes = 'alleles ' + variants[0] + '/' + variants[1]
        if re.match('rs', row[col['RSID']]):
            attributes = attributes + '; db_xref dbsnp:' + row[col['RSID']]

        output = [chromosome, "FTDNA", "SNP", pos_start, pos_end, '.', '+', 
                  '.', attributes]
        yield "\t".join(output)

def convert_to_file(genotype_input, output_file):
    """Convert a Family Tree DNA file and output GFF-formatted data to file"""
    output = output_file  # default assumes writable file object
    if isinstance(output_file, str):
        output = autozip.file_open(output_file, 'w')
    conversion = convert(genotype_input)
    for line in conversion:
        output.write(line + "\n")
    output.close()

def main():
    # Parse options
    usage = ("\n%prog -i inputfile [-o outputfile]\n"
             "%prog [-o outputfile] < inputfile")
    parser = OptionParser(usage=usage)
    parser.add_option("-i", "--input", dest="inputfile",
                      help="read Family Tree DNA data from INFILE (automatically "
                      "uncompress if *.zip, *.gz, *.bz2)", metavar="INFILE")
    parser.add_option("-o", "--output", dest="outputfile",
                      help="write report to OUTFILE (automatically compress "
                      "if *.gz, or *.bz2)", metavar="OUTFILE")
    options, args = parser.parse_args()

    # Handle input
    if sys.stdin.isatty():  # false if data is piped in
        var_input = options.inputfile
    else:
        var_input = sys.stdin

    # Handle output
    if options.outputfile:
        convert_to_file(var_input, options.outputfile)
    else:
        for line in convert(var_input):
            print line


if __name__ == "__main__":
    main()
