# Filename: bitset.pyx

# Extracted from the bx-python project with modifications to replace inline
# function calls with their explicit equivalents (surrounded by braces in
# comments; inline functions are supported in Cython but not in Pyrex),
# changing from __new__ to __cinit__, etc. Added clone() to BinnedBitSet.
# ---
# This code is part of the bx-python project and is governed by its license.

"""
Compact mutable sequences of bits (vectors of 0s and 1s) supporting various
boolean operations, and a "binned" variation which stores long runs of 
identical bits compactly.

Because the binned implementation avoids a lot of memory allocation and access
when working with either small subregions of the total interval or setting /
testing spans larger than the bin size, it can be much faster.
"""

cdef extern from "common.h":
    ctypedef int boolean

cdef extern from "bits.h":
    ctypedef unsigned char Bits
    # Allocate bits. 
    Bits *bitAlloc(int bitCount)
    # Clone bits. 
    Bits *bitClone(Bits* orig, int bitCount)
    # Free bits; takes the address as argument for some reason (i.e. &bits). 
    void bitFree(Bits **pB)
    # Set a single bit. 
    void bitSetOne(Bits *b, int bitIx)
    # Clear a single bit. 
    void bitClearOne(Bits *b, int bitIx)
    # Set a range of bits. 
    void bitSetRange(Bits *b, int startIx, int bitCount)
    # Read a single bit. 
    int bitReadOne(Bits *b, int bitIx)
    # Count number of bits set in range. 
    int bitCountRange(Bits *b, int startIx, int bitCount)
    # Find the index of the the next set bit. 
    int bitFindSet(Bits *b, int startIx, int bitCount)
    # Find the index of the the next clear bit. 
    int bitFindClear(Bits *b, int startIx, int bitCount)
    # Clear many bits. 
    void bitClear(Bits *b, int bitCount)
    # And two bitmaps. Put result in a. 
    void bitAnd(Bits *a, Bits *b, int bitCount)
    # Or two bitmaps. Put result in a. 
    void bitOr(Bits *a, Bits *b, int bitCount)
    # Xor two bitmaps. Put result in a. 
    void bitXor(Bits *a, Bits *b, int bitCount)
    # Flip all bits in a. 
    void bitNot(Bits *a, int bitCount)
    ## # Print part or all of bit map as a string of 0s and 1s.
    ## void bitPrint(Bits *a, int startIx, int bitCount, FILE* out)

cdef extern from "binBits.h":
    struct BinBits:
        int size
        int bin_size
        int nbins
        Bits **bins
    BinBits *binBitsAlloc(int size, int granularity)
    void binBitsFree(BinBits *bb)
    int binBitsReadOne(BinBits *bb, int pos)
    void binBitsSetOne(BinBits *bb, int pos)
    void binBitsClearOne(BinBits *bb, int pos)
    void binBitsSetRange(BinBits *bb, int start, int size)
    int binBitsCountRange(BinBits *bb, int start, int size)
    int binBitsFindSet(BinBits *bb, int start)
    int binBitsFindClear(BinBits *bb, int start)
    void binBitsAnd(BinBits *bb1, BinBits *bb2)
    void binBitsOr(BinBits *bb1, BinBits *bb2)
    void binBitsNot(BinBits *bb)

## ---- Forward declarations ------------------------------------------------

cdef class BitSet
cdef class BinnedBitSet

## ---- BitSet bounds checking ----------------------------------------------
unsupported_inline_code = """
cdef inline b_check_index(BitSet b, index):
    if index < 0:
        raise IndexError("BitSet index (%d) must be non-negative" % index)
    if index >= b.bitCount:
        raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, b.bitCount))
    
cdef inline b_check_range(BitSet b, start, end):
    b_check_index(b, start)
    if end < start:
        raise IndexError("range end (%d) must be greater than range start(%d)" % (end, start))
    if end > b.bitCount:
        raise IndexError("end %d is larger than the size of this BitSet (%d)" % (end, b.bitCount))
        
cdef inline b_check_range_count(BitSet b, start, count):
    b_check_index(b, start)
    if count < 0:
        raise IndexError("count (%d) must be non-negative" % count)
    if start + count > b.bitCount:
        raise IndexError("end %d is larger than the size of this BitSet (%d)" % (start + count, b.bitCount))

cdef inline b_check_same_size(BitSet b, BitSet other):
    if b.bitCount != other.bitCount:
        raise ValueError("BitSets must have the same size")
"""
## ---- BitSet --------------------------------------------------------------

