#!/usr/bin/python
# Filename: gff_concordancy.py

"""
usage: %prog gff_files_1 gff_files_2 [options]
  -e, --enumerate: output each concordant and discordant SNP (twice)
  -r, --read-depth: output mean and median read depth information
  -v, --verbose: output additional information
"""

# Output concordancy in tabular format for each pair of files
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import glob, math, os, sys
from tempfile import TemporaryFile
from utils import doc_optparse, gff

# for output purposes, column headings will be alphanumeric
def excel_column(n):
    alphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
    col = ""
    while n > 0:
        digit = n % 26
        if digit == 0:
            digit = 26
        n = (n - digit) / 26
        col = alphabet[digit - 1] + col
    return col

# based on <http://mail.python.org/pipermail/python-list/2004-December/294990.html>
def mean(numbers):
    try:
        return sum(numbers) / float(len(numbers))
    except ZeroDivisionError:
        return None

def median(numbers):
    s = sorted(numbers)
    l = len(s)
    # odd number
    try:
        if l & 1:
            return s[l // 2]
        else:
            return (s[l // 2 - 1] + s[l // 2]) / float(2)
    except IndexError:
        return None 

def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)
    
    if len(args) < 2:
        doc_optparse.exit()
    
    gff_files_1 = glob.glob(args[0])
    gff_files_2 = glob.glob(args[1])
    
    # create temporary files to store intersections
    temp_file_1 = TemporaryFile()
    temp_file_2 = TemporaryFile()
    
    if not option.enumerate:
        # use a wider column if we're going to need it
        if option.read_depth:
            col_width = 24
        elif option.verbose:
            col_width = 16
        else:
            col_width = 8
        
        # print column headings
        print " " * 8,
        for i in range(1, len(gff_files_1) + 1):
            print excel_column(i).ljust(col_width),
        print ""
    
    # initialize counter to print row headings
    file_number = 0
    
    # iterate through the second list of files
    for g2_path in gff_files_2:
        
        # print row heading
        if not option.enumerate:
            file_number += 1
            print str(file_number).ljust(8),
        
        # now iterate through the first list, do intersections and compare
        for g1_path in gff_files_1:
            
            # do the intersection one way
            g1 = gff.input(g1_path)
            g2 = gff.input(g2_path)
            for line in g1.intersect(g2):
                print >> temp_file_1, line
            
            # now do the intersection the other way
            g1_reverse = gff.input(g1_path)
            g2_reverse = gff.input(g2_path)
            for line in g2_reverse.intersect(g1_reverse):
                print >> temp_file_2, line
            
            # rewind each temporary file now storing intersection data
            temp_file_1.seek(0)
            temp_file_2.seek(0)
            
            # now go through the temporary files and work out concordancy
            g1_intx = gff.input(temp_file_1)
            g2_intx = gff.input(temp_file_2)
            matching_count = unmatching_count = 0
            # we cannot chain equal signs here, because the two would reference the
            # same list, and that would be bad...
            matching_read_depths, unmatching_read_depths = [], []
            
            for record1 in g1_intx:
                record2 = g2_intx.next()
                
                # these records should match in terms of the interval they represent
                if record2.seqname != record1.seqname or \
                  record2.start != record1.start or \
                  record2.end != record1.end:
                      raise ValueError("files must be pre-sorted")
                
                # isolate the read depth info if we need to
                if option.read_depth:
                    rd = []
                    try:
                        rd.append(int(record1.attributes["read_depth"].strip("\"")))
                    except KeyError:
                        pass
                    try:
                        rd.append(int(record2.attributes["read_depth"].strip("\"")))
                    except KeyError:
                        pass
                
                # now test if there's concordance
                try:
                    if sorted(record2.attributes["alleles"].strip("\"").split("/")) != \
                      sorted(record1.attributes["alleles"].strip("\"").split("/")):
                        unmatching_count += 1
                        if option.enumerate:
                            record1.attributes["concordant"] = "false"
                            record2.attributes["concordant"] = "false"
                            print record1
                            print record2
                        if option.read_depth:
                            unmatching_read_depths.extend(rd)
                    else:
                        matching_count += 1
                        if option.enumerate:
                            record1.attributes["concordant"] = "true"
                            record2.attributes["concordant"] = "true"
                            print record1
                            print record2
                        if option.read_depth:
                            matching_read_depths.extend(rd)
                # no alleles? not a SNP
                except KeyError:
                    continue
            
            # now we print the result, being mindful of possible zero division problems, etc.
            if option.enumerate:
                pass
            elif option.read_depth:
                try:
                    a = "%.1f" % mean(matching_read_depths)
                    b = "%.1f" % median(matching_read_depths)
                except TypeError:
                    a = "--"
                    b = "--"
                try:
                    c = "%.1f" % mean(unmatching_read_depths)
                    d = "%.1f" % median(unmatching_read_depths)
                except TypeError:
                    c = "--"
                    d = "--"
                print ("%s %s : %s %s" % (a, b, c, d)).ljust(col_width),
            else:
                try:
                    p = "%.1f%%" % (float(matching_count) / (matching_count + unmatching_count) * 100)
                except ZeroDivisionError:
                    p = "--"
                if option.verbose:
                    total_count = unmatching_count + matching_count
                    print ("%s %s/%s" % (p, matching_count, total_count)).ljust(col_width),
                else:
                    print p.ljust(col_width),
            
            # now we rewind, delete everything, and start again!
            temp_file_1.seek(0)
            temp_file_1.truncate()
            temp_file_2.seek(0)
            temp_file_2.truncate()
        
        # wrap up the line
        print ""
    
    # print the legend describing what the column and row headings mean
    if not option.enumerate:
        print "-" * 8
        file_number = 0
        for i in gff_files_1:
            file_number += 1
            print ("[%s]" % excel_column(file_number)).ljust(8),
            print i
        file_number = 0
        for i in gff_files_2:
            file_number += 1
            print ("[%s]" % file_number).ljust(8),
            print i

if __name__ == "__main__":
    main()
