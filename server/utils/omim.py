#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

# A prototype OMIM parser

class OMIMRecord(object):
    def __init__(self, d):
        self.number = d.get("no")
        self.title = d.get("ti")
        self.text = d.get("tx")
        self.allelic_variants = d.get("av")
        self.see_also = d.get("sa")
        self.references = d.get("rf")
        self.clinical_synopsis = d.get("cs")
        self.contributors = d.get("cn")
        self.creation_date = d.get("cd")
        self.edit_history = d.get("ed")
    def __str__(self):
        return self.title[0]

class OMIMAllelicVariant(object):
    def __init__(self, d):
        self.number = d.get("number")
        self.title = d.get("title")
        self.gene = d.get("gene")
        self.mutation = d.get("mutation")
        self.text = d.get("text")
    def __str__(self):
        return self.title[0]

def _omim_number(f, d):
    d["no"] = f.readline().strip()

def _omim_title(f, d):
    titles = []
    while True:
        # peek ahead two characters
        # (our normal peeking procedure doesn't work
        # because some titles really begin with "*")
        test = f.read(2)
        # move back
        f.seek(-2, 1)
        # quit if we're done
        if test == "*F" or test == "*R":
            break
        # otherwise, we're OK
        titles.append(f.readline().strip())
    # join the individual lines, then split by ";;" and strip whitespace
    d["ti"] = [t.strip() for t in (" ".join(titles)).split(";;")]

def _omim_text(f, d):
    text = []
    # peek ahead one character
    while f.read(1) != "*":
        para = []
        # move back
        f.seek(-1, 1)
        while True:
            t = f.readline().strip()
            if t:
                para.append(t)
            else:
                # this has the effect of swallowing the empty line
                break
        text.append(" ".join(para))
    # now that we're done, move back
    f.seek(-1, 1)
    d["tx"] = "\n\n".join(text)

def _omim_allelic_variants(f, d):
    variants = []
    # peek ahead one character
    while f.read(1) != "*":
        variant_dictionary = {}
        # move back
        f.seek(-1, 1)

        # read the variant number
        variant_dictionary['number'] = f.readline().strip()

        # read the title(s)
        title = [f.readline().strip()]
        
        # handle the special case where the variants have been moved or removed
        if title[0].startswith("MOVED TO") or title[0].startswith("REMOVED FROM"):
            # we're done with this variant and not storing it
            continue
        
        # otherwise, continue reading the title(s)
        alt_titles = []
        while True:
            line = f.readline().strip()
            if line:
                alt_titles.append(line)
            else:
                # this has the effect of swallowing the empty line
                break

        # the last "title" is in fact the gene info
        try:
            gene_mutation = alt_titles.pop()
        except IndexError:
            # shouldn't happen, but it does! ugh.
            gene_mutation = None
        try:
            variant_dictionary['gene'], variant_dictionary['mutation'] = \
                gene_mutation.split(", ", 1)
        except (AttributeError, ValueError):
            # shouldn't happen, but it also does! double ugh.
            variant_dictionary['gene'] = gene_mutation
            
        # append the alternate titles
        title.extend([t.strip() for t in (" ".join(alt_titles)).split(";;")])
        variant_dictionary['title'] = title
        
        # read the text
        text = []
        while True:
            # first peek ahead one character
            test = f.read(1)
            # move back
            f.seek(-1, 1)
            # quit if we're done
            if test == "." or test == "*":
                break
            # otherwise, build the next paragraph
            para = []
            while True:
                t = f.readline().strip()
                if t:
                    para.append(t)
                else:
                    # this has the effect of swallowing the empty line
                    break
            text.append(" ".join(para))
        variant_dictionary['text'] = "\n\n".join(text)
        
        # we're almost done!
        variants.append(OMIMAllelicVariant(variant_dictionary))
        
    # now that we're done, move back
    f.seek(-1, 1)
    d["av"] = variants

def _omim_see_also(f, d):
    refs = []
    # peek ahead one character
    while f.read(1) != "*":
        # move back
        f.seek(-1, 1)
        refs.append(f.readline().strip())
    # now that we're done, move back
    f.seek(-1, 1)
    # join the individual lines, then split by ";" and strip
    d["sa"] = [r.strip() for r in (" ".join(refs)).split(";")]

