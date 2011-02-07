#!/usr/bin/python
# Filename: gff_nonsynonymous_filter.py

"""
usage: %prog gff_file transcript_file
"""

# Compare genome data, if it contains regions of called matching-reference, 
# to the file of transcript data and report what coding regions are missing.
# Data is outputed in json format.

import gzip, os, re, sys
import simplejson as json
from utils import doc_optparse, gff, transcript

def report_uncovered(gff_input, transcript_filename):
    # Set up GFF input
    # Iff gff_filename is a string ending with ".gz", assume gzip compressed
    gff_data = None
    if isinstance(gff_input, str) and (re.match(".*\.gz$", gff_input)):
        gff_data = gff.input(gzip.open(gff_input))
    else:
        # GFF will interpret if gff_filename is string containing path 
        # to a GFF-formatted text file, or a string generator 
        # (e.g. file object) with GFF-formatted strings
        gff_data = gff.input(gff_input)
    
    # set up transcript file input
    transcript_input = transcript.Transcript_file(transcript_filename)

    # Store regions being examined, remove or reduce if covered
    # key: Transcript object
    # value: list of tuples (chr (string), start (int), end (int))
    # Note: Start is 1-based, not 0-based as is in transcript files
    examined_regions = {}

    for record in gff_data:

        # Add "chr" to chromosome ID if needed
        if record.seqname.startswith("chr"):
            chromosome = record.seqname
        else:
            if record.seqname.startswith("Chr"):
                chromosome = "chr" + record.seqname[3:]
            else:
                chromosome = "chr" + record.seqname

        # Move forward in transcripts until past record end
        next_region = (chromosome, record.start, record.end)
        removed_transcripts = transcript_input.cover_next_position(next_region)

        for ts in transcript_input.transcripts:

            # Add to examined_regions if new
            if (not ts in examined_regions):
                regions = [] 
                for i in range(len(ts.data["coding_starts"])):
                    regions.append( (ts.data["chr"], (ts.data["coding_starts"][i] + 1), ts.data["coding_ends"][i]) )
                examined_regions[ts] = regions

            # examine regions and remove any covered by the record
            updated_regions = []
            for region in examined_regions[ts]:
                # if overlaps...
                if (chromosome == region[0] and record.start <= region[2] and record.end >= region[1]):
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
                        remaining_before = (region[0], region[1], record.start - 1)
                        remaining_after = (region[0], record.end + 1, region[2])
                        updated_regions.extend([remaining_before, remaining_after])
                else:
                    updated_regions.append(region)
            examined_regions[ts] = updated_regions

        for ts in removed_transcripts:
            gene_data = {}
            gene_data["chr"] = ts.data["chr"]
            gene_data["gene"] = ts.data["name"]
            gene_data["length"] = ts.get_coding_length()
            total_uncovered = 0
            missing_regions = []
            if ts in examined_regions:
                for region in examined_regions[ts]:
                    missing_regions.append(str(region[1]) + "-" + str(region[2]))
                    total_uncovered += region[2] - (region[1] - 1)
            else:  # must have been skipped entirely: it's all missing
                regions = []
                for i in range(len(ts.data["coding_starts"])):
                    missing_regions.append(str(ts.data["coding_starts"][i] + 1) + "-" + str(ts.data["coding_ends"][i]))
                    total_uncovered += ts.data["coding_ends"][i] - ts.data["coding_starts"][i]
            gene_data["missing"] = total_uncovered
            gene_data["missing_regions"] = ", ".join(missing_regions)
            if gene_data["length"] > 0:
                yield str(json.dumps(gene_data))

    # Move through any remaining transcripts
    record_beyond_end_hack = ("chrZ", 999999999)
    removed_transcripts = transcript_input.cover_next_position(record_beyond_end_hack)

    # clean up remaining transcripts (if any)
    for ts in removed_transcripts + transcript_input.transcripts:
        gene_data = {}
        gene_data["chr"] = ts.data["chr"]
        gene_data["gene"] = ts.data["name"]
        gene_data["length"] = ts.get_coding_length()
        total_uncovered = 0
        missing_regions = []
        if ts in examined_regions and examined_regions[ts]:
            for region in examined_regions[ts]:
                missing_regions.append(region[0] + ":" + str(region[1]) + "-" + str(region[2]))
                total_uncovered += region[2] - (region[1] - 1)
        else: # must have been skipped entirely: it's all missing   
            for i in range(len(ts.data["coding_starts"])):
                missing_regions.append(str(ts.data["coding_starts"][i] + 1) + "-" + str(ts.data["coding_ends"][i]))
                total_uncovered += ts.data["coding_ends"][i] - ts.data["coding_starts"][i]
        gene_data["missing"] = total_uncovered
        gene_data["missing_regions"] = ", ".join(missing_regions)
        if gene_data["length"] > 0:
            yield str(json.dumps(gene_data))

def report_uncovered_to_file(gff_input, transcript_filename, output_file):
    f_out = None
    if isinstance(output_file, str):
        f_out = open(output_file, 'w')
    else:
        # assume writeable file object
        f_out = output_file
    has_data = False
    out = report_uncovered(gff_input, transcript_filename)
    for line in out:
        f_out.write(line + "\n")
        has_data = True
    f_out.close()
    if not has_data and isinstance(output_file, str):
        os.remove(output_file)  # remove empty file

def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)

    if len(args) < 2:
        doc_optparse.exit()  # Error
    elif len(args) < 3:
        out = report_uncovered(args[0], args[1])
        for line in out:
            print line
    else:
        report_uncovered_to_file(args[0], args[1], args[2])

if __name__ == "__main__":
    main()
