#!/usr/bin/python
# Filename: biopython_utils.py

# Mini version of some useful parts of BioPython
# ---
# Portions copyright 2000-2002 Brad Chapman; 2004-2005 by M de Hoon; 2007 by Peter Cock
# "This code is part of the Biopython distribution and governed by its license"

import array, string, re

class TranslationError(Exception):
    pass
    
class CodonTable:
    def __init__(self, name, table, stop_codons, start_codons):
        self.name = name
        self.forward_table = table # only includes codons which actually code
        self.back_table = None # for back translations
#        self.back_table = self.make_back_table(table, stop_codons[0])
        self.start_codons = start_codons
        self.stop_codons = stop_codons
        self._cache = {}

    def __getitem__(self, codon):
        try:
            x = self._cache[codon]
        except KeyError:
            pass
        else:
            if x is TranslationError:
                raise TranslationError, codon # no translation
            if x is KeyError:
                raise KeyError, codon # it's a stop codon
            return x
        try:
            x = self.forward_table[codon]
            self._cache[codon] = x
            return x
        except KeyError:
            if self.stop_codons.count(codon):
                self._cache[codon] = KeyError
                raise KeyError, codon # it's a stop codon
            else:
                self._cache[codon] = TranslationError
                raise TranslationError, codon # no translation

    def get(self, codon, stop_symbol = None):
        try:
            return self.__getitem__(codon)
        except KeyError:
            return stop_symbol

    def make_back_table(self, table, default_stop_codon):
        # ONLY RETURNS A SINGLE CODON
        # do the sort so changes in the hash implementation won't affect
        # the result when one amino acid is coded by more than one codon.
        back_table = {}
        keys = table.keys() ; keys.sort()
        for key in keys:
            back_table[table[key]] = key
        back_table[None] = default_stop_codon
        return back_table

ambiguous_dna_complement = {
    "A": "T",
    "C": "G",
    "G": "C",
    "T": "A",
    "M": "K",
    "R": "Y",
    "W": "W",
    "S": "S",
    "Y": "R",
    "K": "M",
    "V": "B",
    "H": "D",
    "D": "H",
    "B": "V",
    "X": "X",
    "N": "N",
}

codon_tables = {
    "Standard": CodonTable('Standard',
        { 'TTT': 'F', 'TTC': 'F', 'TTA': 'L', 'TTG': 'L', 'TCT': 'S',
          'TCC': 'S', 'TCA': 'S', 'TCG': 'S', 'TAT': 'Y', 'TAC': 'Y',
          'TGT': 'C', 'TGC': 'C', 'TGG': 'W', 'CTT': 'L', 'CTC': 'L',
          'CTA': 'L', 'CTG': 'L', 'CCT': 'P', 'CCC': 'P', 'CCA': 'P',
          'CCG': 'P', 'CAT': 'H', 'CAC': 'H', 'CAA': 'Q', 'CAG': 'Q',
          'CGT': 'R', 'CGC': 'R', 'CGA': 'R', 'CGG': 'R', 'ATT': 'I',
          'ATC': 'I', 'ATA': 'I', 'ATG': 'M', 'ACT': 'T', 'ACC': 'T',
          'ACA': 'T', 'ACG': 'T', 'AAT': 'N', 'AAC': 'N', 'AAA': 'K',
          'AAG': 'K', 'AGT': 'S', 'AGC': 'S', 'AGA': 'R', 'AGG': 'R',
          'GTT': 'V', 'GTC': 'V', 'GTA': 'V', 'GTG': 'V', 'GCT': 'A',
          'GCC': 'A', 'GCA': 'A', 'GCG': 'A', 'GAT': 'D', 'GAC': 'D',
          'GAA': 'E', 'GAG': 'E', 'GGT': 'G', 'GGC': 'G', 'GGA': 'G',
          'GGG': 'G', },
        [ 'TAA', 'TAG', 'TGA', ],
        [ 'TTG', 'CTG', 'ATG', ]),

    "Vertebrate Mitochondrial": CodonTable('Vertebrate Mitochondrial',
        { 'TTT': 'F', 'TTC': 'F', 'TTA': 'L', 'TTG': 'L', 'TCT': 'S',
          'TCC': 'S', 'TCA': 'S', 'TCG': 'S', 'TAT': 'Y', 'TAC': 'Y',
          'TGT': 'C', 'TGC': 'C', 'TGA': 'W', 'TGG': 'W', 'CTT': 'L',
          'CTC': 'L', 'CTA': 'L', 'CTG': 'L', 'CCT': 'P', 'CCC': 'P',
          'CCA': 'P', 'CCG': 'P', 'CAT': 'H', 'CAC': 'H', 'CAA': 'Q',
          'CAG': 'Q', 'CGT': 'R', 'CGC': 'R', 'CGA': 'R', 'CGG': 'R',
          'ATT': 'I', 'ATC': 'I', 'ATA': 'M', 'ATG': 'M', 'ACT': 'T',
          'ACC': 'T', 'ACA': 'T', 'ACG': 'T', 'AAT': 'N', 'AAC': 'N',
          'AAA': 'K', 'AAG': 'K', 'AGT': 'S', 'AGC': 'S', 'GTT': 'V',
          'GTC': 'V', 'GTA': 'V', 'GTG': 'V', 'GCT': 'A', 'GCC': 'A',
          'GCA': 'A', 'GCG': 'A', 'GAT': 'D', 'GAC': 'D', 'GAA': 'E',
          'GAG': 'E', 'GGT': 'G', 'GGC': 'G', 'GGA': 'G', 'GGG': 'G', },
        [ 'TAA', 'TAG', 'AGA', 'AGG', ],
        [ 'ATT', 'ATC', 'ATA', 'ATG', 'GTG', ])
}

def reverse_complement(seq):
    """
    Returns the reverse complement DNA sequence (string).
    """
    d = ambiguous_dna_complement

    before = ''.join(d.keys())
    after  = ''.join(d.values())
    before = before + before.lower()
    after  = after + after.lower()
    ttable = string.maketrans(before, after)

    # much faster on really long sequences than the previous loop based one
    # thx to Michael Palmer, University of Waterloo
    s = seq.translate(ttable)
    return s[::-1]
    
def translate(seq, table="Standard", stop_symbol="*"):
    """
    Translate a DNA sequence into amino acids (string).

    table - Which codon table to use? This is a name
            "Standard" or "Vertebrate Mitochondrial"
    """
    table = codon_tables[table]
    
    get = table.get
    seq = seq.upper()
    n = len(seq)
    
    protein = [get(seq[i:i+3], stop_symbol) for i in xrange(0,n-n%3,3)]
    protein = "".join(protein)
    return protein
 