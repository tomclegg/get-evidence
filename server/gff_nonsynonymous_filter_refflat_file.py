#!/usr/bin/python
# Filename: gff_nonsynonymous_filter.py

"""
usage: %prog gff_file twobit_file
"""

# Appnd amino_acid attribute to nonsynonymous mutations, and filter out synonymous
# and non-coding mutations
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import math, os, sys
import MySQLdb
from utils import gff, twobit
from utils.biopython_utils import reverse_complement, translate
from utils.codon_intersect import codon_intersect
from config import DB_HOST, DB_READ_USER, DB_READ_PASSWD, DB_READ_DATABASE, REFFLAT_SORTED


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
    # we're done if it's not intronic or exonic
    if (record.strand == "+" and record.start <= cdsStart) or \
      (record.strand == "-" and record.end > cdsEnd):
        return ("5'-UTR",)
    
    if (record.strand == "+" and record.end > cdsEnd) or \
      (record.strand == "-" and record.start <= cdsStart):
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

    # parse out exons
    exons = []
    running_intron_count = running_exon_count = running_cds_bases_count = 0 # 1-based
    trimmed_bases = 0

    for j in range(0, len(exonStarts)):
        # discard any non-coding portions with this if statement
        if exonEnds[j] > cdsStart and exonStarts[j] <= cdsEnd:
            
            # trim the start and end to the coding region
            if exonStarts[j] < cdsStart:
                trimmed_bases = cdsStart - exonStarts[j]
                exonStarts[j] = cdsStart
            if exonEnds[j] > cdsEnd:
                trimmed_bases = exonEnds[j] - cdsEnd
                exonEnds[j] = cdsEnd
            
            # increment the count
            running_exon_count += 1
            
            # look at the intron, if applicable
            if len(exons) > 0:
                if strand == "+":
                    intron_start = exons[-1][1]
#                    intron_start = exons[-1][1] - 1 # the end of the last exon considered
                    intron_end = exonStarts[j]
                else:
                    intron_start = exonEnds[j]
                    intron_end = exons[-1][0]
                
                running_intron_count += 1
                
                # test if is in intron (remember, start and end are 1-based)
                # (this only works if record.start = record.end (i.e. SNPs);
                # otherwise, this will need to be adapted by taking strand
                # into account)
                if (record.start > intron_start and record.end <= intron_end):
                    return ("intron", running_intron_count)
            
            # look at exon (again, this only works if record.start = record.end
            # and assumes both are 1-based)
            if (record.start > exonStarts[j] and record.end <= exonEnds[j]):
                # figure out number of bases, amino acid residues, frame
                if strand == "+":
                    running_cds_bases_count += record.start - exonStarts[j]
                    frame_offset = running_cds_bases_count % 3
                    if frame_offset == 0:
                        frame_offset = 3
                        # chr direction =>
                        # translation direction =>
                        # -------------
                        # | 1 | 2 | 3 |
                        # -------------
                        #   ^ first base of codon
                        #
                        # note that this convention corresponds to frames 0, 2, 1
                        # respectively in GTF notation
                else:
                    running_cds_bases_count += exonEnds[j] + 1 - record.end
                    frame_offset = -1 * (running_cds_bases_count % 3)
                    if frame_offset == 0:
                        frame_offset = -3
                        # chr direction =>
                        # <= translation direction
                        # -------------
                        # |-3 |-2 |-1 |
                        # -------------
                        #           ^ first base of codon
                        #
                        # note that this convention corresponds to frames 1, 2, 0
                        # respectively in GTF notation
                
                # ugly, but that's the way it is, we want to divide by 3, then take the ceiling
                # as a (long) integer; so, we convert to float, divide, take the ceiling, then
                # convert back...
                amino_acid_residue = long(math.ceil(float(running_cds_bases_count) / 3))
                
                # figure out what we need, and prepare to look it up
                start_exon, end_exon, intervals = \
                  codon_intersect(record.start - 1, record.end, all_exons, frame_offset)
                
                # calculate the chromosome name we want to use
                if record.seqname.startswith("chr"):
                    chr = record.seqname
                else:
                    chr = "chr" + record.seqname
                
                # look it up
                ref_seq = "".join([twobit_file[chr][k[0]:k[1]] for k in intervals])
                
                # within each set of intervals, the same codons could have
                # different positions for alternative splicings, etc.
                replacement_coord = (frame_offset + 3) % 4
                
                # figure out which allele is not the mutant
                alleles = record.attributes["alleles"].strip("\"").split("/")
                try:
                    alleles.remove(record.attributes["ref_allele"])
                except ValueError:
                    pass

                if strand == "+":
                    alleles_relative_to_gene = alleles;
                    ref_relative_to_gene = record.attributes["ref_allele"]
                else:
                    alleles_relative_to_gene = map(reverse_complement,alleles)
                    ref_relative_to_gene = reverse_complement(record.attributes["ref_allele"])
                
                # now work through each mutant allele
                amino_acid_changes = []
                is_synonymous = True
                for mut_allele in alleles:                
                    mut_seq_list = list(ref_seq)
                    mut_seq_list[replacement_coord] = mut_allele
                    mut_seq = "".join(mut_seq_list)
                    
                    if frame_offset > 0 and not chr.startswith("chrM"):
                        ref_residue = translate(ref_seq)
                        mut_residue = translate(mut_seq)
                    elif frame_offset < 0 and not chr.startswith("chrM"):
                        ref_residue = translate(reverse_complement(ref_seq))
                        mut_residue = translate(reverse_complement(mut_seq))
                    elif frame_offset > 0:
                        ref_residue = translate(ref_seq, "Vertebrate Mitochondrial")
                        mut_residue = translate(mut_seq, "Vertebrate Mitochondrial")
                    else:
                        ref_residue = translate(reverse_complement(ref_seq),
                          "Vertebrate Mitochondrial")
                        mut_residue = translate(reverse_complement(mut_seq),
                          "Vertebrate Mitochondrial")
                    
                    if ref_residue != mut_residue:
                        amino_acid_changes.append(ref_residue + str(amino_acid_residue) + mut_residue)
                        is_synonymous = False
                
                # return info
                if not is_synonymous:
                    return ("nonsynonymous coding", running_exon_count, " ".join(amino_acid_changes))
                else:
                    return ("synonymous coding", running_exon_count, amino_acid_residue)
            
            # otherwise, continue the bookkeeping
            running_cds_bases_count += exonEnds[j] - exonStarts[j]                    
            exons.append([exonStarts[j], exonEnds[j]])

