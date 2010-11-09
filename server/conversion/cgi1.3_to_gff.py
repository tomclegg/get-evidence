#!/usr/bin/python

# Convert CGI var file (version 1.3) to GFF
# Example usage: bzcat var-GS000001394-ASM.tsv.bz2 | python cgi1.3_to_gff.py > PGP1_cgi.gff

import sys
import re

def main():
    file = sys.stdin
    for line in file:
        if re.search("^#", line): # skip commented lines
            continue
        if re.search("^\W*$", line): # skip empty lines
            continue
        if re.search("^>", line): # skip header line
            continue
        data = line.split("\t")
        if data[2] == "all" or data[1] == "1":
            if data[6] == "ref" or re.match("no-call",data[6]):
                continue
            else:
                # only report single nucleotide substitutions
                if len(data[7]) == 1 and len(data[8]) == 1:
                    print data[3] + "\tCGI\tSNP\t" + data[5] + "\t" + data[5] + "\t.\t+\t.\talleles " + data[8] + ";ref_allele " + data[7]
        else:   # two different alleles called, get second
            line2 = file.next()
            data2 = line2.split("\t")
            # ignore half-called reads
            if re.match("no-call",data[6]) or re.match("no-call",data2[6]):
                continue
            # only report single nucleotide substitutions
            if len(data[7]) == 1 and len(data[8]) == 1 and len(data2[7]) == 1 and len(data2[8]) == 1:
                if (data[8] == data2[8]):
                    print data[3] + "\tCGI\tSNP\t" + data[5] + "\t" + data[5] + "\t.\t+\t.\talleles " + data[8] + ";ref_allele " + data[7]
                else:
                    print data[3] + "\tCGI\tSNP\t" + data[5] + "\t" + data[5] + "\t.\t+\t.\talleles " + data[8] + "/" + data2[8] + ";ref_allele " + data[7]
            

if __name__ == "__main__":
    main()

