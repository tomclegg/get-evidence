#!/usr/bin/python
"""
twobit.py a python-based 2bit parser

extracted from the bx-python project with minor modifications
---
This code is part of the bx-python project and is governed by its license.
"""

import _twobit

from struct import unpack, calcsize
from UserDict import DictMixin

TWOBIT_MAGIC_NUMBER = 0x1A412743
TWOBIT_MAGIC_NUMBER_SWAP = 0x4327411A
TWOBIT_MAGIC_SIZE = 4
TWOBIT_VERSION = 0

class TwoBitSequence(object):
    """Store index, length, and other information for a twobit sequence"""
    def __init__(self, twobit_file, header_offset=None):
        self.twobit_file = twobit_file
        self.header_offset = header_offset
        self.sequence_offset = None
        self.size = None
        self.n_blocks = None
        self.masked_blocks = None
        self.loaded = False
        
    def __getitem__(self, slice_data):
        """
        Interpret slice data and return region of sequence from twobit file
        """
        start, stop, stride = slice_data.indices(self.size)
        assert stride == 1, "striding in slices not supported"
        if stop - start < 1:
            return ""
        return _twobit.read(self.twobit_file, self, start, stop, False)
        
    def __len__(self):
        """Return sequence size"""
        return self.size
        
    def get(self, start, end):
        """Get region of sequence from twobit file"""
        # Trim start / stop
        if start < 0:
            start = 0
        if end > self.size:
            end = self.size
        out_size = end - start
        if out_size < 1:
            raise Exception("end before start (%s, %s)" % (start, end))
        # Find position of packed portion
        dna = _twobit.read(self.twobit_file, self, start, end, False)
        # Return
        return dna
        
class TwoBitFile(DictMixin):
    """Open and keep track of twobit genome file"""

    def __init__(self, src, do_mask=False):
        # Try to open the file, in case we're given a path
        try:
            twobit_file = open(src)
        # If that doesn't work, treat the argument itself as a file
        except TypeError:
            twobit_file = src
        self.do_mask = do_mask
        # Read magic and determine byte order
        self.byte_order = ">"
        magic = unpack(">L", twobit_file.read(TWOBIT_MAGIC_SIZE))[0]
        if magic != TWOBIT_MAGIC_NUMBER:
            if magic == TWOBIT_MAGIC_NUMBER_SWAP: 
                self.byte_order = "<"
            else: 
                raise Exception("not a 2bit file")
        self.magic = magic
        self.twobit_file = twobit_file
        # Read version
        self.version = self.read("L")
        if self.version != TWOBIT_VERSION:
            raise Exception("file is version '%d' but I only know about '%d'" %
                            (self.version, TWOBIT_VERSION))
        # Number of sequences in file
        self.seq_count = self.read("L")
        # Header contains some reserved space
        self.reserved = self.read("L")
        # Read index of sequence names to offsets
        index = dict()
        for i in range(self.seq_count):
            name = self.read_p_string()
            offset = self.read("L")
            index[name] = TwoBitSequence(self.twobit_file, offset)
        self.index = index
    
    def __getitem__(self, name):
        """Return sequence region requested, load index data if necessary"""
        seq = self.index[name]
        if not seq.loaded:
            for item in self.index:
                if self.index[item].loaded:
                    self.unload_sequence(item)
            self.load_sequence(name)
        return seq
    
    def close(self):
        """Close twobit file"""
        assert (self.twobit_file is not None)
        self.twobit_file.close()
        self.twobit_file = None
    
    def keys(self):
        """Report sequence names"""
        return self.index.keys()
        
    def load_sequence(self, name):
        """
        Store positions for a seq, to be used later as an index for file read
        """
        seq = self.index[name]
        # Seek to start of sequence block
        self.twobit_file.seek(seq.header_offset)
        # Size of sequence
        seq.size = self.read("L")
        # Read N and masked block regions
        seq.n_block_starts, seq.n_block_sizes = self.read_block_coords()
        seq.masked_block_starts, seq.masked_block_sizes = self.read_block_coords(skip=True)
        seq.masked_block_starts = None
        seq.masked_block_sizes = None
        # Reserved
        self.read("L")
        # Save start of actual sequence
        seq.sequence_offset = self.twobit_file.tell()
        # Mark as loaded
        seq.loaded = True
        
    def unload_sequence(self, name):
        """
        Attempt to remove stored data when done with a chromosome

        We can do this because we've presorted files in analysis, 
        we shouldn't see that sequence again. Unfortunately, using del 
        doesn't seem to be freeing the memory with python.
        """
        offset = self.index[name].header_offset
        del(self.index[name])
        self.index[name] = TwoBitSequence(self.twobit_file, offset)

    def read_block_coords(self, skip=False):
        """Read in the block coordinates from UCSC file"""
        # note that each usage of read moves the file pointer
        block_count = self.read("L")
        if block_count == 0:
            return [], []
        starts = self.read(str(block_count) + "L", untuple=False, skip=skip)
        sizes = self.read(str(block_count) + "L", untuple=False, skip=skip)
        return list(starts), list(sizes)
        
    def read(self, pattern, untuple=True, skip=False):
        """
        Read in twobit data from a file and use struct.unpack to interpret it.
        """
        # Omitting the byte order from calcsize() causes problems in 64-bit
        # systems when the native sizes differ from the standard Python sizes
        if skip:
            # This is a hack added so we can skip storing an array of ints for
            # masked regions -- python fails to free the memory and we don't
            # need repeat-masking.
            self.twobit_file.read(calcsize(self.byte_order + pattern))
            return []
        else:
            rval = unpack(self.byte_order + pattern, 
                      self.twobit_file.read(calcsize(self.byte_order + 
                                                     pattern)))
            if untuple and len(rval) == 1: 
                return rval[0]
            return rval
        
    def read_p_string(self):
        """
        Read a length-prefixed string 
        """
        length = self.read("B")
        return self.twobit_file.read(length)

def input(tbf):
    return TwoBitFile(tbf)