# Maximum value of a signed 32-bit integer (2**31 - 1)
cdef int MAX_INT
MAX_INT = 2147483647

cdef class BitSet:
    cdef Bits * bits
    cdef int bitCount

    def __cinit__(self, bitCount):
        if bitCount > MAX_INT:
            raise ValueError("%d is larger than the maximum BitSet size of %d" % (bitCount, MAX_INT))
        self.bitCount = bitCount
        self.bits = bitAlloc(bitCount)

    def __dealloc__(self):
        bitFree(& self.bits)

    property size:
        def __get__(self):
            return self.bitCount

    def set(self, index):
        # b_check_index(self, index)
        # {
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bitCount:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bitCount))
        # }
        bitSetOne(self.bits, index)

    def clear(self, index):
        # b_check_index(self, index)
        # {
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bitCount:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bitCount))
        # }
        bitClearOne(self.bits, index)

    def clone(self):
        other = BitSet(self.bitCount)
        other.ior(self)
        return other

    def set_range(self, start, count):
        # b_check_range_count(self, start, count)
        # {
        #   b_check_index(self, start)
        #   {
        index = start
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bitCount:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bitCount))
        #   }
        if count < 0:
            raise IndexError("count (%d) must be non-negative" % count)
        if start + count > self.bitCount:
            raise IndexError("end %d is larger than the size of this BitSet (%d)" % (start + count, self.bitCount))
        # }
        bitSetRange(self.bits, start, count)

    def get(self, index):
        # b_check_index(self, index)
        # {
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bitCount:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bitCount))
        # }
        return bitReadOne(self.bits, index);

    def count_range(self, start=0, count=None):
        if count == None: 
            count = self.bitCount - start
        # b_check_range_count(self, start, count)
        # {
        #   b_check_index(self, start)
        #   {
        index = start
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bitCount:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bitCount))
        #   }
        if count < 0:
            raise IndexError("count (%d) must be non-negative" % count)
        if start + count > self.bitCount:
            raise IndexError("end %d is larger than the size of this BitSet (%d)" % (start + count, self.bitCount))
        # }
        return bitCountRange(self.bits, start, count)

    def next_set(self, start, end=None):
        if end == None: 
            end = self.bitCount
        # b_check_range(self, start, end)
        # {
        #   b_check_index(self, start)
        #   {
        index = start
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bitCount:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bitCount))
        #   }
        if end < start:
            raise IndexError("range end (%d) must be greater than range start(%d)" % (end, start))
        if end > self.bitCount:
            raise IndexError("end %d is larger than the size of this BitSet (%d)" % (end, self.bitCount))
        # }
        return bitFindSet(self.bits, start, end)

    def next_clear(self, start, end=None):
        if end == None: 
            end = self.bitCount
        # b_check_range(self, start, end)
        # {
        #   b_check_index(self, start)
        #   {
        index = start
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bitCount:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bitCount))
        #   }
        if end < start:
            raise IndexError("range end (%d) must be greater than range start(%d)" % (end, start))
        if end > self.bitCount:
            raise IndexError("end %d is larger than the size of this BitSet (%d)" % (end, self.bitCount))
        # }
        return bitFindClear(self.bits, start, end)

    def iand(self, BitSet other):
        # b_check_same_size(self, other)
        # {
        if self.bitCount != other.bitCount:
            raise ValueError("BitSets must have the same size")
        # }
        bitAnd(self.bits, other.bits, self.bitCount)

    def ior(self, BitSet other): 
        # b_check_same_size(self, other)
        # {
        if self.bitCount != other.bitCount:
            raise ValueError("BitSets must have the same size")
        # }
        bitOr(self.bits, other.bits, self.bitCount)

    def ixor(self, BitSet other): 
        # b_check_same_size(self, other)
        # {
        if self.bitCount != other.bitCount:
            raise ValueError("BitSets must have the same size")
        # }
        bitXor(self.bits, other.bits, self.bitCount)

    def invert(self):
        bitNot( self.bits, self.bitCount)

    def __getitem__(self, index):
        return self.get(index)

    def __iand__(self, other):
        self.iand(other)
        return self

    def __ior__(self, other):
        self.ior(other)
        return self

    def __invert__(self):
        self.invert()
        return self

