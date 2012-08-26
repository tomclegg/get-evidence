#!/usr/bin/python
# Filename: cgivar_to_gff.py
"""Conversion of Complete Genomics, Inc. (CGI) var ver 1.5 files to GFF files

The files should be interpretable by GET-Evidence's genome processing system.
To see command line usage, run with "-h" or "--help".
"""
import os
import re
import sys
import bz2
import gzip
import zipfile
from optparse import OptionParser

GETEV_MAIN_PATH = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
if not GETEV_MAIN_PATH in sys.path:
    sys.path.insert(1, GETEV_MAIN_PATH)
del GETEV_MAIN_PATH

from utils import autozip

DEFAULT_BUILD = "b36"


def process_header(vcf_line, build):
    """Process VCF header information to find genome build"""
    if (re.search('reference_sequence=([^= ]*)', vcf_line)):
        ref_seq = re.search('reference_sequence=([^= ]*)', vcf_line).groups()[0]
        if (re.search('37', ref_seq)):
            build = 'b37'
        elif (re.search('36', ref_seq)):
            build = 'b36'
    return build

def process_info(info_str):
    """Process "info" column of VCF and return dict"""
    data = info_str.split(';')
    info = dict()
    for item in data:
        if re.match('([^:]*?)=(.*)', item):
            [key, val] = re.match('([^:]*?)=(.*)', item).groups()
            info[key] = val
        else:
            info[item] = True
    return info


def process_line(vcf_line):
    """Converts a VCF variant line to a GFF format variant line"""
    data = vcf_line.rstrip('\n').split("\t")
    info = process_info(data[7])
    chrom = data[0]
    if not re.match('chr', chrom):
        chrom = 'chr' + chrom
    datatype = 'VCF'
    vartype = 'SNP'
    start = data[1]
    end = str(int(start) + (len(data[3]) - 1))
    if len(data[3]) != len(data[4]) or data[4] == '.':
        vartype = "INDEL"
        if data[3][0] == data[4][0]:
            data[3] = data[3][1:]
            data[4] = data[4][1:]
            start = str(int(start) + 1)
        if len(data[3]) == 0:
            data[3] = '-'
        if len(data[4]) == 0:
            data[4] = '-'
    elif len(data[3]) > 1:
        vartype = "SUB"
    attributes = []
    # Get alleles
    if 'AF' in info:
        if (float(info['AF']) == 0.5):
            alleles = data[3] + '/' + data[4]
        elif (float(info['AF']) == 1.0):
            alleles = data[4]
        else:
            return None
    else:
        return None
    attributes.append('alleles ' + alleles)
    attributes.append('ref_allele ' + data[3])
    if data[2]:
        data2_items = data[2].split(';')
        dbsnps = []
        for item in data2_items:
            if item != '.' and re.match('rs', item):
                dbsnps.append('dbsnp:' + item)
        if dbsnps:
            attributes.append('db_xref ' + ','.join(dbsnps))
    attr_str = ';'.join(attributes)
    output = [chrom, datatype, vartype, start, end, '.', '+', '.', attr_str]
    return '\t'.join(output)


def convert(vcf_input, options=None):
    """Generator that converts CGI var data to GFF-formated strings"""
    # Set up VCF input. Default is to assume a str generator.
    vcf_data = vcf_input
    if isinstance(vcf_input, str): 
        vcf_data = autozip.file_open(vcf_input, 'r')
     
    build = DEFAULT_BUILD
    header_done = False
    saw_chromosome = False
    for line in vcf_data:
        # Handle the header, get the genome build if you can.
        if not header_done:
            if re.match("#", line):
                build = process_header(line, build)
                continue
            else:
                # Output GFF header once we're done reading VCF header.
                yield "##genome-build " + build
                header_done = True
        if re.search("^\W*$", line): 
            continue

        if options and options.chromosome:
            data = line.rstrip('\n').split("\t")
            if (data[0] != options.chromosome and 
                'chr' + data[0] != options.chromosome):
                if saw_chromosome:
                    # Assume all base calls for a single chromosome 
                    # are in a contiguous block.
                    break
                continue
            saw_chromosome = True
        
        output = process_line(line)
        if output:
            yield output


def convert_to_file(vcf_input, output_file):
    """Convert a VCF file and output GFF-formatted data to file"""
    output = output_file  # default assumes writable file object
    if isinstance(output_file, str): 
        output = autozip.file_open(output_file, 'w')
    conversion = convert(vcf_input)  # set up generator
    for line in conversion:
        output.write(line + "\n")
    output.close()


def main():
    # Parse options
    usage = "\n%prog -i inputfile [-o outputfile]\n" \
            + "%prog [-o outputfile] < inputfile"
    parser = OptionParser(usage=usage)
    parser.add_option("-i", "--input", dest="inputfile",
                      help="read CGI data from INFILE (automatically uncompress"
                      + " if *.zip, *.gz, *.bz2)", metavar="INFILE")
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

