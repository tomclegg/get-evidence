#!/usr/bin/python
# Filename: gff_twobit_map.py

"""
usage: %prog gff_file twobit_file [options]
  -f, --flank=NUMBER: include in output a certain number of leading/trailing bases
  -s, --strand: adjust output to match the strand indicated in the GFF record
  -u, --unique: assuming a sorted file, output only unique sequences
"""

# Output FASTA record for intervals in the 2bit file as specified in each GFF record
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import sys
from utils import doc_optparse, gff, twobit
from utils.biopython_utils import reverse_complement
from utils.fasta import FastaRecord

def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)
    
    if len(args) < 2:
        doc_optparse.exit()
    
    flank = int(option.flank or 0)
    
    # try opening the file both ways, in case the arguments got confused
    try:
        gff_file = gff.input(args[1])
        twobit_file = twobit.input(args[0])
    except Exception:
        gff_file = gff.input(args[0])
        twobit_file = twobit.input(args[1])
    
    # initialize a set of variables to keep track of uniqueness, if we need them
    if option.unique:
        previous_record = None
        previous_ref_seq = None
        repetition_count = 1
    
    for record in gff_file:
        # if we're using the unique option, output the previous record only when
        # we're sure we've seen all repetitions of it
        if option.unique and record == previous_record:
            repetition_count += 1
            continue
        elif option.unique:
            if previous_record:
                previous_record.attributes["repetition_count"] = str(repetition_count)
                print FastaRecord(str(previous_record).replace("\t", "|"), previous_ref_seq)
            repetition_count = 1
            previous_record = record

        if record.seqname.startswith("chr"):
            chr = record.seqname
        else:
            chr = "chr" + record.seqname
        
        ref_seq = twobit_file[chr][(record.start - 1):record.end]

        if flank != 0:
            # calculate the flanks (these variables are 0-based)
            left_flank_start = record.start - flank - 1
            left_flank_end = record.start - 1
            if left_flank_start < 0:
                left_flank_start = 0
            
            right_flank_start = record.end
            right_flank_end = record.end + flank
            
            # now find them
            left_flank_seq = twobit_file[chr][left_flank_start:left_flank_end]
            right_flank_seq = twobit_file[chr][right_flank_start:right_flank_end]
            ref_seq = left_flank_seq + "\n\n" + ref_seq + "\n\n" + right_flank_seq
        
        if option.strand and record.strand == "-":
            ref_seq = reverse_complement(ref_seq)
        
        # we don't output the current record if we're using the unique option
        if option.unique:
            previous_ref_seq = ref_seq
        else:
            print FastaRecord(str(record).replace("\t", "|"), ref_seq)
    
    # we'll have one last record yet to output if we used the unique option
    if option.unique:
        previous_record.attributes["repetition_count"] = str(repetition_count)
        print FastaRecord(str(previous_record).replace("\t", "|"), previous_ref_seq)

if __name__ == "__main__":
    main()
