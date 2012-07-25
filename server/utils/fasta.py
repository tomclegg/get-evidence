#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

# A quick-and-dirty FASTA file parser
# based in part on the example at <http://www.dalkescientific.com/writings/NBN/parsing.html>

import textwrap

class FastaRecord(object):
    def __init__(self, title, sequence):
        self.title = title
        self.sequence = sequence
    def __str__(self):
        return ">" + self.title + "\n" + textwrap.fill(self.sequence, 50)

def _fasta_iterator(f):
    # get started with the first title
    title = f.readline()    
    if not title.startswith(">"):
        raise Exception("not a FASTA file")
    title = title[1:].rstrip()
    
    # start reading in sequence
    sequence = []
    for line in f:
        # yield record if at next sequence, reset
        if line.startswith(">"):
            yield FastaRecord(title, "".join(sequence))
            
            title = line[1:].rstrip()
            sequence = []
            continue
        
        # otherwise, append to sequence list
        line = line.strip()
        sequence.append(line)
    
    # we're at the end of the file; yield the last record
    yield FastaRecord(title, "".join(sequence))

class FastaFile(object):
    def __init__(self, src):
        # try to open the file, in case we're given a path
        try:
            f = open(src)
        # if that doesn't work, treat the argument itself as a file
        except TypeError:
            f = src
        self.iterator = _fasta_iterator(f)
        self.file = f
    
    def __iter__(self):
        return self
    
    def next(self):
        return self.iterator.next()
    
    def __getitem__(self, key):
        key = key.rstrip()
        for record in iter(self):
            if key == record.title:
                return record
        return None
    
    def close(self):
        assert (self.file is not None)
        self.file.close()
        self.file = None

def input(src):
    return FastaFile(src)
