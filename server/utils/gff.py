#!/usr/bin/python
# Filename: gff.py

# A GFF file parser
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

from warnings import warn
from intervals import Interval, IntervalFile

# for maximum compatibility, we assume GFF files are version 2 unless otherwise specified
DEFAULT_GFF_VERSION = 2

class GFFRecord(Interval):
    def __init__(self, seqname, source, feature, start, end,
                 score, strand, frame, attributes=None, comments=None,
                 version=DEFAULT_GFF_VERSION):
        self.seqname = seqname
        self.source = source
        self.feature = feature
        self.start = start
        self.end = end
        self.score = score
        self.strand = strand
        self.frame = frame
        self.attributes = attributes
        self.comments = comments
        self.version = version
        if version >= 3:
            try:
                self.id = attributes["ID"]
            except KeyError:
                pass
    
    def __str__(self):
        if self.attributes is None:
            attributes_string = ""
        elif self.version >= 3:
            attributes_string = ";".join(["=".join([k, v]) for k, v in sorted(self.attributes.items())])
        elif self.version == 2:
            attributes_string = ";".join([" ".join([k, v]) for k, v in sorted(self.attributes.items())])
        else:
            attributes_string = self.attributes
        
        if self.comments is None:
            comments_string = ""
        else:
            comments_string = self.comments
        
        s = "%s\t%s\t%s\t%d\t%d\t%s\t%s\t%s\t%s\t%s" % (self.seqname,
            self.source, self.feature, self.start, self.end, self.score,
            self.strand, self.frame, attributes_string, comments_string)
        return s.rstrip("\t")
    
    @property
    def sort_key(self):
        """Returns a key useful for meaningful sorting, required for batched sorts."""
        return (self.seqname, self.start, self.end, self.strand, self.feature)

def _gff_iterator(f, version=DEFAULT_GFF_VERSION):
    """
    Deep parser that returns information in GFFRecord format; faithful to the textual
    representation, (start, end) is one-based and inclusive.
    """
    linenumber = 0
    for line in f:
        linenumber += 1

        # parse comments
        if line.startswith('#'):
            # do nothing unless it's meta
            if not line.startswith('##'):
                continue
            
            # process meta comments
            line = line.strip()
            
            # gff-version
            if line.startswith("##gff-version"):
                try:
                    v = int(line.rpartition(' ')[2])
                except ValueError:
                    raise Exception("file version invalid")
                # allow meta comment to override specified version
                version = v
                # we don't want to interpret future versions incorrectly
                if version > 3:
                    raise Exception("file version unsupported")
            
            # RNA or Protein
            elif line.startswith("##RNA") or line.startswith("##Protein"):
                raise Exception("RNA or protein files not supported") # we only do DNA
            
            # whatever else we've done with the meta comment
            # we're now done with the line--don't try to parse it
            continue
        
        # start parsing the line
        l = line.strip().split("\t")
        # we stop altogether if there's embedded fasta
        if version >= 3 and l[0].startswith('>'):
            break
        # otherwise, raise an exception if we don't have the minimum number of fields
        if len(l) < 8:
            raise Exception("insufficient fields at line %d" % linenumber)
        
        # sanity check on start and end
        start = long(l[3])
        end = long(l[4])
        if end < start:
            raise Exception("end before start (%s,%s) at line %d" % (start, end, linenumber))
            
        # convert score to float
        score = l[5]
        if score != '.':
            score = float(score)
        
        # convert frame to int
        frame = l[7]
        if frame != '.':
            frame = int(frame)
        
        # parse attributes (shallow, for speed reasons)
        if len(l) < 9:
            attributes = None
        elif version >= 3:
            attributes = dict(attr.strip().split('=', 1) for attr in l[8].strip(";").split(';'))
        elif version == 2:
            attributes = dict(attr.strip().split(' ', 1) for attr in l[8].strip(";").split(';'))
        else:
            attributes = l[8]
        
        # parse comments, if they exist
        if len(l) < 10:
            comments = None
        else:
            comments = [c.lstrip('#').strip() for c in l[9:]]
        
        # note how we don't do any processing on seqname, source, feature, or strand
        yield GFFRecord(l[0], l[1], l[2], start, end,
                        score, l[6], frame, attributes, comments, version)

def _gff_interval_iterator(f):
    """
    Shallow parser that returns information in Interval records.
    Line is stripped of whitespace and (start, end) is zero-based, half-open for more
    standardized processing in Python. Ignores empty lines and presumes strand is + unless
    explicitly set to -.
    """
    # columns for each of the fields we're interested in (0-based)
    chrom_col, start_col, end_col, strand_col = 0, 3, 4, 6
    for line in f:
        if line.startswith("#") or line.isspace():
            continue

        fields = line.split()

        chrom = fields[chrom_col]

        # in GFF, numbering is 1-based, but in Python, we use 0-based, half-open,
        # so subtract 1 from the value given in the start column
        start, end = int(fields[start_col]) - 1, int(fields[end_col])
        if start > end: warn("interval start after end")

        strand = "+"
        if len(fields) > strand_col:
            if fields[strand_col] == "-": strand = "-"
        
        yield Interval(chrom, start, end, strand, line.strip())

class GFFFile(IntervalFile):
    def __init__(self, src, length_src=[], version=DEFAULT_GFF_VERSION):
        # call the superclass
        IntervalFile.__init__(self, src, length_src)
        # set our deep and shallow iterators
        # (self.file is determined automatically by the superclass from the src argument)
        self.iterator = _gff_iterator(self.file, version)
        self.interval_iterator = _gff_interval_iterator(self.file)
    
    def __getitem__(self, key):
        key = key.strip()
        for record in iter(self):
            if hasattr(record, "id") and key == record.id:
                return record
        return None

def input(src, version=DEFAULT_GFF_VERSION):
    return GFFFile(src, version=version)
