#!/usr/bin/python
# Filename: get_metadata.py

"""
usage: %prog genome_gff_file json_coverage_report
"""

# Process the sorted genome GFF data and the json report of coding region 
# coverage to get and return meta data.

import json, gzip, os, re, sys
from utils import doc_optparse, gff, transcript

def header_data(gff_in, metadata=dict(), check_ref=0):
    """Read GFF header data from file, store or return metadata
    
    Optionally also checks the first N lines for records where the type is 
    "REF" (third column). (Our genome processing treats these as regions where 
    the genotype is "called" as matching the reference genome.)
    """
    # Set up GFF data
    if isinstance(gff_in, str) and re.search(r'\.gz$', gff_in):
        gff_data = gff.input(gzip.open(gff_in))
    else:
        gff_data = gff.input(gff_in)

    # Pull record to force GFFFile to read through header, then store metadata.
    record = gff_data.next()
    metadata['gff-format'], metadata['build'] = gff_data.data[0:2]
    
    # Check for REF lines if we asked to do this. False unless we see some.
    if check_ref > 0:
        metadata['has_ref'] = False
        for i in range(check_ref):
            if record.feature == "REF":
                metadata['has_ref'] = True
                break
            record = gff_data.next()

    return metadata

def get_genome_stats(build, filename):
    ref_genome = dict()
    stats = open(filename)
    for line in stats:
        data = line.split()
        if data[0] == build:
            chrom_data = dict()
            chrom_data['seq_all'] = int(data[2])
            chrom_data['seq_nogap'] = int(data[3])
            ref_genome[data[1]] = chrom_data
    stats.close()
    return ref_genome

def genome_metadata(gff_input, genome_stats_file):
    # Return 'genome', a dict storing the following data:
    # genome['has_ref']: whether a genome has REF lines (boolean)
    # genome['chromosomes']: chromosome names (list of str)
    # genome['called_num']: # of reference positions called (int)
    # genome['called_frac']: fraction of reference called (float)
    # genome['called_frac_placeable'] = fraction of nogap reference called (float)
    # genome['coding_num']: # of coding positions called (int)
    # genome['coding_frac']: fraction of coding psotiions called (float)
    
    # Set up variables
    genome = dict()
    chromosomes = list()
    chromosomes_raw = list()
    has_ref = False
    called_num = 0          # Total positions called
    match_num = 0           # Only if we can match the chromosome ID
    ref_all_num = 0         # Grows based on whether a chromosome is...
    ref_nogap_num = 0       #       ...present in gff_input (e.g. chrY or chrM)

    # Set up gff_data
    gff_data = None
    if isinstance(gff_input, str) and (re.match(".*\.gz$", gff_input)):
        gff_data = gff.input(gzip.open(gff_input))
    else:
        gff_data = gff.input(gff_input)
    
    ref_genome = None
    build = None
    header_read = False
    for record in gff_data:
        # Header won't be read until iterator is called
        if not header_read:
            build = gff_data.data[1]
            ref_genome = get_genome_stats(build, genome_stats_file)
            header_read = True
        dist = (record.end - (record.start - 1))
        called_num += dist
        if record.seqname in ref_genome \
                or "chr" + record.seqname in ref_genome \
                or "chr" + record.seqname[3:] in ref_genome:
            match_num += dist
        if not has_ref and record.feature == "REF":
            has_ref = True
        if record.seqname not in chromosomes_raw:
            chromosomes_raw.append(record.seqname)
            if record.seqname in ref_genome:
                chromosomes.append(record.seqname)
                ref_all_num = ref_all_num + ref_genome[record.seqname]['seq_all']
                ref_nogap_num = ref_nogap_num + ref_genome[record.seqname]['seq_nogap']
            elif "chr" + record.seqname in ref_genome:
                chromosomes.append("chr" + record.seqname)
                ref_all_num += ref_genome["chr" + record.seqname]['seq_all']
                ref_nogap_num += ref_genome["chr" + record.seqname]['seq_nogap']
            elif "chr" + record.seqname[3:] in ref_genome:
                chromosomes.append("chr" + record.seqname[3:])
                ref_all_num += ref_genome["chr" + record.seqname[3:]]['seq_all']
                ref_nogap_num += ref_genome["chr" + record.seqname[3:]]['seq_nogap']
            else:
                print "Possible error, chromosome name unmatchable: \'" + record.seqname + "\'"
                break
    
    genome['build'] = build
    genome['has_ref'] = has_ref
    genome['chromosomes'] = chromosomes
    genome['called_num'] = called_num
    genome['match_num'] = match_num
    genome['called_frac'] = match_num * 1.0 / ref_all_num
    genome['called_frac_placeable'] = match_num * 1.0 / ref_nogap_num
    genome['called_ref_all'] = ref_all_num
    genome['called_ref_nogap'] = ref_nogap_num
    return genome

def coding_metadata(json_coverage, chromosomes):
    genome = dict()
    # Set up coverage_data and variables
    coverage_file = open(json_coverage)
    ref_coding = 0      # Add only if in gene is in chromosome file (ignore random/other)
    ref_coding_clintest = 0
    called_coding = 0
    called_coding_clintest = 0
    for line in coverage_file:
        coverage_data = json.loads(line)
        if "chr" in coverage_data:
            if coverage_data["chr"] in chromosomes:
                called_length = coverage_data["length"] - coverage_data["missing"]
                if called_length >= 0 and called_length <= coverage_data["length"]: # sanity check
                    ref_coding += coverage_data["length"]
                    called_coding += called_length
                    if "clin_test" in coverage_data and coverage_data["clin_test"]:
                        ref_coding_clintest += coverage_data["length"]
                        called_coding_clintest += called_length
        else:
            print "Error, no 'chr' in: " + line
    if ref_coding > 0:
        genome['coding_num'] = called_coding
        genome['coding_frac'] = called_coding * 1.0 / ref_coding
        if ref_coding_clintest > 0:
            genome['coding_clintest_num'] = called_coding_clintest
            genome['coding_clintest_frac'] = called_coding_clintest * 1.0 / ref_coding_clintest
        else:
            genome['coding_clintest_num'] = 0
            genome['coding_clintest_frac'] = 0
    else:
        genome['coding_num'] = 0
        genome['coding_frac'] = 0
    genome['coding_ref'] = ref_coding
    genome['coding_clintest_ref'] = ref_coding_clintest
    return genome

def main():
    out = header_data(sys.argv[1], check_ref=100)
    print "header data:"
    print out

if __name__ == "__main__":
    main()
