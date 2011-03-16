#!/usr/bin/python
# Filename: 23andme_to_gff.py
"""Conversion of 23andMe data to GFF for genome processing

The files should be interpretable by GET-Evidence's genome processing system.                                                                                 
To see command line usage, run with "-h" or "--help".
"""

import re
import sys
import bz2
import gzip
import zipfile
from optparse import OptionParser

DEFAULT_BUILD = "b36"

def autozip_file_open(filename, mode='r'):
    """Return file obj, with compression if appropriate extension is given"""
    if re.search("\.zip", filename):
        archive = zipfile.ZipFile(filename, mode)
        if mode == 'r':
            files = archive.infolist()
            if len(files) == 1:
                if hasattr(archive, "open"):
                    return archive.open(files[0])
                else:
                    sys.exit("zipfile.ZipFile.open not available. " +
                             "Upgrade python to 2.6 to work with " +
                             "zip-compressed files!")
            else:
                sys.exit("Zip archive " + filename + 
                         " has more than one file!")
        else:
            sys.exit("Zip archive only supported for reading.")
    elif re.search("\.gz", filename):
        return gzip.GzipFile(filename, mode)
    elif re.search("\.bz2", filename):
        return bz2.BZ2File(filename, mode)
    else:
        return open(filename, mode)

def convert(genotype_input):
    """Take in 23andme genotype data, yield GFF formatted lines"""
    genotype_data = genotype_input
    if isinstance(genotype_input, str):
        genotype_data = autozip_file_open(genotype_input, 'r')
    build = DEFAULT_BUILD
    header_done = False
    for line in genotype_data:
        # Handle the header, get the genome build if you can.
        if not header_done:
            if re.match("#", line):
                if re.search("human assembly build 37", line):
                    build = "b37"
                elif re.search("human assembly build 36", line):
                    build = "b36"
                continue
            else:
                yield "##genome-build " + build
                header_done = True
        data = line.rstrip('\n').split()
        if len(data) < 3:
            continue
        if data[1] == "MT":
            chromosome = 'chrM'
        else:
            chromosome = 'chr' + data[1]
        pos_start = data[2]
        pos_end = data[2]
        # Ignore uncalled or indel positions.
        if not (re.match(r'[ACGT]{1,2}', data[3])):
            continue
        if len(data[3]) > 1:
            if data[3][0] == data[3][1]:
                attributes = 'alleles ' + data[3][0]
            else:
                attributes = 'alleles ' + data[3][0] + '/' + data[3][1]
        else:
            attributes = 'alleles ' + data[3]
        if re.match('rs', data[0]):
            attributes = attributes + '; db_xref dbsnp:' + data[0]
        output = [chromosome, "CGI", "SNP", pos_start, pos_end, '.', '+', 
                  '.', attributes]
        yield "\t".join(output)

def convert_to_file(genotype_input, output_file):
    """Convert a 23andme file and output GFF-formatted data to file"""
    output = output_file  # default assumes writable file object
    if isinstance(output_file, str):
        output = autozip_file_open(output_file, 'w')
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
                      help="read CGI data from INFILE (automatically "
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
