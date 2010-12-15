#!/usr/bin/python
# Filename: gff_nonsynonymous_filter.py

"""
usage: %prog gff_file
"""

# Appnd amino_acid attribute to nonsynonymous mutations, and filter out synonymous
# and non-coding mutations
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import math, os, sys, subprocess
import MySQLdb, MySQLdb.cursors
import simplejson as json
from utils import gff, twobit
from utils.biopython_utils import reverse_complement, translate
from utils.codon_intersect import codon_intersect
from config import DB_HOST, GETEVIDENCE_USER, GETEVIDENCE_PASSWD, GETEVIDENCE_DATABASE, KNOWNGENE_SORTED
from codon import codon_123

query_gene = '''
SELECT genetests.testable,
genetests.reviewed
FROM genetests
WHERE (genetests.gene=%s)
'''

class transcript_file:
    def __init__(self, filename):
        self.f = open(filename)
        self.data = self.f.readline().split()
        self.transcripts = [ self.data ]

    def cover_next_position(self, position):
        if (self.data):
            # Indexes 2 and 4 are chromosome and transcript start position
            last_start_position = (self.transcripts[-1][2], int(self.transcripts[-1][4]))
        # Move ahead until empty or start of newest transcript is after given position
        while (self.data and self.comp_position(last_start_position, position) < 0):
            self.data = self.f.readline().split()
            if (self.data):
                self.transcripts.append(self.data)
                last_start_position = (self.transcripts[-1][2], int(self.transcripts[-1][4]))
        # return all transcripts covering or after this position
        return self._remove_uncovered_transcripts(position)

    def new_next_position(self, position):
        if (self.data):
            # Indexes 2 and 4 are chromosome and transcript start position
            last_start_position = (self.transcripts[-1][2], int(self.transcripts[-1][4]))
        new_transcripts = []
        # Move ahead until empty or start of newest transcript is after given position
        while (self.data and self.comp_position(last_start_position, position) < 0):
            self.data = self.f.readline().split()
            if (self.data):
                self.transcripts.append(self.data)
                new_transcripts.append(self.data)
                last_start_position = (self.transcripts[-1][2], int(self.transcripts[-1][4]))
        self._remove_uncovered_transcripts(position)
        return new_transcripts

    def _remove_uncovered_transcripts(self, position):
        covered_transcripts = []
        data_to_remove = []
        if self.transcripts:
            for data in self.transcripts:
                start_position = (data[2], int(data[4]))
                end_position = (data[2], int(data[5]))
                if (self.comp_position(position, end_position) <= 0):
                    if (self.comp_position(position, start_position) >= 0):
                        covered_transcripts.append(list(data))
                else:
                    # remove any with end before target
                    data_to_remove.append(data)
            for data in data_to_remove:
                self.transcripts.remove(data)
        return covered_transcripts

    def comp_position(self, position1, position2):
        # positions are tuples of chromosome (str) and position (int)
        if (position1[0] != position2[0]):
            return cmp(position1[0],position2[0])
        else:
            return cmp(position1[1],position2[1])

def get_coding(transcript):
    coding_start = int(transcript[6])
    coding_end = int(transcript[7])
    starts = [int(x) for x in transcript[9].strip(",").split(",")]
    ends = [int(x) for x in transcript[10].strip(",").split(",")]
    coding_regions = []
    for i in range(len(starts)):
        if ends[i] > coding_start and starts[i] < coding_end:
            start = starts[i]
            end = ends[i]
            if start < coding_start:
                start = coding_start
            if end > coding_end:
                end = coding_end
            start = start + 1  # going to compare with 1-based
            region = (transcript[0], transcript[2], start, end)
            coding_regions.append(region)
    return coding_regions

def remove_covered_regions(check_regions, record):
    new_check_regions = []
    for region in check_regions:
        if record.end < region[2] or record.start > region[3]:
            new_check_regions.append(region)
        else:       # some overlap exists
            if record.start <= region[2] and record.end >= region[3]:
                pass        # fully covered!
            elif record.start <= region[2]:
                remaining_region = (region[0], region[1], int(record.end + 1), region[3])
                new_check_regions.append(remaining_region)
            else:
                remaining_before = (region[0], region[1], region[2], int(record.start - 1))
                new_check_regions.append(remaining_before)
                if record.end < region[3]:
                    remaining_after = (region[0], region[1], int(record.end + 1), region[3])
                    new_check_regions.append(remaining_after)
    return new_check_regions

