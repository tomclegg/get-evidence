#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

# A basic set of functionality for files that specify intervals

from warnings import warn
from bitset import *

class Interval(object):
    def __init__(self, chrom, start, end, strand=None, raw=None):
        self.chrom = chrom
        self.start = start
        self.end = end
        self.strand = strand
        self.raw = raw # raw stores the original, unparsed line for output
    
    def __str__(self):
        return self.raw
    
    @property
    def sort_key(self):
        """Returns a key useful for meaningful sorting, required for batched sorts."""
        return (self.chrom, self.start, self.end, self.strand)
    
    def __cmp__(self, other):
        try:
            return cmp(self.sort_key, other.sort_key)
        except AttributeError:
            return cmp(str(self), str(other))

def _interval_iterator(f):
    """
    Shallow parser that returns information in a tuple of (chrom, start, end, strand, line).
    Line is stripped of whitespace and (start, end) is presumed to be zero-based, half-open.
    Ignores empty lines and presumes strand is + unless explicitly set to -.
    """
    # columns for each of the fields we're interested in (0-based)
    chrom_col, start_col, end_col, strand_col = 0, 1, 2, 3
    for line in f:
        if line.startswith("#") or line.isspace():
            continue
        fields = line.split()
        chrom = fields[chrom_col]
        start, end = int(fields[start_col]) , int(fields[end_col])
        if start > end: warn("interval start after end")
        strand = "+"
        if len(fields) > strand_col:
            if fields[strand_col] == "-": strand = "-"
        yield Interval(chrom, start, end, strand, line.strip())

def _operate_basewise(operator, *operands):
    if len(operands) == 0:
        return {}
    
    try:
        bitsets = operands[0].binned_bitsets()
    # assume we have pre-made dictionaries of binned bitsets if we can't make them
    # for consistency, we clone these binned bitsets so as not to operate in place
    except AttributeError:
        bitsets = dict()
        for k, v in operands[0].iteritems():
            bitsets[k] = v.clone()

    for o in operands[1:]:
        try:
            other_bitsets = o.binned_bitsets()
        except AttributeError:
            other_bitsets = o
        
        if operator == "add":
            for chrom in other_bitsets:
                if chrom in bitsets:
                    bitsets[chrom].ior(other_bitsets[chrom])
                else:
                    bitsets[chrom] = other_bitsets[chrom].clone()
        elif operator == "exclude":
            # we add and intersect (it's recursive!)
            if bitsets is not None:
                add_result = _operate_basewise("add", bitsets, other_bitsets)
                intersect_result = _operate_basewise("intersect", bitsets, other_bitsets)
                bitsets = None # delete the last reference to these bitsets for now to save memory
            else:
                add_result = _operate_basewise("add", add_result, other_bitsets)
                intersect_result = _operate_basewise("intersect", intersect_result, other_bitsets)
        elif operator == "intersect":
            delete_list = []
            for chrom in bitsets:
                # the intersection is empty if others don't have that chrom
                # (we can't delete it from the dictionary, though, if we're
                # iterating through it, so we save it in a list for later)
                if chrom not in other_bitsets:
                    delete_list.append(chrom)
                    continue
                bitsets[chrom].iand(other_bitsets[chrom])
            # now we delete what we need to
            for chrom in delete_list:
                del bitsets[chrom]
        elif operator == "subtract":
            for chrom in bitsets:
                if chrom not in other_bitsets:
                    continue
                subtract_bits = other_bitsets[chrom]
                subtract_bits.invert()
                bitsets[chrom].iand(subtract_bits)
    
    # one last thing for the exclude operation
    if bitsets is None:
        bitsets = _operate_basewise("subtract", add_result, intersect_result)
    
    return bitsets

