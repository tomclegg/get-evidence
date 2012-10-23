#!/usr/bin/python
# Filename: gff_from_decodeme.py
"""Conversion of deCODEme data to GFF for genome processing

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

def revcomp(sequence):
    comp = {'A': 'T', 'C': 'G', 'G': 'C', 'T': 'A'}
    output = [comp[x] for x in list(sequence)]
    output.reverse()
    return ''.join(output)

def convert(genotype_input):
    """Take in deCODEme genotype data, yield GFF formatted lines"""
    genotype_data = genotype_input
    if isinstance(genotype_input, str):
        genotype_data = csv.reader(autozip.file_open(genotype_input, 'r', 
                                                     'deCODEme_scan.csv'))
    else:
        genotype_data = csv.reader(genotype_input)

    # We are allowing people to donate only the 'deCODEme_scan.csv' file, 
    # which unfortunately lacks build information (it is stored separately 
    # in 'deCODEme_info.txt', but this file also contains the deCODEme 
    # username). So fare deCODEme files have only been build 36, and so 
    # this is the current assumption for data processing.
    build = "b36"
    yield "##genome-build " + build

    header_row = genotype_data.next()
    col = dict()
    for i in range(len(header_row)):
        col[header_row[i]] = i

    for row in genotype_data:
        variants = list(row[col['YourCode']])
        if variants[0] == '-':
            continue
        chromosome = 'chr' + row[col['Chromosome']]
        strand = row[col['Strand']]
        if strand == '-':
            variants = [revcomp(x) for x in variants]
        pos_start = row[col['Position']]
        pos_end = pos_start

        attributes = ''
        if variants[0] == variants[1]:
            attributes = 'alleles ' + variants[0]
        else:
            attributes = 'alleles ' + variants[0] + '/' + variants[1]
        if re.match('rs', row[col['Name']]):
            attributes = attributes + '; db_xref dbsnp:' + row[col['Name']]

        output = [chromosome, "deCODEme", "SNP", pos_start, pos_end, '.', '+', 
                  '.', attributes]
        yield "\t".join(output)

def convert_to_file(genotype_input, output_file):
    """Convert a deCODEme file and output GFF-formatted data to file"""
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
                      help="read deCODEme data from INFILE (automatically "
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