# based on <http://www.peterbe.com/plog/uniqifiers-benchmark>
def unique(seq): # not order preserving, but that's OK; we can sort it later
    return {}.fromkeys(seq).keys()

class refFlat:
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


def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 3:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # try opening the file both ways, in case the arguments got confused
    try:
        gff_file = gff.input(sys.argv[2])
        twobit_file = twobit.input(sys.argv[1])
    except Exception:
        gff_file = gff.input(sys.argv[1])
        twobit_file = twobit.input(sys.argv[2])
    
    # set up refFlat input
    refFlat_input = refFlat(os.getenv('DATA') + "/" + REFFLAT_SORTED)

    for record in gff_file:
        if record.seqname.startswith("chr"):
            chromosome = record.seqname
        else:
            if record.seqname.startswith("Chr"):
                chromosome = "chr" + record.seqname[3:]
            else:
                chromosome = "chr" + record.seqname
        
        # record.start is 1-based, but refFlat is 0-based, so subtract 1
        record_position = (chromosome, record.start - 1)

        transcripts = refFlat_input.cover_next_position(record_position)

        # Skip the rest if no transcripts are returned
        if (not transcripts):
            if (len(sys.argv) >= 4):
                if (sys.argv[3] == "print-all"):
                    print record
            continue

        # otherwise, cycle through
        inferences = []
        is_nonsynonymous = False
        
        for data in transcripts:
            # need to make "d" match up with refFlat's order
            # d : geneName, strand, cdsStart, cdsEnd, exonStarts, exonEnds
            #     0, 3, 6, 7, 9, 10
            d = ( data[0], data[3], int(data[6]), int(data[7]), data[9], data[10] )
            #print record_position
            #print d
            i = infer_function(twobit_file, record, *d)
            if i[0] == "nonsynonymous coding":
                inferences.append("%s %s" % (d[0], i[2]))
                is_nonsynonymous = True
        
        # set the attribute if we can
        if not is_nonsynonymous:
            if (len(sys.argv) >= 4):
                if (sys.argv[3] == "print-all"):
                    print record
                else:
                    continue
            else:
                continue
        else:
            if len(inferences) > 0:
                unique_inferences = unique(inferences)
                unique_inferences.sort(key=str.lower)
                record.attributes["amino_acid"] = "/".join(unique_inferences)
            print record
        

if __name__ == "__main__":
    main()
