#!/usr/bin/python

# Convert CGI var file (version 1.3) to GFF
# Example usage: bzcat var-GS000001394-ASM.tsv.bz2 | python cgi1.3_to_gff.py > PGP1_cgi.gff

import sys
import re

def main():
    file = sys.stdin
    buffer = ""
    for line in file:
        if re.search("^#", line): # skip commented lines
            continue
        if re.search("^\W*$", line): # skip empty lines
            continue
        if re.search("^>", line): # skip header line
            continue
        # Need buffer because later we have read-ahead until next line position is past current
        if len(buffer) == 0:
            buffer = line
            continue
        else:
            data = buffer.split("\t")
            buffer = line
        if data[2] == "all" or data[1] == "1":
            if data[6] == "ref" or data[6] == "no-ref" or re.match("no-call",data[6]) or re.match("PAR-called-in-X",data[6]):
                continue
            else:
                # only report single nucleotide substitutions
                if len(data[7]) == 1 and len(data[8]) == 1:
                    print data[3] + "\tCGI\tSNP\t" + data[5] + "\t" + data[5] + "\t.\t+\t.\talleles " + data[8] + ";ref_allele " + data[7]
        elif data[2] == "1":   # two different strands called, get all of 1 then all of 2
            strand1_data = [ data ]
            buffer_data = buffer.split("\t")
            while buffer_data[2] == "1":
                new_strand = buffer_data[:]
                strand1_data.append(new_strand)
                buffer = file.next()
                buffer_data = buffer.split("\t")
            strand2_data = [ ]
            while buffer_data[2] == "2":
                new_strand = buffer_data[:]
                strand2_data.append(new_strand)
                buffer = file.next()
                buffer_data = buffer.split("\t")
            # loop through for matches from strand1 pos to strand2 pos
            for strand1 in strand1_data:
                for strand2 in strand2_data:
                    # skip sites where only one strand got called
                    if re.match("no-call", strand1[6]) or re.match("no-call",strand2[6]):
                        continue
                    # check both describe same position
                    if strand1[4] == strand2[4] and strand1[5] == strand2[5]:
                        # only report single nucleotide substitutions
                        if len(strand1[7]) == 1 and len(strand1[8]) == 1 and len(strand2[7]) == 1 and len(strand2[8]) == 1:
                            if strand1[8] == strand2[8]:
                                print strand1[3] + "\tCGI\tSNP\t" + strand1[5] + "\t" + strand1[5] + "\t.\t+\t.\talleles " + strand1[8] + ";ref_allele " + strand1[7]
                            else:
                                print strand1[3] + "\tCGI\tSNP\t" + strand1[5] + "\t" + strand1[5] + "\t.\t+\t.\talleles " + strand1[8] + "/" + strand2[8] + ";ref_allele " + strand1[7]
        else:  # this shouldn't happen, if it does just continue
            continue
            

if __name__ == "__main__":
    main()

