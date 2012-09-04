#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""
usage: %prog genome_gff_file
"""

# Process the sorted genome GFF data and the json report of coding region 
# coverage to get and return meta data.

import gzip
import re
import sys
from utils import gff

DEFAULT_BUILD = "b36"

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
            try:
                if record.feature == "REF":
                    metadata['has_ref'] = True
                    break
                record = gff_data.next()
            except StopIteration:
                break

    return metadata

def get_genome_stats(build, filename):
    """Get chromosome sizes from genome stats file"""
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

def genome_metadata(gff_input, genome_stats_file, progresstracker):
    """Take GFF, track and record associated metadata, yield same GFF lines

    Required arguments:
    gff_input: file or GFF-formatted string generator
    genome_stats_file: str, path to a text file containing chromosome sizes 
    progresstracker: ProgressTracker object

    The following keys will store metadata in progresstracker.metadata:
    chromosomes: list of str, chromosome names
    called_num: int, # of positions called
    match_num: int, # of positions called w/chr matching ref
    ref_all_num: int, # of positions in reference genome (includes unplaceable)
    ref_nogap_num: int, # of placeable positions in reference genome
    called_frac_all: float, fraction of reference called (includes unplaceable)
    called_frac_nogap: float, fraction of placeable reference called

    Returns a generator, yielding same GFF-formatted strings as were inputed.
    """
    # 'chromosomes_raw' is a list of all the raw chromosome sequences seen.
    # 'chromosomes' has the same names edited, if needed, to match ref_genome.
    chromosomes_raw = list()

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
    try:
        ref_genome = get_genome_stats(progresstracker.metadata['build'], 
                                      genome_stats_file)
    except KeyError:
        ref_genome = get_genome_stats(DEFAULT_BUILD, genome_stats_file)

    # Initialize chromosomes list, we'll add them as we see them.
    progresstracker.metadata['chromosomes'] = list()

    # Progress through GFF input.
    header_done = False
    for record in gff_data:
        # Have to do this after calling the first record to
        # get the iterator to read through the header data
        if not header_done:
            yield "##gff-version " + gff_data.data[0]
            yield "##genome-build " + gff_data.data[1]
            yield "# Produced by: get_metadata.py"
            header_done = True

        # Record number of positions called.
        dist = (record.end - (record.start - 1))
        called_num += dist
        is_in_ref_genome = (record.seqname in ref_genome
                            or "chr" + record.seqname in ref_genome
                            or "chr" + record.seqname[3:] in ref_genome)
        if is_in_ref_genome:
            match_num += dist

        # If this is a new chromosome: (1) Add it to our chromosomes list,
        # (2) increase genome size variables (ref_all_num and ref_nogap_num)
        # (3) call progresstracker.saw().
        if record.seqname not in chromosomes_raw:
            chromosomes_raw.append(record.seqname)
            # Standardize chromosome name for metadata storage.
            chr_name = ""
            if record.seqname in ref_genome:
                chr_name = record.seqname
            elif "chr" + record.seqname in ref_genome:
                chr_name = "chr" + record.seqname
            elif "chr" + record.seqname[3:] in ref_genome:
                chr_name = "chr" + record.seqname[3:]
            if chr_name:
                progresstracker.metadata['chromosomes'].append(chr_name)
                ref_all_num += ref_genome[record.seqname]['seq_all']
                ref_nogap_num += ref_genome[record.seqname]['seq_nogap']
                progresstracker.saw(chr_name)
        
        yield str(record)
    
    progresstracker.metadata['called_num'] = called_num
    progresstracker.metadata['match_num'] = match_num
    progresstracker.metadata['ref_all_num'] = ref_all_num
    progresstracker.metadata['ref_nogap_num'] = ref_nogap_num

    if ref_all_num > 0:
        called_frac_all = match_num * 1.0 / ref_all_num
        progresstracker.metadata['called_frac_all'] = called_frac_all

    if ref_nogap_num > 0:
        called_frac_nogap = match_num * 1.0 / ref_nogap_num
        progresstracker.metadata['called_frac_nogap'] = called_frac_nogap

def main():
    """Return GFF header metadata"""
    out = header_data(sys.argv[1], check_ref=100)
    print "header data:"
    print out

if __name__ == "__main__":
    main()