## ---- BinnedBitSet bounds checking ----------------------------------------
unsupported_inline_code = unsupported_inline_code + """
cdef inline bb_check_index(BinnedBitSet bb, index):
    if index < 0:
        raise IndexError("BitSet index (%d) must be non-negative" % index)
    if index >= bb.bb.size:
        raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, bb.bb.size))        
cdef inline bb_check_start(BinnedBitSet bb, start):
    bb_check_index(bb, start)
cdef inline bb_check_range_count(BinnedBitSet bb, start, count):
    bb_check_index(bb, start)
    if count < 0:
        raise IndexError("count (%d) must be non-negative" % count)
    if start + count > bb.bb.size:
        raise IndexError("end (%d) is larger than the size of this BinnedBitSet (%d)" % (start + count, bb.bb.size))
cdef inline bb_check_same_size(BinnedBitSet bb, BinnedBitSet other):
    if bb.bb.size != other.bb.size:
        raise ValueError("BitSets must have the same size")
"""
## ---- BinnedBitSet --------------------------------------------------------

MAX = 512 * 1024 * 1024 

cdef class BinnedBitSet:
    cdef BinBits * bb
    cdef int granularity

    def __cinit__(self, size=MAX, granularity=1024):
        if size > MAX_INT:
            raise ValueError("%d is larger than the maximum BinnedBitSet size of %d" % (size, MAX_INT))
        self.granularity = granularity
        self.bb = binBitsAlloc(size, granularity)

    def __dealloc__(self):
        binBitsFree(self.bb);

    def __getitem__(self, index):
        # bb_check_index(self, index)
        # {
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bb.size:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bb.size))        
        # }
        return binBitsReadOne(self.bb, index)

    def set(self, index):
        # bb_check_index(self, index)
        # {
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bb.size:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bb.size))        
        # }
        binBitsSetOne(self.bb, index)

    def clear(self, index):
        # bb_check_index(self, index)
        # {
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bb.size:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bb.size))        
        # }
        binBitsClearOne(self.bb, index)

    def clone(self):
        other = BinnedBitSet(self.bb.size, self.granularity)
        other.ior(self)
        return other

    def set_range(self, int start, count):
        # bb_check_range_count(self, start, count)
        # {
        #   bb_check_index(self, start)
        #   {
        index = start
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bb.size:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bb.size))
        #   }
        if count < 0:
            raise IndexError("count (%d) must be non-negative" % count)
        if start + count > self.bb.size:
            raise IndexError("end (%d) is larger than the size of this BinnedBitSet (%d)" % (start + count, self.bb.size))
        # }
        binBitsSetRange(self.bb, start, count)

    def count_range(self, start, count):
        # bb_check_range_count(self, start, count)
        # {
        #   bb_check_index(self, start)
        #   {
        index = start
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bb.size:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bb.size))
        #   }
        if count < 0:
            raise IndexError("count (%d) must be non-negative" % count)
        if start + count > self.bb.size:
            raise IndexError("end (%d) is larger than the size of this BinnedBitSet (%d)" % (start + count, self.bb.size))
        # }
        return binBitsCountRange(self.bb, start, count)

    def next_set(self, start):
        # bb_check_start(self, start)
        # {
        #   bb_check_index(self, start)
        #   {
        index = start
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bb.size:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bb.size))
        #   }
        # }
        return binBitsFindSet(self.bb, start)

    def next_clear(self, start):
        # bb_check_start(self, start)
        # {
        #   bb_check_index(self, start)
        #   {
        index = start
        if index < 0:
            raise IndexError("BitSet index (%d) must be non-negative" % index)
        if index >= self.bb.size:
            raise IndexError("%d is larger than the size of this BitSet (%d)" % (index, self.bb.size))
        #   }
        # }
        return binBitsFindClear(self.bb, start)

    property size:
        def __get__(self):
            return self.bb.size

    property bin_size:
        def __get__(self):
            return self.bb.bin_size

    def iand(self, BinnedBitSet other):
        # bb_check_same_size(self, other)
        # {
        if self.bb.size != other.bb.size:
            raise ValueError("BitSets must have the same size")
        # }
        binBitsAnd(self.bb, other.bb)

    def ior(self, BinnedBitSet other):
        # bb_check_same_size(self, other)
        # {
        if self.bb.size != other.bb.size:
            raise ValueError("BitSets must have the same size")
        # }
        binBitsOr(self.bb, other.bb)

    def invert(self):
        binBitsNot(self.bb)
