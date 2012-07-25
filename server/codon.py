# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

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
    "TERM": "*",
    "Stop": "*",
    "Frameshift": "Shift"
}

three_letter_alphabet = {
    "A": "Ala",
    "R": "Arg",
    "N": "Asn",
    "D": "Asp",
    "C": "Cys",
    "Q": "Gln",
    "E": "Glu",
    "G": "Gly",
    "H": "His",
    "I": "Ile",
    "L": "Leu",
    "K": "Lys",
    "M": "Met",
    "F": "Phe",
    "P": "Pro",
    "S": "Ser",
    "T": "Thr",
    "W": "Trp",
    "Y": "Tyr",
    "V": "Val",
    "B": "Asx",
    "Z": "Glx",
    "X": "Xaa",
    "*": "TERM"
}

def codon_123 (x):
    try:
        return three_letter_alphabet[x]
    except KeyError:
        return 'XXX'

def codon_321 (x):
    try:
        return one_letter_alphabet[x]
    except KeyError:
        return '*'

    