def remove_uncovered_regions(check_regions, record):
    uncovered_regions = []
    for region in check_regions:
        if region[3] < record.start:
            uncovered_regions.append(region)
    for region in uncovered_regions:
        check_regions.remove(region)
    return uncovered_regions


def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # first, try to connect to the databases
    try:
        connection = MySQLdb.connect(cursorclass=MySQLdb.cursors.DictCursor, host=DB_HOST, user=GETEVIDENCE_USER, passwd=GETEVIDENCE_PASSWD, db=GETEVIDENCE_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        sys.stderr.write ("Error %d while connecting to database: %s" % (message[0], message[1]))
        sys.exit()

    # make sure the required table is really there
    try:
        cursor.execute ('DESCRIBE snap_latest')
    except MySQLdb.Error:
        sys.stderr.write ("No 'snap_latest' table => empty output")
        sys.exit()


    # open gff file
    gff_file = gff.input(sys.argv[1])
    
    # set up transcript file input
    transcript_input = transcript_file(os.getenv('DATA') + "/" + KNOWNGENE_SORTED)

    # keep list of regions we're checking
    # each will be tuple of (info, chr, start, end)
    check_regions = []

    # check if reference coverage is reported
    cmd = "head -100 " + sys.argv[1] + " | grep REF"
    out = os.popen(cmd);

    same_gene = []
    if (out.readline()):
        for record in gff_file:
            if record.seqname.startswith("chr"):
                chromosome = record.seqname
            else:
                if record.seqname.startswith("Chr"):
                    chromosome = "chr" + record.seqname[3:]
                else:
                    chromosome = "chr" + record.seqname

            # print "record: "+ str(record) + str(record.start)
            # Move forward in transcripts until past record end
            record_end = (chromosome, record.end)
            new_transcripts = transcript_input.new_next_position(record_end)            
            for transcript in new_transcripts:
                # print "New transcript: " + str(transcript)
                new_regions = get_coding(transcript)
                check_regions = check_regions + new_regions

            if check_regions:
                # print "Check regions before: " + str(check_regions)
                check_regions = remove_covered_regions(check_regions, record)
                # print "After: " + str(check_regions)
                uncovered_regions = remove_uncovered_regions(check_regions, record)
                for region in uncovered_regions:
                    #print region[0] + "\t" + region[1] + "\t" + str(region[2]) + "\t" + str(region[3])
                    if (not same_gene) or same_gene[0][0] == region[0]:
                        same_gene.append(region)
                    else:
                        regions_in_gene = []
                        for region_in_gene in same_gene:
                            regions_in_gene.append(region[1] + ":" + str(region[2]) + "-" + str(region[3]))
                        region_data = { }
                        region_data["regions"] = ", ".join(regions_in_gene)
                        region_data["gene"] = same_gene[0][0]
                        
                        # check get-evidence if gene is in genetests...
                        cursor.execute(query_gene, (region_data["gene"]))
                        if cursor.rowcount > 0:
                            data = cursor.fetchall()
                            for key in data[0].keys():
                                region_data[key] = data[0][key]
                        print json.dumps(region_data)
                        same_gene = [ region ]
        regions_in_gene = []
        for region_in_gene in same_gene:
            regions_in_gene.append(region[1] + ":" + str(region[2]) + "-" + str(region[3]))
        region_data = { }
        region_data["regions"] = ", ".join(regions_in_gene)
        region_data["gene"] = same_gene[0][0]

        # check get-evidence if gene is in genetests...
        cursor.execute(query_gene, (region_data["gene"]))
        if cursor.rowcount > 0:
            data = cursor.fetchall()
            for key in data[0].keys():
                region_data[key] = data[0][key]
        print json.dumps(region_data)




if __name__ == "__main__":
    main()