class IntervalFile(object):
    def __init__(self, src, length_src=[]):
        # try to open the file, in case we're given a path
        try:
            f = open(src)
        # if that doesn't work, treat the argument itself as a file
        except TypeError:
            f = src
        self.iterator = _interval_iterator(f)
        self.interval_iterator = _interval_iterator(f)
        self.file = f
        # use length_src to parse a dictionary of chromosome lengths
        try:
            length_f = open(length_src)
        except TypeError:
            length_f = length_src
        mapping = dict()
        for line in length_f:
            fields = line.split()
            mapping[fields[0]] = int(fields[1])
        self.chromosome_lengths = mapping
        # close length_f if we opened it
        if length_f != length_src:
            length_f.close()
    
    def __iter__(self):
        return self
    
    def next(self):
        return self.iterator.next()
    
    def __getitem__(self, key):
        key = key.strip()
        for interval in iter(self):
            if key == str(interval):
                return interval
        return None
    
    def close(self):
        assert (self.file is not None)
        self.file.close()
        self.file = None
    
    def add_basewise(self, *others):
        """
        Add all regions in all files. Returns a dictionary of binned bitsets.
        """
        if len(others) == 0:
            raise TypeError("add_basewise() requires at least one other file")
        return _operate_basewise("add", self, *others)
    
    def binned_bitsets(self, upstream_pad=0, downstream_pad=0):
        """
        Read an interval file into a dictionary of binned bitsets.
        """
        last_chrom = None
        last_bitset = None
        bitsets = dict()
        
        for interval in self.interval_iterator:
            chrom, start, end = interval.chrom, interval.start, interval.end
            if chrom != last_chrom:
                if chrom not in bitsets:
                    if chrom in self.chromosome_lengths:
                        size = self.chromosome_lengths[chrom]
                    else:
                        size = MAX
                    bitsets[chrom] = BinnedBitSet(size) 
                last_chrom = chrom
                last_bitset = bitsets[chrom]
            
            if upstream_pad: start = max(0, start - upstream_pad)
            if downstream_pad: end = min(size, end + downstream_pad)
            last_bitset.set_range(start, end - start)
        return bitsets
    
    def complement_basewise(self):
        """
        Complement the regions of an interval file; returns a dictionary of bitsets. Note that
        if chromosome lengths are not given, this function assumes each chromosome to be of the
        maximum length.
        """
        bitsets = self.binned_bitsets()
        for chrom in bitsets:
            bitsets[chrom].invert()
        return bitsets

    def coverage(self):
        """
        Calculate the number of bases covered by all intervals in a file (or any iterable
        containing interval records; bases covered by more than one interval are counted once).
        """
        bitsets = self.binned_bitsets()
        total = 0
        for chrom in bitsets:
            total += bitsets[chrom].count_range(0, bitsets[chrom].size)
        return total
    
    def exclude_basewise(self, *others):
        """
        Find regions in any file or dictionary of binned bitsets that are not shared with
        any other file or dictionary of binned bitsets, using a base-by-base comparison.
        Returns a dictionary of binned bitsets.
        """
        if len(others) == 0:
            raise TypeError("exclude_basewise() requires at least one other file")
        return _operate_basewise("exclude", self, *others)
    
    def filter(self, func):
        """
        Filter using a given function. This function parses records completely using the
        built-in iterator for the class
        """
        # evaluate the expression, if we can, in case it's a string or code object
        try:
            func = eval(func, {})
        except TypeError:
            pass
        # now iterate
        for record in iter(self):
            if bool(func(record)):
                yield str(record)

    def intersect(self, *others, **options):
        """
        Find regions in the file that intersect regions in all other interval files or
        dictionaries of binned bitsets. The output preserves all fields from the current
        file. Optional keyword arguments are as follows.
    
        - 'min_overlap' is the minimum number of bases of overlap required (default: 1)
        """
        # default options
        min_overlap = 1
        if options is not None:
            if "min_overlap" in options: min_overlap = options["min_overlap"]
    
        if len(others) == 0:
            raise TypeError("intersect() requires at least one other file")
        bitsets = _operate_basewise("intersect", *others)
        for interval in self.interval_iterator:
            chrom, start, end = interval.chrom, interval.start, interval.end
            if chrom in bitsets and bitsets[chrom].count_range(start, end - start) >= min_overlap:
                yield str(interval)

    def intersect_basewise(self, *others):
        """
        Find regions in the file that intersect regions in all other interval files or
        dictionaries of binned bitsets, using a base-by-base comparison. Returns a
        dictionary of binned bitsets.
        """
        if len(others) == 0:
            raise TypeError("intersect_basewise() requires at least one other file")
        return _operate_basewise("intersect", self, *others)

    def sort(self, key=None, buffer_size=16384):
        """
        Sort an interval file, optionally by the key provided; this function parses
        records completely using the built-in iterator for the class, so lines can be
        sorted based on any of its attributes.
        """
        if key is None:
            key = lambda obj : obj.sort_key
        else:
            # evaluate the expression if we can, in case it's a string or code object
            try:
                key = eval(key, {})
            except TypeError:
                pass
        records = [(key(r), str(r)) for r in iter(self)]
        records.sort()
        for k, v in records:
            yield v

    def subtract(self, *others, **options):
        """
        Find regions in the file that do not intersect any region in any other interval file.
        The output preserves all fields from the current file. Optional keyword arguments are
        as follows.
    
        - 'max_overlap' is the maximum number of bases of overlap tolerated before a region
          is subtracted (default: 0)
        """
        # default options
        max_overlap = 0
        if options is not None:
            if "max_overlap" in options: max_overlap = options["max_overlap"]
    
        if len(others) == 0:
            raise TypeError("subtract() requires at least one other file")
        bitsets = _operate_basewise("add", *others)
        for interval in self.interval_iterator:
            chrom, start, end = interval.chrom, interval.start, interval.end
            if chrom in bitsets and bitsets[chrom].count_range(start, end - start) > max_overlap:
                continue
            yield str(interval)

    def subtract_basewise(self, *others):
        """
        Find regions in the file that do not intersect any region in any other interval file,
        using a base-by-base comparison. Returns a dictionary of binned bitsets.
        """
        if len(others) == 0:
            raise TypeError("subtract_basewise() requires at least one other file")
        return _operate_basewise("subtract", self, *others)
