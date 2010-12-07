#!/usr/bin/python

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
            data = buffer.rstrip('\n').split("\t")
            buffer = line
        if data[2] == "all" or data[1] == "1":
            start_onebased = str(int(data[4]) + 1)
            if data[6] == "ref":
                print data[3] + "\tCGI\tREF\t" + start_onebased + "\t" + data[5] + "\t.\t+\t.\t."
            elif data[6] == "no-ref" or re.match("no-call",data[6]) or re.match("PAR-called-in-X",data[6]):
                continue
            else:
                type = "INDEL"
                if len(data[7]) == 1 and len(data[8]) == 1:
                    type = "SNP"
                elif len(data[7]) == len(data[8]):
                    type = "SUB"
                alleles = data[8]
                ref_allele = data[7]
                if len(alleles) == 0:
                    alleles = "-"
                if len(ref_allele) == 0:
                    ref_allele = "-"
                print data[3] + "\tCGI\t" + type + "\t" + start_onebased + "\t" + data[5] + "\t.\t+\t.\talleles " + alleles + ";ref_allele " + ref_allele + dbsnp_string(data[11])
        elif data[2] == "1":   # two different strands called, get all of 1 then all of 2
            strand1_data = [ data ]
            buffer_data = buffer.rstrip('\n').split("\t")
            while buffer_data[2] == "1":
                new_strand = buffer_data[:]
                strand1_data.append(new_strand)
                buffer = file.next()
                buffer_data = buffer.rstrip('\n').split("\t")
            strand2_data = [ ]
            while buffer_data[2] == "2":
                new_strand = buffer_data[:]
                strand2_data.append(new_strand)
                buffer = file.next()
                buffer_data = buffer.rstrip('\n').split("\t")
            # loop through for matches from strand1 pos to strand2 pos
            for strand1 in strand1_data:
                for strand2 in strand2_data:
                    # skip sites where only one strand got called
                    if re.match("no-call", strand1[6]) or re.match("no-call",strand2[6]):
                        continue
                    # check both describe same position
                    if strand1[4] == strand2[4] and strand1[5] == strand2[5]:
                        type = "INDEL"
                        if (len(strand1[7]) == len(strand1[8]) and len(strand2[7]) == len(strand2[8])):
                            if (len(strand1[7]) == 1):
                                type = "SNP"
                            else:
                                type = "SUB"
                        start_onebased = str(int(strand1[4]) + 1)
                        if len(strand1[7]) == 0:
                            strand1[7] = "-"
                        if len(strand1[8]) == 0:
                            strand1[8] = "-"
                        if len(strand2[7]) == 0:
                            strand2[7] = "-"
                        if len(strand2[8]) == 0:
                            strand2[8] = "-"
                        if strand1[8] == strand2[8]:
                            print strand1[3] + "\tCGI\t" + type + "\t" + start_onebased + "\t" + strand1[5] + "\t.\t+\t.\talleles " + strand1[8] + ";ref_allele " + strand1[7] + dbsnp_string(strand1[11])
                        else:
                            print strand1[3] + "\tCGI\t" + type + "\t" + start_onebased + "\t" + strand1[5] + "\t.\t+\t.\talleles " + strand1[8] + "/" + strand2[8] + ";ref_allele " + strand1[7] + dbsnp_string(strand1[11],strand2[11])
        else:  # this shouldn't happen, if it does just continue
            continue
            
def dbsnp_string(data1, data2=None):
    db_xref_items = data1.split(";")
    if data2:
        db_xref_items = db_xref_items + data2.split(";")
    if db_xref_items and len(db_xref_items[0]) > 0:
        return ";db_xref " + ",".join(db_xref_items)
    else:
        return ""

if __name__ == "__main__":
    main()

