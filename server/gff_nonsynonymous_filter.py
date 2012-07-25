#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""
usage: %prog gff_file twobit_file transcript_file [output_file]
"""

# Predict amino acid changes (if any) from variants, add to GFF output as
# an amino_acid attribute.
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import datetime, gzip, re, sys
from utils import doc_optparse, gff, twobit
from utils.biopython_utils import reverse_complement, translate
from codon import codon_123

def predict_nonsynonymous(gff_input, twobit_path, transcript_path, progresstracker=False):
    twobit_file = twobit.input(twobit_path)
    transcript_input = transcript_file(transcript_path)

    # Set up gff_data
    gff_data = None
    if isinstance(gff_input, str) and (re.match(".*\.gz$", gff_input)):
        gff_data = gff.input(gzip.open(gff_input))
    else:
        # GFF will interpret if gff_filename is string containing path 
        # to a GFF-formatted text file, or a string generator 
        # (e.g. file object) with GFF-formatted strings
        gff_data = gff.input(gff_input)

    header_done = False

    for record in gff_data:
        # Have to do this after calling the first record to
        # get the iterator to read through the header data
        if not header_done:
            yield "##genome-build " + gff_data.data[1]
            yield "# Produced by: gff_nonsynonymous_filter.py"
            yield "# Date: " + datetime.datetime.now().isoformat(' ')
            header_done = True
        
        if record.feature == "REF":
            yield str(record)
            continue

        if record.seqname.startswith("chr"):
            chromosome = record.seqname
        else:
            if record.seqname.startswith("Chr"):
                chromosome = "chr" + record.seqname[3:]
            else:
                chromosome = "chr" + record.seqname
        if progresstracker: progresstracker.saw(chromosome)
        
        # record.start is 1-based, but UCSC annotation starts are 0-based, so subtract 1
        record_position = (chromosome, record.start - 1)

        transcripts = transcript_input.cover_next_position(record_position)

        # Skip the rest if no transcripts are returned
        if (not transcripts):
            yield str(record)
            continue

        # otherwise, cycle through
        nonsyn_inferences = []
        splice_inferences = []
        ucsc_transcripts = []
        is_nonsynonymous = is_splice = False
        
        for data in transcripts:
            # need to make "d" match up with transcript file order
            # d : geneName, strand, cdsStart, cdsEnd, exonStarts, exonEnds
            #     0, 3, 6, 7, 9, 10
            d = ( data[0], data[3], int(data[6]), int(data[7]), data[9], data[10] )
            i = infer_function(twobit_file, record, *d)
            if i[0] == "nonsynonymous coding":
                nonsyn_inferences.append("%s %s" % (d[0], i[2]))
                is_nonsynonymous = True
                ucsc_transcripts.append(data[1]) 
            elif i[0] == "splice site":
                splice_inferences.append("%s %s " % (d[0], i[2]))
                is_splice = True
        
        # set the attribute if we can
        if (not is_nonsynonymous) and (not is_splice):
            yield str(record)
        else:
            if len(nonsyn_inferences) > 0:
                unique_inferences = unique(nonsyn_inferences)
                unique_inferences.sort(key=str.lower)
                record.attributes["amino_acid"] = "/".join(unique_inferences)
                record.attributes["ucsc_trans"] = ",".join(ucsc_transcripts)
            if len(splice_inferences) > 0:
                # Not going to report splice sites for now, but leaving the
                # code here because we hope to later. - Madeleine 2010/11/29
                pass
                # unique_inferences = unique(splice_inferences)
                # unique_inferences.sort(key=str.lower)
                # record.attributes["splice"] = "/".join(unique_inferences)
            yield str(record)
        


def infer_function(twobit_file, record, geneName, strand, cdsStart, cdsEnd, exonStarts, exonEnds):
    """
    Infer "function" (as dbSNP calls it) given a reference TwoBitFile object,
    a GFFRecord object, and info about the gene: name, strand, coding sequence
    start, coding sequence end (both 0-based, half-open), exon starts (comma-
    separated string), and exon ends (comma-separated string).
    
    Returns a tuple consisting of the "function" (coding, 5'-UTR, etc.),
    followed by the exon or intron number (1-based, if applicable), and amino
    acid residue (1-based numeric type, if applicable) or change (1-based
    string, if applicable).
    """
    # Check chromosome name
    if record.seqname.startswith("chr"):
        chr = record.seqname
    else:
        chr = "chr" + record.seqname


    # Check if it falls entirely outside the gene region
    if (record.strand == "+" and record.end <= cdsStart) or \
      (record.strand == "-" and record.start > cdsEnd):
        return ("5'-UTR",)
   
    if (record.strand == "+" and record.start > cdsEnd) or \
      (record.strand == "-" and record.end <= cdsStart):
        return ("3'-UTR",)
    
    # make exonStarts and exonEnds into lists
    # first, we have to make sure they're strings...
    try:
        exonStarts = exonStarts.tostring()
        exonEnds = exonEnds.tostring()
    # if we already have a string, tostring() won't work
    except AttributeError:
        pass
    
    # now, we really make them lists
    exonStarts = [long(e) for e in exonStarts.strip(",").split(",")]
    exonEnds = [long(e) for e in exonEnds.strip(",").split(",")]
    
    # make a list of all exons, in case we need it
    all_exons = zip(exonStarts, exonEnds)

    # reverse for strand; note how we set aside all_exons first before doing this
    if strand == "-":
        exonStarts.reverse()
        exonEnds.reverse()

    # Get coordinates of coding sequence
    exonWCodeStarts = []
    exonWCodeEnds = []
    exonCodingRanges = []
    for j in range(len(exonStarts)):
        if (exonEnds[j] <= cdsStart or exonStarts[j] > cdsEnd):
            continue
        else:
            start = exonStarts[j]
            end = exonEnds[j]
            if start < cdsStart:
                start = long(cdsStart)
            if end > cdsEnd:
                end = long(cdsEnd)
            exonWCodeStarts.append(exonStarts[j])
            exonWCodeEnds.append(exonEnds[j])
            exonCodingRanges.append( (start, end) )

    # parse out exons
    exons = []
    exon_seqs = []
    running_intron_count = running_exon_count = running_cds_bases_count = 0 # 1-based
    trimmed_bases = 0

    for j in range(0, len(exonWCodeStarts)):
        # skip exons we know are noncoding (we reported UTR already earlier)
        if exonWCodeEnds[j] > cdsStart and exonWCodeStarts[j] <= cdsEnd:
            
            # Commenting out splice prediction for now - MPB 2010/12/03
            '''
            # check if it is spanning or within 2bp of the splice junction
            overlap_start = (record.start <= exonWCodeStarts[j] and record.end > exonWCodeStarts[j] - 2) \
                            and exonWCodeStarts[j] >= cdsStart
            overlap_end = (record.start <= exonWCodeEnds[j] + 2 and record.end > exonWCodeEnds[j]) \
                            and exonWCodeEnds[j] <= cdsEnd
            before_seq = after_seq = ""
            if overlap_start:
                if strand == "-":
                    before_seq = reverse_complement("".join([twobit_file[chr][e[0]:e[1]] for e in reversed(exonCodingRanges[0:j+1])]))
                    after_seq = reverse_complement(twobit_file[chr][exonCodingRanges[j+1][0]:exonCodingRanges[j+1][1]])
                else:
                    before_seq = "".join([twobit_file[chr][e[0]:e[1]] for e in exonCodingRanges[0:j]])
                    after_seq = twobit_file[chr][exonCodingRanges[j][0]:exonCodingRanges[j][1]]
            elif overlap_end:
                if strand == "-":
                    before_seq = reverse_complement("".join([twobit_file[chr][e[0]:e[1]] for e in reversed(exonCodingRanges[0:j])]))
                    after_seq = reverse_complement(twobit_file[chr][exonCodingRanges[j][0]:exonCodingRanges[j][1]])
                else:
                    before_seq = "".join([twobit_file[chr][e[0]:e[1]] for e in exonCodingRanges[0:j+1]])
                    after_seq = twobit_file[chr][exonCodingRanges[j+1][0]:exonCodingRanges[j+1][1]]
            if (overlap_start or overlap_end):
                desc = ""
                if len(before_seq) % 3 == 0:
                    var1 = codon_1to3(translate(before_seq[-3:]))
                    var2 = codon_1to3(translate(after_seq[:3]))
                    pos = len(before_seq) / 3
                    desc = var1 + "-" + var2 + str(pos) + "-" + str(pos+1) + "Splice"
                else:
                    aa = translate(before_seq + after_seq)
                    var = codon_1to3(aa[(len(before_seq)/3)])
                    pos = 1 + len(before_seq) / 3
                    desc = var + str(pos) + "Splice"
                    # print "Predicting splice start -, seq_ref: " + seq_ref + " next_exon: " + next_exon + " var: " + var + " desc: " + desc
                return ("splice site",1,desc)
            '''

            # trim the start and end to the coding region
            if exonWCodeStarts[j] < cdsStart:
                trimmed_bases = cdsStart - exonWCodeStarts[j]
                exonWCodeStarts[j] = cdsStart
            if exonWCodeEnds[j] > cdsEnd:
                trimmed_bases = exonWCodeEnds[j] - cdsEnd
                exonWCodeEnds[j] = cdsEnd

            # check if it's in intron
            if len(exons) > 0:
                if strand == "+":
                    intron_start = exons[-1][1]
                    intron_end = exonWCodeStarts[j]
                else:
                    intron_start = exonWCodeEnds[j]
                    intron_end = exons[-1][0]

                running_intron_count += 1

                # test if is in within intron
                if (record.start > intron_start and record.end <= intron_end):
                    return ("intron", running_intron_count)

            # skip variants spanning start or end of coding region
            # (we haven't worked out how to report these yet)
            if (record.start <= cdsStart and record.end > cdsStart) or \
                (record.start <= cdsEnd and record.end > cdsEnd) or \
                (record.start - 1 == record.end == cdsStart) or \
                (record.start == record.end + 1 == cdsEnd):
                return ("span_coding_edge", )

            # skip variants spanning start or end of exon boundaries
            # (we haven't worked out how to report these yet)
            if (record.start <= exonWCodeStarts[j] and record.end > exonWCodeStarts[j]) or \
                (record.start <= exonWCodeEnds[j] and record.end > exonWCodeEnds[j]):
                return ("span_exon_boundary", )

            if ( (record.start > exonWCodeStarts[j] and record.start <= exonWCodeEnds[j]) \
                and (record.end > exonWCodeStarts[j] and record.end <= exonWCodeEnds[j])):
                
                # get alleles and length is reference genome
                alleles = record.attributes["alleles"].strip("\"").split("/")
                for i in range(len(alleles)):
                    if alleles[i] == "-":
                        alleles[i] = ""
                ref_allele = record.attributes["ref_allele"]
                if ref_allele == "-":
                    ref_allele = ""
                if (len(ref_allele) != record.end + 1 - record.start):
                    sys.exit("Reference allele length doesn't match GFF positions! ref_allele: \""  \
                        + record.attributes["ref_allele"] + "\", start: " + str(record.start) + " end: " \
                        + str(record.end))
                try:
                    alleles.remove(ref_allele)
                except ValueError:
                    pass

                # Generate reference and variant coding region DNA sequences
                seq_var = [ ]
                seq_ref = seq_ref_pre = seq_ref_post = ""
                if strand == "-":
                    seq_ref = "".join([twobit_file[chr][e[0]:e[1]] for e in reversed(exonCodingRanges)])
                    seq_ref = reverse_complement(seq_ref)
                    seq_ref_pre = "".join([twobit_file[chr][e[0]:e[1]] for e in reversed(exonCodingRanges[:j])])
                    seq_ref_post = "".join([twobit_file[chr][e[0]:e[1]] for e in reversed(exonCodingRanges[j+1:])])
                else:
                    seq_ref = "".join([twobit_file[chr][e[0]:e[1]] for e in exonCodingRanges])
                    seq_ref_pre = "".join([twobit_file[chr][e[0]:e[1]] for e in exonCodingRanges[:j]])
                    seq_ref_post = "".join([twobit_file[chr][e[0]:e[1]] for e in exonCodingRanges[j+1:]])
                for allele in alleles:
                    seq = ""
                    if strand == "-":
                        seq = seq_ref_post + twobit_file[chr][exonCodingRanges[j][0]:(record.start - 1)] \
                            + allele + twobit_file[chr][record.end:exonCodingRanges[j][1]] + seq_ref_pre
                        seq = reverse_complement(seq)
                    else: 
                        seq = seq_ref_pre + twobit_file[chr][exonCodingRanges[j][0]:(record.start - 1)] \
                            + allele + twobit_file[chr][record.end:exonCodingRanges[j][1]] + seq_ref_post
                    seq_var.append(seq)

                # Get variants
                amino_acid_changes = []
                for i in range(len(alleles)):
                    variant_descriptions = []
                    try: variant_descriptions = desc_variants(seq_ref, seq_var[i])
                    except AssertionError:
                        continue
                    if (variant_descriptions):
                        amino_acid_changes.append(variant_descriptions)
                if amino_acid_changes:
                    return("nonsynonymous coding", 1, " ".join(amino_acid_changes))
                else:
                    return("synonymous coding",)
            
            exons.append([exonWCodeStarts[j], exonWCodeEnds[j]])

# based on <http://www.peterbe.com/plog/uniqifiers-benchmark>
def unique(seq): # not order preserving, but that's OK; we can sort it later
    return {}.fromkeys(seq).keys()

def desc_variants (coding_seq1, coding_seq2):
    var_description = ""
    assert len(coding_seq1) % 3 == 0, "Reference coding sequence malformed: " \
            + "should have a length that is a multiple of 3! " \
            + "DNA sequence is: " + coding_seq1
    aa1 = list(translate(coding_seq1))
    for i in range(len(aa1)-1):
        assert aa1[i] != "*", "Reference coding sequence malformed: only " \
            + "last codon should be stop codon! AA sequence is: " + "".join(aa1) \
            + " DNA sequence is: " + coding_seq1
    assert aa1[-1] == "*", "Reference coding sequence malformed: last " \
            + "codon should be a stop codon! AA sequence is: " + "".join(aa1) \
            + " DNA sequence is: " + coding_seq1
    if (len(coding_seq2) % 3 != 0):
        # Frameshift. Find first amino acid that is changed.
        coding_seq2_trimmed = coding_seq2[0:3 * (len(coding_seq2)/3)]
        aa2 = list(translate(coding_seq2))
        pos = 1
        while (pos <= len(aa1) and pos <= len(aa2) and aa1[pos-1] == aa2[pos-1]):
            pos += 1
        if (pos <= len(aa1)):
            var_description = aa1[pos-1] + str(pos) + "Shift"
    else:
        aa2 = list(translate(coding_seq2))
        position = 1
        last_ref_aa = ""
        while (len(aa1) > 0 and len(aa2) > 0 and aa1[0] == aa2[0]):
            last_ref_aa = aa1.pop(0)
            aa2.pop(0)
            position += 1
        while (len(aa1) > 0 and len(aa2) > 0 and aa1[-1] == aa2[-1]):
            aa1.pop(-1)
            aa2.pop(-1)
        if len(aa1) == 0 and len(aa2) == 0:  # no change
            pass
        elif len(aa1) == 0: # pure insertion
            if position > 1:   # ignore if before 1st AA, shouldn't get translated
                # search for stop in aa2 -- don't want to report beyond this
                if any(["*" in aa for aa in aa2]):
                    report_aa2 = []
                    for i in range(len(aa2)):
                        report_aa2.append(aa2[i])
                        if aa2[i] == "*":
                            break
                    var_description = last_ref_aa + str(position-1) + "".join([last_ref_aa] + report_aa2)
                # report aa just before insert, pos of that aa, repeat that aa and add the insert
                else:
                    var_description = last_ref_aa + str(position-1) + "".join([last_ref_aa] + aa2)
        elif len(aa2) == 0: # pure deletion, report all of aa1, first pos of aa1, "Del"
            var_description = "".join(aa1) + str(position) + "Del"
        else:
            # search for stop -- don't want to report beyond this
            if any(["*" in aa for aa in aa2]):
                report_aa1 = []
                report_aa2 = []
                for i in range(len(aa2)):
                    report_aa2.append(aa2[i])
                    if len(aa1) >= i + 1:
                            report_aa1.append(aa1[i])
                    if aa2[i] == "*":
                        break
                var_description = "".join(report_aa1) + str(position) + "".join(report_aa2)
            # No stop -- report all of aa1, first pos of aa1, all of aa2
            else:
                var_description = "".join(aa1) + str(position) + "".join(aa2)
    return var_description

def codon_1to3 (aa):
    threeletter = codon_123(aa)
    if (threeletter == "TERM"):
        threeletter = "Stop"
    return threeletter

class transcript_file:
    def __init__(self, filename):
        self.f = open(filename)
        self.data = self.f.readline().split()
        self.transcripts = [ self.data ]

    def cover_next_position(self, position):
        if (self.data):
            last_start_position = (self.transcripts[-1][2], int(self.transcripts[-1][4]))
        while (self.data and self.comp_position(last_start_position, position) < 0):
            self.data = self.f.readline().split()
            if (self.data):
                self.transcripts.append(self.data)
                last_start_position = (self.transcripts[-1][2], int(self.transcripts[-1][4]))
        return self._remove_uncovered_transcripts(position)

    def _remove_uncovered_transcripts(self, position):
        covered_transcripts = []
        data_to_remove = []
        if self.transcripts:
            for data in self.transcripts:
                start_position = (data[2], int(data[4]))
                end_position = (data[2], int(data[5]))
                if (self.comp_position(position, end_position) <= 0):
                    if (self.comp_position(position, start_position) >= 0):
                        covered_transcripts.append(list(data))
                else:
                    # remove any with end before target
                    data_to_remove.append(data)
            for data in data_to_remove:
                self.transcripts.remove(data)
        return covered_transcripts

    def comp_position(self, position1, position2):
        # positions are tuples of chromosome (str) and position (int)
        if (position1[0] != position2[0]):
            return cmp(position1[0],position2[0])
        else:
            return cmp(position1[1],position2[1])

def predict_nonsynonymous_to_file(gff_file_input, twobit_path, transcript_path, output_file, progresstracker=False):
    # Set up output file
    f_out = None
    if isinstance(output_file, str):
        # Treat as path
        if (re.match(".*\.gz", output_file)):
            f_out = gzip.open("f_out", 'w')
        else:
            f_out = open(output_file, 'w')
    else:
        # Treat as writeable file object
        f_out = output_file

    out = predict_nonsynonymous(gff_file_input, twobit_path, transcript_path, progresstracker=progresstracker)
    for line in out:
        f_out.write(line + "\n")
    f_out.close()

def main():
    # return if we don't have the correct arguments
    # parse options
    option, args = doc_optparse.parse(__doc__)

    if len(args) < 3:
        doc_optparse.exit()  # Error
    elif len(args) < 4:
        out = predict_nonsynonymous(args[0], args[1], args[2])
        for line in out:
            print line
    else:
        predict_nonsynonymous_to_file(args[0], args[1], args[2], args[3])

if __name__ == "__main__":
    main()
