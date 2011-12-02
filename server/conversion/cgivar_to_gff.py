#!/usr/bin/python
# Filename: cgivar_to_gff.py
"""Conversion of Complete Genomics, Inc. (CGI) var ver 1.5 files to GFF files

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
                    sys.exit("zipfile.ZipFile.open not available. Upgrade " \
                        + "python to 2.6 to work with zip-compressed files!")
            else:
                sys.exit("Zip archive " + filename + " has more than one file!")
        else:
            sys.exit("Zip archive only supported for reading.")
    elif re.search("\.gz", filename):
        return gzip.GzipFile(filename, mode)
    elif re.search("\.bz2", filename):
        return bz2.BZ2File(filename, mode)
    else:
        return open(filename, mode)

def process_full_position(data):
    """Return GFF-formated string for when all alleles called on same line"""
    chrom, begin, end, feat_type, ref_allele, var_allele = data[3:9]

    # Skip regions that are unmatchable, uncovered, or pseudoautosomal-in-X.
    if (data[6] == "no-ref" 
            or re.match("no-call",data[6]) 
            or re.match("PAR-called-in-X",data[6])):
        return None

    # GFF uses 1-based start & 1-based end, CGI has 0-based start, 1-based end.
    start_onebased = str(int(begin) + 1)

    if feat_type == "ref": 
        feat_type, attributes = "REF", "."
    else:
        # Default feature type is INDEL unless lengths are equal.
        feat_type = "INDEL"
        if len(ref_allele) == 1 and len(var_allele) == 1: 
            feat_type = "SNP"
        elif len(ref_allele) == len(var_allele): 
            feat_type = "SUB"

        # Get alleles and dbSNP data, store in the "attributes" field. 
        # Our convention is to use '-' for zero-length sequences.
        if not ref_allele: 
            ref_allele = '-'
        if not var_allele: 
            var_allele = '-'
        dbsnp_data = []
        if data[11]: 
            dbsnp_data = data[11].split(";")
        attributes = "alleles " + var_allele + ";ref_allele " \
                        + ref_allele + dbsnp_string(dbsnp_data)
    # Output GFF-formatted string
    output = [chrom, "CGI", feat_type, start_onebased, 
              end, ".", "+", ".", attributes]
    return "\t".join(output)

def process_allele(allele_data, dbsnp_data):
    """Combine data from multiple lines refering to a single allele.
    
    Returns four strings containing: concatenated variant sequence, 
    concatenated reference sequence, 1-based start, and end.
    """
    start_1based = str(int(allele_data[0][4]) + 1)
    end = allele_data[-1][5]
    allele_seq = ref_seq = ""
    for data in allele_data:
        # We reject allele data if any subset of the data has a no-call.
        if re.match("no-call", data[6]) or re.match("no-call", data[6]):
            allele_seq = "NA"
            ref_seq = "NA"
            break
        else:
            allele_seq = allele_seq + data[8]
            ref_seq = ref_seq + data[7]
            if data[11]:
                data = data[11].split(";")
                for item in data: 
                    dbsnp_data.append(item)
    return allele_seq, ref_seq, start_1based, end

def process_split_position(data, cgi_input):
    """Process CGI var where alleles are reported separately."""
    assert data[2] == "1"

    # Get all lines for each allele. Note that this means we'll end up with data
    # from one line ahead stored in 'next_data'; it will be handled at the end.
    strand1_data = [ data ]
    strand2_data = [ ]
    next_data = cgi_input.next().rstrip('\n').split("\t")
    while next_data[2] == "1":
        strand1_data.append(next_data)
        next_data = cgi_input.next().rstrip('\n').split("\t")
    while next_data[2] == "2":
        strand2_data.append(next_data)
        next_data = cgi_input.next().rstrip('\n').split("\t")

    # Process all the lines to get concatenated sequences and other data.
    dbsnp_data = []
    strand1_proc = process_allele(strand1_data, dbsnp_data)
    a1_seq, r1_seq, a1_start_1based, a1_end = strand1_proc
    strand2_proc = process_allele(strand2_data, dbsnp_data)
    a2_seq, r2_seq, a2_start_1based, a2_end = strand2_proc
    if not (a1_seq == "NA" or a2_seq == "NA"):
        # Check that reference sequence and positions match.
        assert r1_seq == r2_seq
        assert a1_start_1based == a2_start_1based
        assert a1_end == a2_end
        # Default feature type is indel unless lengths are all the same.
        feat_type = "INDEL"
        if len(a1_seq) == 1 and len(a2_seq) == 1 and a1_end == a1_start_1based:
            feat_type = "SNP"
        elif len(a1_seq) == len(a2_seq) == len(r1_seq): 
            feat_type = "SUB"
        # Our convention is to use '-' for zero-length sequence.
        if len(a1_seq) == 0: 
            a1_seq = "-"
        if len(a2_seq) == 0: 
            a2_seq = "-"
        if len(r1_seq) == 0: 
            r1_seq = "-"
        # Store alleles and dbSNP data in attributes, with heterozygous 
        # alleles separated by a '/'.
        if a1_seq == a2_seq:
            attributes = "alleles " + a1_seq
        else:
            attributes = "alleles " + a1_seq + "/" + a2_seq   
        attributes = attributes + ";ref_allele " + r1_seq
        attributes = attributes + dbsnp_string(dbsnp_data)
        output = [strand1_data[0][3], "CGI", feat_type, a1_start_1based, 
                  a1_end, ".", "+", ".", attributes]
        yield "\t".join(output)
    # Handle the remaining line (may recursively call this function if it's 
    # the start of a new region with separated allele calls).
    if next_data[2] == "all" or next_data[1] == "1":
        out = process_full_position(next_data)
    else:
        out = process_split_position(next_data, cgi_input)
    if out:
        if isinstance(out, str): 
            yield out
        else: 
            for line in out: 
                yield line

def convert(cgi_input, options=dict()):
    """Generator that converts CGI var data to GFF-formated strings"""
    # Set up CGI input. Default is to assume a str generator.
    cgi_data = cgi_input
    if isinstance(cgi_input, str): 
        cgi_data = autozip_file_open(cgi_input, 'r')
     
    build = DEFAULT_BUILD    
    header_done = False
    saw_chromosome = False
    for line in cgi_data:
        # Handle the header, get the genome build if you can.
        if not header_done:
            if re.match("#", line):
                if re.match("#GENOME_REFERENCE.*NCBI build 37", line): 
                    build = "b37"
                elif re.match("#GENOME_REFERENCE.*NCBI build 36", line): 
                    build = "b36"
                continue
            else:
                # Output GFF header once we're done reading CGI's.
                yield "##genome-build " + build
                header_done = True
        if re.search("^\W*$", line): 
            continue
        # TODO: use table header instead of assuming which column to use
        if re.search("^>", line): 
            continue

        # Handle data
        data = line.rstrip('\n').split("\t")

        if options.chromosome:
            if data[3] != options.chromosome:
                if saw_chromosome:
                    # Assume all base calls for a single chromosome are in a contiguous block
                    break
                continue
            saw_chromosome = True

        if data[2] == "all" or data[1] == "1":
            # The output from process_full_position is a str.
            out = process_full_position(data)
        else:
            assert data[2] == "1"
            # The output from process_split_position is a str generator;
            # it may end up calling itself recursively.
            out = process_split_position(data, cgi_data)
        if not out: 
            continue
        if isinstance(out, str): 
            yield out
        else: 
            for line in out: 
                yield line

def dbsnp_string(data):
    """Format dbSNP data, if any, for GFF attributes"""
    if data:
        cleaned_data = []
        for item in data:
            if not item in cleaned_data:
                cleaned_data.append(item)
        return ";db_xref " + ",".join(cleaned_data)
    else:
        return ""

def convert_to_file(cgi_input, output_file):
    """Convert a CGI var file and output GFF-formatted data to file"""
    output = output_file  # default assumes writable file object
    if isinstance(output_file, str): 
        output = autozip_file_open(output_file, 'w')
    conversion = convert(cgi_input)  # set up generator
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

