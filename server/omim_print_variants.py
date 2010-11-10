#!/usr/bin/python
# Filename: omim_print_variants.py

"""
usage: %prog omim.txt
"""

# Output tab-separated allelic variant information for each entry in OMIM
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import sys, math, re
from utils import omim

one_letter_alphabet = {
    "Ala": "A",
    "Arg": "R",
    "Asn": "N",
    "Asp": "D",
    "Cys": "C",
    "Gln": "Q",
    "Glu": "E",
    "Gly": "G",
    "His": "H",
    "Ile": "I",
    "Leu": "L",
    "Lys": "K",
    "Met": "M",
    "Phe": "F",
    "Pro": "P",
    "Ser": "S",
    "Thr": "T",
    "Trp": "W",
    "Tyr": "Y",
    "Val": "V",
    "Asx": "B",
    "Glx": "Z",
    "Xaa": "X",
    "TERM": "*"
}

def _process_variant_title(t):
    # when there are no alternative titles, the OMIM parser returns an empty 2nd element
    if len(t) == 2 and len(t[1]) == 0:
        del t[1]
        return t
    
    # sometimes, the alternative title isn't an alternative title at all; the first title was just
    # too long and wrapped (at around 75 characters); we should fix these too
    if len(t) >= 2 and t[1].find(", INCLUDED") < 0:
        try:
            first_word_length = re.match("['\"\\w]+", t[1]).span()[1]
        except AttributeError:
            print >> sys.stderr, "Regex match failed with string:", t[1]
            first_word_length = -1
        test_length = len(t[0]) + first_word_length + 1
        if test_length >= 75:
            t[0] += " " + t[1]
            del t[1]
            # print >> sys.stderr, t[0]
            return t
    
    # otherwise, return it untouched
    return t

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    f = omim.input(sys.argv[1])
    av_count = del_count = dup_count = ins_count = ivs_count = fs_count = unknown_count = 0
    for record in f:
        if record.allelic_variants is not None:
            av_count += len(record.allelic_variants)
            for variant in record.allelic_variants:
                if variant.mutation is not None:
                    if variant.mutation.find("DEL") >= 0:
                        del_count += 1
                    elif variant.mutation.find("DUP") >= 0:
                        dup_count += 1
                    elif variant.mutation.find("INS") >= 0:
                        ins_count += 1
                    elif variant.mutation.find("IVS") >= 0:
                        ivs_count += 1
                    elif variant.mutation.find("FS") >= 0:
                        fs_count += 1
                    else:
                        # if we don't have a specified point mutation, class it as unknown
                        try:
                            aa_pos = int(variant.mutation[3:-3])
                        except ValueError:
                            unknown_count += 1
                            continue
                        
                        # reformat the reference and mutant amino acids
                        ref_aa = variant.mutation[0:3].title()
                        if ref_aa == "Ter":
                            ref_aa = "TERM"
                        elif ref_aa not in one_letter_alphabet.keys():
                            unknown_count += 1
                            continue
                        
                        mut_aa = variant.mutation[-3:].title()
                        if mut_aa == "Ter":
                            mut_aa = "TERM"
                        elif mut_aa not in one_letter_alphabet.keys():
                            unknown_count += 1
                            continue
                        
                        # process and print the phenotypes
                        phenotypes = _process_variant_title(variant.title)
                        for p in phenotypes:
                            print "%s\t%s\t%s-%s\t%s\t%s\tomim:%s%s" % (p, variant.gene,
                              ref_aa, mut_aa, aa_pos, variant.text.count(" ") + 1,
                              record.number, variant.number)

    print >> sys.stderr, "TOTAL VARIANTS PARSED:", av_count, "DEL:", del_count,
    print >> sys.stderr, "DUP:", dup_count, "INS:", ins_count,
    print >> sys.stderr, "IVS:", ivs_count, "FS:", fs_count,
    print >> sys.stderr, "UNKNOWN:", unknown_count

if __name__ == "__main__":
    main()
