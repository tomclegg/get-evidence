#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

# A useful utility function for retrieving coding sequence fragments

def codon_intersect(start, end, exons, codon_position):
    """Returns a tuple to express the intervals in genomic sequence
    that contain the codons affected by changes between the start and
    end points supplied. The first item of the tuple is the starting
    exon; the second item is the ending exon; the last item is a list
    of intervals (each a tuple of start and end positions) with
    base counting as in Python slice notation (i.e. [a, b) if a and b
    are reckoned by 0-based counting)).
    
    Keyword arguments:
    start -- the start position, inclusive (0-based)
    end -- the end position, exclusive (0-based)
    exons -- a list of exon intervals, each a list or tuple of start
    and end positions with numbered reckoned as in Python slice
    notation
    codon_position -- 1, 2, or 3 for a + stranded cds, referring to
    whether the start position nucleotide is the 1st, 2nd, or 3rd base
    of a codon, respectively; or, -1, -2, -3 for a - stranded cds,
    referring to whether the nucleotide referenced by the "end"
    argument is the 1st, 2nd, or 3rd base of a codon
    
    >>> codon_intersect(12, 13, [[0, 13], [13, 20]], 3)
    (1, 1, [(10, 13)])
    >>> codon_intersect(12, 13, [[0, 13], [13, 20]], 2)
    (1, 2, [(11, 13), (13, 14)])
    """
    
    # basic sanity check
    if start >= end:
        #TODO: throw a specific error
        return
    
    # find our starting exon, and coding region start and end
    # start by working forward
    for i in range(0, len(exons)):
        # test for overlap, given by:
        # exon_end > start and end > exon_start
        if exons[i][1] > start and end > exons[i][0]:
            if codon_position > 0: exon_index = i
            if start < exons[i][0]: start = exons[i][0]
            break
    # do it again backwards (not the most efficient!)
    for i in reversed(range(0, len(exons))):
        if exons[i][1] > start and end > exons[i][0]:
            if codon_position < 0: exon_index = i
            if end > exons[i][1]: end = exons[i][1]
            break
    
    # initialize some variables we'll return
    coding_intervals = []
    coding_length = 0
    start_exon = None
    end_exon = None

    # now, let's get down to the meat of the calculation
    # for + stranded cds
    if codon_position > 0:
    
        # find the correct start position
        # this traverses the exons backwards starting at exon_index
        for i in reversed(range(0, exon_index + 1)):
        
            exon_start = exons[i][0]
            if i > 0: prev_exon_end = exons[i - 1][1]
            
            if start - (codon_position - 1) < exon_start:
                codon_position -= start - exon_start + 1
                start = prev_exon_end - 1
                # this will throw an error if there's no previous exon
            else:
                start -= codon_position - 1
                start_exon = i + 1 # 1-based
                break
        
        # we're in trouble if start_exon is None
        if start_exon is None:
            return
        
        # now find the correct regions to request
        codon_completion_length = None # variable for special case below
        
        for i in range(start_exon - 1, len(exons)):

            exon_end = exons[i][1]
            if i < len(exons) - 1: next_exon_start = exons[i + 1][0]
            
            if exon_end < end + (3 - (coding_length + end - start) % 3) % 3:
                # deal with special case if exon covers the end nucleotide but not the
                # additional bases required to complete the codon
                if codon_completion_length is not None:
                    codon_completion_length -= exon_end - start
                elif exon_end >= end:
                    codon_completion_length = (3 - (coding_length + end - start) % 3) % 3 \
                                              - (exon_end - end)

                coding_intervals.append((start, exon_end))
                coding_length += exon_end - start
                start = next_exon_start
            else:
                if codon_completion_length is not None:
                    coding_intervals.append((start, start + codon_completion_length))
                else:
                    coding_intervals.append((start,
                                             end + (3 - (coding_length + end - start) % 3) % 3))
                coding_length += (end + (3 - (coding_length + end - start) % 3) % 3) - start
                end_exon = i + 1
                break
        
        if end_exon is None:
            return
    
    # for - stranded cds
    else:
        # same idea, essentially, except we start with "end" and end with "start
        for i in range(exon_index, len(exons)):
            
            exon_end = exons[i][1]
            if i < len(exons) - 1: next_exon_start = exons[i + 1][0]
            
            if end - (codon_position + 1) > exon_end:
                codon_position += exon_end - end + 1
                end = next_exon_start + 1
            else:
                end -= codon_position + 1
                end_exon = i + 1
                break
            
        if end_exon is None:
            return
        
        codon_completion_length = None
        for i in reversed(range(0, end_exon)):

            exon_start = exons[i][0]
            if i > 0: prev_exon_end = exons[i - 1][1]
            
            if exon_start > start - (3 - (coding_length + end - start) % 3) % 3:

                if codon_completion_length is not None:
                    codon_completion_length -= end - exon_start
                elif exon_start <= start:
                    codon_completion_length = (3 - (coding_length + end - start) % 3) % 3 \
                                              - (start - exon_start)

                coding_intervals.append((exon_start, end))
                coding_length += end - exon_start
                end = prev_exon_end
            else:
                if codon_completion_length is not None:
                    coding_intervals.append((end - codon_completion_length, end))
                else:
                    coding_intervals.append((start - (3 - (coding_length + end - start) % 3) % 3,
                                             end))
                coding_length += end - (start - (3 - (coding_length + end - start) % 3) % 3)
                start_exon = i + 1
                break
        
        if start_exon is None:
            return
        
        # for the negative strand, we need to reverse the coding_intervals
        coding_intervals.reverse()

    # finally, return the stuff we want
    return start_exon, end_exon, coding_intervals
