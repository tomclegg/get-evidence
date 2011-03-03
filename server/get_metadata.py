#!/usr/bin/python
# Filename: get_metadata.py

"""
usage: %prog genome_gff_file
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

def genome_metadata(gff_input, genome_stats_file, metadata):
    """Take GFF, track and record associated metadata, yield same GFF lines

    Requires three inputs: gff_input (file or GFF-formatted string generator),
    genome_stats_file (str, path to a text file containing chromosome sizes), 
    and genome_metadata object (dict into which metadata will be stored).

    Updates genome_metadata after GFF-input in fully processed with:
    genome_metadata['chromosomes']: chromosome names (list of str)
    genome_metadata['called_num']: # of positions called (int)
    genome_metadata['match_num']: # of positions called w/chr matching ref (int)
    genome_metadata['called_frac']: fraction of reference called (float)
    genome_metadata['called_frac_placeable']: frac of nogap ref called (float)
    genome_metadata['coding_num']: # of coding positions called (int)
    genome_metadata['coding_frac']: fraction of coding psotiions called (float)

    Returns a generator, yielding same GFF-formatted strings as were inputed.
    """
    # 'chromosomes_raw' is a list of all the raw chromosome sequences seen.
    # 'chromosomes' has the same names edited, if needed, to match ref_genome.
    chromosomes_raw = list()
    chromosomes = list()

    # 'called_num' counts total positions called, while 'match_num' only counts
    # positions which match a chromosome ID in the ref_genome data.
    called_num = 0
    match_num = 0

    # 'ref_all_num' and 'ref_nogap_num' increment total and placeable genome 
    # sizes (respectively) when new chromosomes are seen (for example, the 
    # lengths for chrY are only added if chrY was seen).
    ref_all_num = 0
    ref_nogap_num = 0

    # Set up gff_data.
    if isinstance(gff_input, str) and (re.match(".*\.gz$", gff_input)):
        gff_data = gff.input(gzip.open(gff_input))
    else:
        gff_data = gff.input(gff_input)
    
    # Get chromosome lengths (total and placeable) for reference genome.
    ref_genome = get_genome_stats(metadata['build'], genome_stats_file)
    
    for record in gff_data:
        dist = (record.end - (record.start - 1))
        called_num += dist
        is_in_ref_genome = (record.seqname in ref_genome
                            or "chr" + record.seqname in ref_genome
                            or "chr" + record.seqname[3:] in ref_genome)
        if is_in_ref_genome:
            match_num += dist

        # If we haven't seen this chromosome before, add it to our lists and
        # increase total & placeable reference sequences using ref_genome data.
        if record.seqname not in chromosomes_raw:
            chromosomes_raw.append(record.seqname)
            chr_name = ""
            if record.seqname in ref_genome:
                chr_name = record.seqname
            elif "chr" + record.seqname in ref_genome:
                chr_name = "chr" + record.seqname
            elif "chr" + record.seqname[3:]:
                chr_name = "chr" + record.seqname[3:]
            if chr_name:
                chromosomes.append(chr_name)
                ref_all_num += ref_genome[record.seqname]['seq_all']
                ref_nogap_num += ref_genome[record.seqname]['seq_nogap']
        
        yield str(record)
    
    metadata['chromosomes'] = chromosomes
    metadata['called_num'] = called_num
    metadata['match_num'] = match_num
    metadata['called_frac'] = match_num * 1.0 / ref_all_num
    metadata['called_frac_placeable'] = match_num * 1.0 / ref_nogap_num
    metadata['called_ref_all'] = ref_all_num
    metadata['called_ref_nogap'] = ref_nogap_num

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
    """Return GFF header metadata"""
    out = header_data(sys.argv[1], check_ref=100)
    print "header data:"
    print out

if __name__ == "__main__":
    main()
