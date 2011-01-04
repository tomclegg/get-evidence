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

class Transcript:
    def __init__(self, transcript_data, column_specs = {}):
        self.data = {}
        self.__init_string_data(transcript_data, column_specs)
        self.__init_int_data(transcript_data, column_specs)
        self.__init_int_array_data(transcript_data, column_specs)
        self.__get_coding_regions()

    def __init_string_data(self, transcript_data, column_specs):
        string_col_names = ("name", "ID", "chr", "strand")
        string_default_cols = (0, 1, 2, 3)
        for i in range(len(string_col_names)):
            key = string_col_names[i]
            if key in column_specs:
                self.data[key] = transcript_data[col_specs[key]]
            else:
                self.data[key] = transcript_data[string_default_cols[i]]

    def __init_int_data(self, transcript_data, column_specs):
        int_col_names = ("start", "end", "coding_start", "coding_end", "num_exons")
        int_default_cols = (4, 5, 6, 7, 8)
        for i in range(len(int_col_names)):
            key = int_col_names[i]
            if key in column_specs:
                self.data[key] = int(transcript_data[col_specs[key]])
            else:
                self.data[key] = int(transcript_data[int_default_cols[i]])

    def __init_int_array_data(self, transcript_data, column_specs):
        int_array_col_names = ("exon_starts", "exon_ends")
        int_array_default_cols = (9, 10)
        for i in range(len(int_array_col_names)):
            key = int_array_col_names[i]
            if key in column_specs:
                self.data[key] = int(transcript_data[col_specs[key]])
            else:
                self.data[key] = [int(x) for x in transcript_data[int_array_default_cols[i]].strip(",").split(",")]

    def __get_coding_regions(self):
        coding_starts = []
        coding_ends = []
        for i in range(len(self.data["exon_starts"])):
            if self.data["exon_ends"][i] > self.data["coding_start"] and self.data["exon_starts"][i] < self.data["coding_end"]:
                start = max(self.data["exon_starts"][i], self.data["coding_start"])
                end = min(self.data["exon_ends"][i], self.data["coding_end"])
                coding_starts.append(start)
                coding_ends.append(end)
        self.data["coding_starts"] = coding_starts
        self.data["coding_ends"] = coding_ends

    def get_coding_length(self):
        length = 0
        for i in range(len(self.data["coding_starts"])):
            length += self.data["coding_ends"][i] - self.data["coding_starts"][i]
        return length

