#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

# A BED file parser

from warnings import warn
from intervals import Interval, IntervalFile

class BEDRecord(Interval):
    def __init__(self, chrom, chromStart, chromEnd, name=None, score=None,
                 strand=None, thickStart=None, thickEnd=None, itemRgb=None,
                 blockCount=None, blockSizes=None, blockStarts=None):
        self.chrom = chrom
        self.chromStart = chromStart
        self.chromEnd = chromEnd
        self.name = name
        self.score = score
        self.strand = strand
        self.thickStart = thickStart
        self.thickEnd = thickEnd
        self.itemRgb = itemRgb
        self.blockCount = blockCount
        self.blockSizes = blockSizes
        self.blockStarts = blockStarts
    
    def __str__(self):
        name_string = score_string = strand_string = ""
        thickStart_string = thickEnd_string = itemRgb_string = ""
        blockCount_string = blockSizes_string = blockStarts_string = ""

        # you can only have subsequent columns if you have previous ones
        if self.name is not None:
            name_string = self.name
            if self.score is not None:
                score_string = str(self.score)
                if self.strand is not None:
                    strand_string = self.strand
                    if self.thickStart is not None and self.thickEnd is not None:
                        thickStart_string = str(self.thickStart)
                        thickEnd_string = str(self.thickEnd)
                        if self.itemRgb is not None:
                            itemRgb_string = "(%s)" % ",".join(str(self.itemRgb))
                            if self.blockCount is not None:
                                blockCount_string = str(self.blockCount)
                                blockSizes_string = ",".join(str(self.blockSizes))
                                blockStarts_string = ",".join(str(self.blockStarts))
        
        s = "%s\t%d\t%d\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s" % (self.chrom,
            self.chromStart, self.chromEnd, name_string, score_string, strand_string,
            thickStart_string, thickEnd_string, itemRgb_string, blockCount_string,
            blockSizes_string, blockStarts_string)
        return s.rstrip("\t")
    
    @property
    def sort_key(self):
        """Returns a key useful for meaningful sorting, required for batched sorts."""
        return (self.chrom, self.chromStart, self.chromEnd, self.strand, self.name)

def _bed_iterator(f):
    # start reading line by line
    header_allowed = True
    for line in f:
        # if we have a comment, then move on
        if line.startswith('#'):
            continue
        
        # start parsing the line
        l = line.strip().split("\t")
        if header_allowed and len(l) == 1:
            continue # the iterator does not return header contents or do anything with them
        elif header_allowed:
            header_allowed = False
        if len(l) < 3:
            raise Exception("insufficient fields")
        
        # sanity check on start and end
        chromStart = long(l[1])
        chromEnd = long(l[2])
        if chromEnd <= chromStart:
            raise Exception("chromosome end before start (%s,%s)" % (chromStart, chromEnd))
        
        # name
        if len(l) >= 4:
            name = l[3]
        else:
            name = None
        
        # convert score to float
        if len(l) >= 5:
            score = int(l[4])
            if score < 0 or score > 1000:
                raise Exception("score not a valid integer between 0 and 1000")
        
        # strand
        if len(l) >= 6:
            strand = l[5]
        else:
            strand = None
        
        # thickStart, thickEnd (note that they are parsed together)
        if len(l) >= 8:
            thickStart, thickEnd = long(l[6]), long(l[7])
            # this test is an inequality because thick regions of len 0 are used to indicate
            # no thick drawing at all
            if thickEnd < thickStart:
                raise Exception("thick end before start (%s,%s)" % (thickStart, thickEnd))
        else:
            thickStart, thickEnd = None, None
        
        # itemRgb
        if len(l) >= 9:
            itemRgb = tuple(map(int, l[8].strip("()").split(",")))
        else:
            itemRgb = None
        
        # blockCount, blockSizes, blockStarts (note that it makes no sense to have
        # just the count, or just the count and sizes, so they are parsed together)
        if len(l) >= 12:
            blockCount = int(l[9])
            blockSizes = map(long, l[10].strip(",").split(","))
            blockStarts = map(long, l[11].strip(",").split(","))
            if blockCount != len(blockSizes) or len(blockSizes) != len(blockStarts):
                raise Exception("number of blocks given does not match number of blocks indicated")
        else:
            blockCount, blockSizes, blockStarts = None, None, None
        
        # note how we don't do any processing, essentially, on chrom, name, or strand
        yield BEDRecord(l[0], chromStart, chromEnd, name, score, 
                        strand, thickStart, thickEnd, itemRgb, blockCount, blockSizes, blockStarts)

def _bed_interval_iterator(f):
    """
    Shallow parser that returns information in Interval records.
    Line is stripped of whitespace and (start, end) is zero-based, half-open. Ignores empty
    lines and presumes strand is + unless explicitly set to -.
    """
    # columns for each of the fields we're interested in (0-based)
    chrom_col, start_col, end_col, strand_col = 0, 1, 2, 5
    # in BED format, the initial lines may be a header, but without comment hashes (annoying!)
    header_allowed = True
    for line in f:
        if line.startswith("#") or line.isspace():
            continue

        fields = line.split()
        if header_allowed and len(fields) == 1:
            continue # the iterator does not return header contents or do anything with them
        elif header_allowed:
            header_allowed = False

        chrom = fields[chrom_col]

        start, end = int(fields[start_col]), int(fields[end_col])
        if start > end: warn("interval start after end")

        strand = "+"
        if len(fields) > strand_col:
            if fields[strand_col] == "-": strand = "-"
        
        yield Interval(chrom, start, end, strand, line.strip())

class BEDFile(IntervalFile):
    def __init__(self, src):
        # call the superclass
        IntervalFile.__init__(self, src, length_src)
        # set our deep and shallow iterators
        # (self.file is determined automatically by the superclass from the src argument)
        self.iterator = _bed_iterator(self.file)
        self.interval_iterator = _bed_interval_iterator(self.file)
    
    def __getitem__(self, key):
        key = key.strip()
        for record in iter(self):
            r_id = "%s:%s..%s %s" % (record.chrom, record.chromStart, record.chromEnd, record.name)
            if key == r_id:
                return record
        return None

def input(src):
    return BEDFile(src)