def _omim_references(f, d):
    refs = []
    # peek ahead one character
    while f.read(1) != "*":
        ref = []
        # move back
        f.seek(-1, 1)
        # read in each individual reference
        while True:
            line = f.readline().strip()
            if line:
                ref.append(line)
            else:
                # this has the effect of swallowing the empty line
                break
        refs.append(" ".join(ref))
    # now that we're done, move back
    f.seek(-1, 1)
    d["rf"] = refs

def _omim_clinical_synopsis(f, d):
    # advance past empty line
    f.readline()
    synopsis = {}
    # peek ahead one character
    while f.read(1) != "*":
        # move back
        f.seek(-1, 1)
        heading = f.readline().strip().rstrip(":")
        entries = []
        # read in each individual entry
        while True:
            line = f.readline().strip()
            if line:
                entries.append(line)
            else:
                # this has the effect of swallowing the empty line
                break
        synopsis[heading] = (" ".join(entries)).split(";")
    # now that we're done, move back
    f.seek(-1, 1)
    d["cs"] = synopsis

def _omim_contributors(f, d):
    contribs = []
    while True:
        c = f.readline().strip()
        if c:
            contribs.append(c)
            # begin syntax error workaround
            # for missing blank lines
            test = f.read(1)
            f.seek(-1, 1)
            if test == "*":
                break
            # end syntax error workaround
        else:
            break
    d["cn"] = contribs

def _omim_creation_date(f, d):
    d["cd"] = f.readline().strip()
    # begin syntax error workaround
    # for missing blank lines
    test = f.read(1)
    f.seek(-1, 1)
    if test == "*":
        return
    # end syntax error workaround
    # advance past the subsequent empty line
    f.readline()

def _omim_edit_history(f, d):
    edits = []
    while True:
        e = f.readline().strip()
        if e:
            edits.append(e)
            # begin syntax error workaround
            # for missing blank lines
            test = f.read(1)
            f.seek(-1, 1)
            if test == "*":
                break
            # end syntax error workaround
        else:
            break
    d["ed"] = edits

def _omim_miscellaneous_field(f, d):
    # placeholder: just read till we hit the next field
    # peek ahead one character
    while f.read(1) != "*":
        # move back
        f.seek(-1, 1)
        f.readline()
    # now that we're done, move back
    f.seek(-1, 1)

def _omim_iterator(f):
    # get started with the first record
    if not f.readline().strip() == "*RECORD*":
        raise Exception("expected *RECORD* line not found")
    
    record_dictionary = {}
    while True:
        # now, read field
        field = f.readline().strip()
        
        # spit out the record if we're done
        if not field or field == "*RECORD*" or field == "*THEEND*":
            yield OMIMRecord(record_dictionary)
            if field == "*RECORD*":
                # we're at the beginning of a new record
                record_dictionary = {}
                continue
            else:
                # we're at the end of the file
                break
        
        # otherwise, continue parsing fields
        if not field.startswith("*FIELD*"):
            raise Exception("expected *FIELD* line not found")
        field = field[8:]
        
        parse = {
            "NO": _omim_number,
            "TI": _omim_title,
            "TX": _omim_text,
            "AV": _omim_allelic_variants,
            "SA": _omim_see_also,
            "RF": _omim_references,
            "CS": _omim_clinical_synopsis,
            "CN": _omim_contributors,
            "CD": _omim_creation_date,
            "ED": _omim_edit_history
        }
        parse.get(field, _omim_miscellaneous_field)(f, record_dictionary)

class OMIMFile(object):
    def __init__(self, src):
        # try to open the file, in case we're given a path
        try:
            f = open(src)
        # if that doesn't work, treat the argument itself as a file
        except TypeError:
            f = src
        self.iterator = _omim_iterator(f)
        self.file = f
    
    def __iter__(self):
        return self
    
    def next(self):
        return self.iterator.next()
    
    def __getitem__(self, key):
        key = key.strip()
        for record in iter(self):
            if key == record.number:
                return record
        return None
    
    def close(self):
        assert (self.file is not None)
        self.file.close()
        self.file = None

def input(src):
    return OMIMFile(src)