class Transcript_file:
    def __init__(self, filename):
        self.f = open(filename)
        self.data = self.f.readline().split()
        self.transcripts = [ Transcript(self.data) ]

    def cover_next_position(self, position):
        if (self.data):
            last_start_position = (self.transcripts[-1].data["chr"], int(self.transcripts[-1].data["start"]))
        # Move ahead until empty or start of newest transcript is after given position
        while (self.data and self.comp_position(last_start_position, position) < 0):
            self.data = self.f.readline().split()
            if (self.data):
                self.transcripts.append(Transcript(self.data))
                last_start_position = (self.transcripts[-1].data["chr"], int(self.transcripts[-1].data["start"]))
        # return all transcripts removed in this step
        return self._remove_uncovered_transcripts(position)

    def _remove_uncovered_transcripts(self, position):
        covered_transcripts = []
        ts_to_remove = []
        if self.transcripts:
            for ts in self.transcripts:
                start_position = (ts.data["chr"], ts.data["start"])
                end_position = (ts.data["chr"], ts.data["end"])
                if (self.comp_position(position, end_position) <= 0):
                    if (self.comp_position(position, start_position) >= 0):
                        covered_transcripts.append(ts)
                else:
                    # remove any with end before target
                    ts_to_remove.append(ts)
            for ts in ts_to_remove:
                self.transcripts.remove(ts)
        return ts_to_remove

    def comp_position(self, position1, position2):
        # positions are tuples of chromosome (str) and position (int)
        if (position1[0] != position2[0]):
            return cmp(position1[0],position2[0])
        else:
            return cmp(position1[1],position2[1])

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
    transcript_input = Transcript_file(os.getenv('DATA') + "/" + KNOWNGENE_SORTED)

    # check if reference coverage is reported
    cmd = "head -100 " + sys.argv[1] + " | perl -ne '@data=split(\"\\t\"); if ($data[2] eq \"REF\") { print; }'"
    out = os.popen(cmd);

    # if we got any lines in the first 100 with "REF" in 
    # the third column, we assume coverage data exists
    if (out.readline()):
        # Store regions being examined, remove or reduce if covered
        # key: Transcript object
        # value: list of tuples (chr (string), start (int), end (int))
        # Note: Start is 1-based, not 0-based as is in transcript files
        examined_regions = {}
        for record in gff_file:
            if record.seqname.startswith("chr"):
                chromosome = record.seqname
            else:
                if record.seqname.startswith("Chr"):
                    chromosome = "chr" + record.seqname[3:]
                else:
                    chromosome = "chr" + record.seqname

            # Move forward in transcripts until past record end
            record_end = (chromosome, record.end)
            removed_transcripts = transcript_input.cover_next_position(record_end)

            for ts in transcript_input.transcripts:

                # Add to examined_regions if new
                if (not ts in examined_regions):
                    regions = [] 
                    for i in range(len(ts.data["coding_starts"])):
                        regions.append( (ts.data["chr"], (ts.data["coding_starts"][i] + 1), ts.data["coding_ends"][i]) )
                    examined_regions[ts] = regions

                # examine regions and remove any covered by the record
                updated_regions = []
                for region in examined_regions[ts]:
                    # if overlaps...
                    if (chromosome == region[0] and record.start <= region[2] and record.end >= region[1]):
                        if record.start <= region[1]:
                            if record.end >= region[2]:
                                pass    # full overlap!
                            else:
                                remaining_region = (region[0], record.end + 1, region[2])
                                updated_regions.append(remaining_region)
                        elif record.end >= region[2]:
                            remaining_region = (region[0], region[1], record.start - 1)
                            updated_regions.append(remaining_region)
                        else:
                            remaining_before = (region[0], region[1], record.start - 1)
                            remaining_after = (region[0], record.end + 1, region[2])
                            updated_regions.extend([remaining_before, remaining_after])
                    else:
                        updated_regions.append(region)
                examined_regions[ts] = updated_regions

            for ts in removed_transcripts:
                gene_data = {}
                gene_data["gene"] = ts.data["name"]
                gene_data["length"] = ts.get_coding_length()
                total_uncovered = 0
                missing_regions = []
                if ts in examined_regions and examined_regions[ts]:
                    for region in examined_regions[ts]:
                        missing_regions.append(region[0] + ":" + str(region[1]) + "-" + str(region[2]))
                        total_uncovered += region[2] - (region[1] - 1)
                gene_data["missing"] = total_uncovered
                gene_data["missing_regions"] = ", ".join(missing_regions)
                cursor.execute(query_gene, (gene_data["gene"]))
                if cursor.rowcount > 0:
                    data = cursor.fetchall()
                    for key in data[0].keys():
                        gene_data[key] = data[0][key]
                print json.dumps(gene_data)

        # clean up remaining transcripts (if any)
        for ts in transcript_input.transcripts:
            gene_data = {}
            gene_data["gene"] = ts.data["name"]
            gene_data["length"] = ts.get_coding_length()
            total_uncovered = 0
            missing_regions = []
            if ts in examined_regions and examined_regions[ts]:
                for region in examined_regions[ts]:
                    missing_regions.append(region[0] + ":" + str(region[1]) + "-" + str(region[2]))
                    total_uncovered += region[2] - (region[1] - 1)
            gene_data["missing"] = total_uncovered
            gene_data["missing_regions"] = ", ".join(missing_regions)
            cursor.execute(query_gene, (gene_data["gene"]))
            if cursor.rowcount > 0:
                data = cursor.fetchall()
                for key in data[0].keys():
                    gene_data[key] = data[0][key]
            print json.dumps(gene_data)



if __name__ == "__main__":
    main()
