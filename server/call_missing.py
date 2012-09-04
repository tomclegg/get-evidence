#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""
Find coding regions missing in a genome report.

Compare GFF-formatted genome data against a transcript file and a list of 
Genetests genes to produce a report of what regions were not covered by
sequencing (as opposed to matching the reference genome).
"""

import simplejson as json
import gzip
import os 
import re
from optparse import OptionParser
from utils import gff, transcript


def std_chr_name(chrom_str):
    """Standardize chromosome name so it starts with 'chr'"""
    if chrom_str.startswith("chr"):
        return chrom_str
    else:
        if chrom_str.startswith("Chr"):
            return "chr" + chrom_str[3:]
        else:
            return "chr" + chrom_str

def remove_covered(regions, record):
    """Update a list of regions to remove positions covered by a GFF record

    Each region is assumed to be a tuple of (chromosome, start, end) where 
    chromosome is a string, and start and end are both numbers.
    """
    chromosome = std_chr_name(record.seqname)
    updated_regions = []
    for region in regions:
        is_overlapping = (chromosome == region[0] and
                          record.start <= region[2] and
                          record.end >= region[1])
        if is_overlapping:
            if record.start <= region[1]:
                if record.end >= region[2]:
                    pass    # full overlap!                               
                else:
                    remaining_region = (region[0], record.end + 1, region[2])
                    updated_regions.append(remaining_region)
            elif record.end >= region[2]:
                remaining_region = (region[0], region[1], record.start - 1)
                updated_regions.append(remaining_region)
            else:
                remain_before = (region[0], region[1], record.start - 1)
                remain_after = (region[0], record.end + 1, region[2])
                updated_regions.extend([remain_before, remain_after])
        else:
            updated_regions.append(region)
    return updated_regions

def process_ts_missing(removed_transcripts, examined_regions, genetests_names,
                       progresstracker):
    """Process past transcripts to yield uncovered regions"""
    for old_ts in removed_transcripts:
        gene_data = {}
        gene_data["chr"] = old_ts.data["chr"]
        gene_data["gene"] = old_ts.data["name"]
        gene_data["length"] = old_ts.get_coding_length()
        if gene_data["gene"] in genetests_names:
            gene_data["clin_test"] = True
        total_uncovered = 0
        missing_regions = []
        if old_ts in examined_regions:
            for region in examined_regions[old_ts]:
                missing_regions.append( str(region[1]) + "-" +
                                        str(region[2]) )
                total_uncovered += region[2] - (region[1] - 1)
            del(examined_regions[old_ts])
        # Must have been skipped entirely -- if so, it's all missing.         
        else:
            for i in range(len(old_ts.data["coding_starts"])):
                region_str = ( str(old_ts.data["coding_starts"][i] + 1) +
                               "-" + str(old_ts.data["coding_ends"][i]) )
                missing_regions.append(region_str)
                total_uncovered += ( old_ts.data["coding_ends"][i] -
                                     old_ts.data["coding_starts"][i] )
        gene_data["missing"] = total_uncovered
        gene_data["missing_regions"] = ", ".join(missing_regions)
        # Check if we should update metadata.
        update_metadata = (gene_data["length"] > 0 and progresstracker and
                           gene_data["missing"] <= gene_data["length"] and
                           "chromosomes" in progresstracker.metadata and
                           gene_data["chr"] in 
                           progresstracker.metadata["chromosomes"])
        if (update_metadata):
            ref = gene_data["length"]
            called = gene_data["length"] - gene_data["missing"]
            progresstracker.metadata['ref_coding_n'] += ref
            progresstracker.metadata['called_coding_n'] += called
            if "clin_test" in gene_data and gene_data["clin_test"]:
                progresstracker.metadata['ref_coding_clintest_n'] += ref
                progresstracker.metadata['called_coding_clintest_n'] += called
        yield gene_data


def report_uncovered(gff_input, transcript_filename, genetests_filename, 
                     output_file=None, progresstracker=None):
    """Compare GFF records to transcripts to find missing coding regions

    Reports missing regions, yielding JSON-formatted strings. If output_file 
    is provided, instead yields the GFF-formatted strings from gff_input and 
    writes the JSON-formatted report strings to file.

    Required arguments:
    gff_input: GFF-formatted strings, string generator or file (can be .gz)
    transcript_filename: transcripts file
    genetests_filename: genetests file

    Optional arguments:
    output_file: If provided, opens and writes to this location (see above)
    progresstracker: If provided, records metadata to progresstracker.metadata
    """
    # Set up GFF input. If it ends with '.gz', assume gzip compressed.
    if isinstance(gff_input, str) and (re.match(".*\.gz$", gff_input)):
        gff_data = gff.input(gzip.open(gff_input))
    else:
        gff_data = gff.input(gff_input)
    
    # set up transcript file input
    transcript_input = transcript.Transcript_file(transcript_filename)

    # grab genetests gene names
    genetests_input = open(genetests_filename)
    genetests_names = set()
    for line in genetests_input:
        if (re.match("#", line)):
            continue
        data = line.split("\t")
        if data[4] == "na":
            continue
        if not (re.match(".*Clinical", data[5])):
            # currently we require "clinical testing available"
            continue
        names = data[4].split("|")
        for name in names:
            genetests_names.add(name)

    # Set up optional output.
    f_out = False
    if output_file:
        if re.match(r'\.gz$', output_file):
            f_out = gzip.open(output_file, 'w')
        else:
            f_out = open(output_file, 'w')

    # If progresstracker was sent, track these for metadata.
    if progresstracker:
        progresstracker.metadata['ref_coding_n'] = 0
        progresstracker.metadata['ref_coding_clintest_n'] = 0
        progresstracker.metadata['called_coding_n'] = 0
        progresstracker.metadata['called_coding_clintest_n'] = 0

    # Store to-be-examined regions, we'll remove covered regions from this list.
    # key: Transcript object
    # value: list of tuples (chr (string), start (int), end (int))
    # Note: Start is 1-based, not 0-based as is in transcript files
    examined_regions = {}

    header_done = False
    for record in gff_data:
        if not header_done:
            yield "##gff-version " + gff_data.data[0]
            yield "##genome-build " + gff_data.data[1]
            yield "# Produced by: call_missing.py"
            header_done = True

        if f_out:
            yield str(record)

        # Move forward in transcripts until past record end.
        chromosome = std_chr_name(record.seqname)
        next_region = (chromosome, record.start, record.end)
        removed_transcripts = transcript_input.cover_next_position(next_region)

        for curr_ts in transcript_input.transcripts:
            # Add to examined_regions if new.
            if (not curr_ts in examined_regions):
                regions = [] 
                for i in range(len(curr_ts.data["coding_starts"])):
                    region = (curr_ts.data["chr"],
                              (curr_ts.data["coding_starts"][i] + 1),
                              curr_ts.data["coding_ends"][i])
                    regions.append(region)
                examined_regions[curr_ts] = regions
            # Examine regions and remove any covered by the record.
            curr_ts_regions = examined_regions[curr_ts]
            examined_regions[curr_ts] = remove_covered(curr_ts_regions, record)

        # Process past transcripts.
        results = process_ts_missing(removed_transcripts, examined_regions,
                                     genetests_names, progresstracker)
        for gene_data in results:
            if gene_data["length"] > 0:
                if f_out:
                    f_out.write(json.dumps(gene_data) + '\n')
                else:
                    yield json.dumps(gene_data)

    # Move through any remaining transcripts and return missing.
    beyond_end_hack = ("chrZ", 9999999999)
    removed_transcripts = transcript_input.cover_next_position(beyond_end_hack)
    remaining_transcripts = removed_transcripts + transcript_input.transcripts
    results = process_ts_missing(remaining_transcripts, examined_regions,
                                 genetests_names, progresstracker)
    for gene_data in results:
        if gene_data["length"] > 0:
            if f_out:
                f_out.write(json.dumps(gene_data) + '\n')
            else:
                yield json.dumps(gene_data)

def report_uncovered_to_file(gff_input, transcript_filename, genetests_filename,
                             output_file, progresstracker=False):
    """Call report_uncovered and report results to output_file"""
    if isinstance(output_file, str):
        f_out = open(output_file, 'w')
    else:
        f_out = output_file
    has_data = False
    out = report_uncovered(gff_input, transcript_filename, genetests_filename, 
                           progresstracker=progresstracker)
    for line in out:
        f_out.write(line + "\n")
        has_data = True
    f_out.close()
    if not has_data and isinstance(output_file, str):
        os.remove(output_file)

def main():
    """Report uncovered coding regions in GFF-formated genome data"""
    # Parse options
    usage = "\n%prog -i genome_gff_file -t transcript_file " + \
            "-g genetests_file [-o output_file]"
    parser = OptionParser(usage=usage)
    parser.add_option("-i", "--input", dest="genome_gff",
                      help="read GFF data from GFF_FILE", metavar="GFF_FILE")
    parser.add_option("-t", "--transcripts", dest="transcript",
                      help="read transcripts data from TRANSCRIPT_FILE", 
                      metavar="TRANSCRIPT_FILE")
    parser.add_option("-g", "--genetests", dest="genetests",
                      help="read Genetests data from GENETEST_FILE",
                      metavar="GENETEST_FILE")
    parser.add_option("-o", "--output", dest="output",
                      help="write output to OUTPUT_FILE", 
                      metavar="OUTPUT_FILE")
    options, args = parser.parse_args()

    if options.genome_gff and options.transcript and options.genetests:
        if options.output:
            report_uncovered_to_file(options.genome_gff, options.transcript,
                                     options.genetests, options.output)
        else:
            out = report_uncovered(options.genome_gff, options.transcript,
                                   options.genetests)
            for line in out:
                print line
    else:
        parser.print_help()


if __name__ == "__main__":
    main()
