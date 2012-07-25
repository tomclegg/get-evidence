# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""Tracking and logging progress and reporting metadata during processing"""

import simplejson as json
import time

class Logger:

    """Write data to log file with time information."""

    def __init__(self, outfile):
        """Initialize Logger object

        Arguments:
        outputfile: file-like object to be written to.
        """
        self.outfile = outfile
        self.start_time = time.time()

    def put(self, msg):
        """Write line to log file along with time-since-creation."""
        self.outfile.write("%s @ %.2f s\n" %
                           (str(msg), time.time() - self.start_time))

class ProgressTracker:

    """Track progress and collect metadata during data processing.

    Public data attributes:
    self.log_handle: Required when object is instantiated, expected to be a 
                     file-like object to which log progress is written.
    self.map_range: Required when object is instantiated, expected to be a 
                    sequence (e.g. list or tuple) where the first two items
                    are numeric, representing a range onto which progress is 
                    mapped.
    self.metadata: dict in which metadata is stored.
    self.n_expected: integer or numeric representing how many total items 
                     are expected to be seen. 
    self.seen: dict that records when an item (used as key) has been seen.
    self.report_unknown: set to True unless 'expected' was passed to __init__ 
                         and was a list, tuple, or set.
    self.n_seen: integer counting how many items have been seen.
    """

    def __init__(self, log_handle, map_range, expected=False, metadata=dict()):
        """Initialize ProgressTracker object with output log and map range.

        Arguments:
        log_handle: file-like object to write progress updates to.
        map_range: list or tuple where [0] and [1] give a number range 
                   against which progress is mapped.
        expected: Optional argument. If provided, self.saw() can be used to 
                  track progress depending on what was passed. If a list, 
                  tuple, or set, each new item will be checked against it and 
                  matches are logged. Otherwise, it is assumed to be a number 
                  ('n_expected'); items are recorded and each new item 
                  increments a counter which is compared against 'n_expected'.
        metadata: Optional argument. If provided, expected to be a dict already 
                  populated with some metadata.
        """
        # Set up initialized data attributes
        self.log_handle = log_handle
        self.map_range = map_range
        self.metadata = metadata
        # Handle 'expected' argument, use it to set more data attributes.
        self.seen = {}
        try:
            self.n_expected = len(expected)
            for item in expected:
                self.seen[item] = False
            self.report_unknown = False
        except TypeError:
            self.n_expected = expected
            self.report_unknown = True
        self.n_seen = 0

    def saw(self, item):
        """Update tracker when seeing an new item

        Note: calling this method won't do anything unless an 'expected' 
        argument was provided when the object was initialized.
        """
        if self.report_unknown:
            is_new = item not in self.seen
        else:
            is_new = item in self.seen and not self.seen[item]
        if self.n_seen < self.n_expected and is_new:
            self.seen[item] = True
            self.n_seen += 1
            cur = self.map_range[0] + self.n_seen * \
                (self.map_range[1] - self.map_range[0]) / self.n_expected
            self.log_handle.write("#status %d\n" % cur)

    def write_metadata(self, output):
        """Write JSON-formatted metadata to file-like object"""
        output.write(json.dumps(self.metadata) + '\n')
