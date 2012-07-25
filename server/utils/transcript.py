#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

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

    def cover_next_position(self, region):
        position_start = (region[0], region[1])
        position_end = (region[0], region[1])
        if (len(region) > 2):
            position_end = (region[0], region[2])
        if (self.data):
            last_start_position = (self.transcripts[-1].data["chr"], int(self.transcripts[-1].data["start"]))
        # Move ahead until empty or start of newest transcript is after given position
        while (self.data and self.comp_position(last_start_position, position_end) < 0):
            self.data = self.f.readline().split()
            if (self.data):
                self.transcripts.append(Transcript(self.data))
                last_start_position = (self.transcripts[-1].data["chr"], int(self.transcripts[-1].data["start"]))
        # return all transcripts removed in this step
        return self._remove_uncovered_transcripts(position_start)

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

