#!/usr/bin/python
# Filename: convert_to_gff.py

import os
import sys
from optparse import OptionParser
import detect_format
import cgivar_to_gff
import gff_from_23andme
import gff_from_decodeme
import gff_from_ftdna
import vcf_to_gff

# A bit of path manipulation to import autozip.py from ../utils/
GETEV_MAIN_PATH = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
if not GETEV_MAIN_PATH in sys.path:
    sys.path.insert(1, GETEV_MAIN_PATH)
del GETEV_MAIN_PATH
from utils import autozip


def convert(input_file, options=None):
    input_type = detect_format.detect_format(input_file)
    if input_type == 'GFF':
        input_data = autozip.file_open(input_file)
    elif input_type == 'CGIVAR':
        input_data = cgivar_to_gff.convert(input_file, options)
    elif input_type == '23ANDME':
        input_data = gff_from_23andme.convert(input_file)
    elif input_type == 'VCF':
        input_data = vcf_to_gff.convert(input_file, options)
    elif input_type == 'deCODEme':
        input_data = gff_from_decodeme.convert(input_file)
    elif input_type == 'FTDNA':
        input_data = gff_from_ftdna.convert(input_file)
    else:
        raise Exception("input format not recognized")

    for line in input_data:
        yield line

def convert_to_file(input_file, output_file):
    """Convert and output GFF-formatted data to file"""
    output = output_file  # default assumes writable file object
    if isinstance(output_file, str):
        output = autozip.file_open(output_file, 'w')
    conversion = convert(input_file)  # set up generator

    for line in conversion:
        output.write(line + "\n")
    output.close()

def main():
    # Parse options
    usage = "\n%prog -i inputfile [-o outputfile]\n" \
        + "%prog [-o outputfile] < inputfile"
    parser = OptionParser(usage=usage)
    parser.add_option("-i", "--input", dest="inputfile",
                      help="read genetic data from INFILE (automatically uncompress"
                      + " and convert as needed", metavar="INFILE")
    parser.add_option("-o", "--output", dest="outputfile",
                      help="write report to OUTFILE (automatically compress if "
                      + "*.gz, or *.bz2)", metavar="OUTFILE")
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
