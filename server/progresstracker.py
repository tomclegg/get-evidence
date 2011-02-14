class ProgressTracker:
    def __init__(self, log_handle, range, expected=False):
        self.range = range
        self.seen = {}
        if type(expected) == type([]):
            self.n_expected = len(expected)
            for x in expected:
                self.seen[x] = 0
            self.report_unknown = False
        else:
            self.n_expected = expected
            self.report_unknown = True
        self.n_seen = 0
        self.log_handle = log_handle
    def saw(self, x):
        if (self.n_seen < self.n_expected and
            ((x not in self.seen) if self.report_unknown
             else (x in self.seen and self.seen[x] == 0))):
            self.seen[x] = 1
            self.n_seen += 1
            cur = self.range[0] + self.n_seen * (self.range[1] - self.range[0]) / self.n_expected
            self.log_handle.write("#status %d\n" % cur)
