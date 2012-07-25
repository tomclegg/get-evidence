#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

import re
import optparse
from utils import gff, autozip

DEFAULT_BUILD = "b36"

class PhaseTrio:
    """Class to call phasing of heterozygous variants from a child
    given both or a single parent's genome."""
    idx2str = {0 : 'child', 1 : 'parA', 2 : 'parB'}
    str2idx = {'child' : 0, 'parA' : 1, 'parB' : 2}
    header_done = False
    NO_CALL, REF, MATCH, MISMATCH = range(4)

    def __init__(self, f_child, f_parA, f_parB, mend_errs):
        """Initializes class variables, opens input files."""
        self.filenames = {0 : f_child, 1 : f_parA}
        self.mend_errs = mend_errs
        self.gffs = {0 : None, 1 : None}
        # Positions are a tuple of chromosome, start, end, and gff record
        self.positions = {0 : ('chr1', -1, -1, None),
                          1 : ('chr1', -1, -1, None)}
        if (not f_parB == None):
            self.filenames[2] = f_parB
            self.gffs[2] = None
            self.positions[2] = ('chr1', -1, -1, None)
    
        # Set up input/output files
        for idx, filename in self.filenames.iteritems():
            self.gffs[idx] = gff.input(autozip.file_open(filename, 'r'))
            
    def advance_child(self, patient):
        """Advance file to next heterozygous variation."""
        patient_idx = self.str2idx[patient] if isinstance(patient, str) \
                        else patient

        # Print header data
        if not self.header_done:
            self.header_done = True
            return ("##genome-build %s" % DEFAULT_BUILD), False

        for record in self.gffs[patient_idx]:
            if (not record.feature == "REF" and is_heterozygous(record)):
                self.positions[patient_idx] = (record.seqname, record.start,
                                                record.end, record)
                return record, True
            else:
                return str(record), False
        return False, False

    def advance_parent_to(self, patient, position):
        """Advance file for given parent to (or immediately past) a given
        position."""
        patient_idx = self.str2idx[patient] if isinstance(patient, str) \
                        else patient

        prev_pos = self.positions[patient_idx]
        comp = within_position(position, prev_pos)
        # Don't advance if within the last range
        if (comp == 0):
            if (position[1] == prev_pos[1] and position[2] == prev_pos[2]):
                # Variant at the searched position
                rec = prev_pos[3]
                if (rec.feature == "REF"):
                    return self.REF
                else:
                    return rec

            # Reference at the searched position
            return self.REF

        # Start generating new records
        for record in self.gffs[patient_idx]:
            next_pos = (record.seqname, record.start, record.end, record)
            comp = within_position(position, next_pos)
            self.positions[patient_idx] = next_pos
            if (comp > 0):
                # Searched position is larger, continue
                prev_pos = next_pos
                continue

            elif (comp == 0):
                if (record.feature == "REF"):
                    return self.REF

                # Searched position is within this record
                if (position[1] == next_pos[1] and position[2] == next_pos[2]):
                    # Variant at the searched position
                    return record

            # Searched position never found or var that didn't match exactly
            break
        return self.NO_CALL

    def call_phase(self):
        """Try to call phasing for SNPs in child, given one or two parents.
        Returns a generator printing GFF-style lines."""
        using_parB = self.str2idx['parB'] in self.gffs
        NO, MAYBE, YES = range(-1, 2) #to match return values for var_match()
        child_record, should_process = self.advance_child('child')
        while (child_record):
            if (not should_process):
                yield child_record
                child_record, should_process = self.advance_child('child')
                continue

            ref = None
            if ('ref_allele' in child_record.attributes):
                ref = child_record.attributes['ref_allele']

            child_alleles = child_record.attributes['alleles']. \
                                strip('"').split('/')

            par_alleles = ["", ""]

            phase = [[None, None],   #parent A calls for child alleles [1,2]
                    [None, None]]    #parent B calls for child alleles [1,2]

            for par_idx in range(2):
                parent_record = None
                #Advance parent 'par_idx' to position of child variant
                if (par_idx == 0):
                    parent_record = self.advance_parent_to('parA',
                                        self.positions[self.str2idx['child']])
                elif (par_idx == 1 and using_parB):
                    parent_record = self.advance_parent_to('parB',
                                        self.positions[self.str2idx['child']])
                else:
                    break

                # Check if parent record is REF or NO_CALL, else it's a variant
                if (isinstance(parent_record, int)):# \
                        #or (not 'alleles' in parent_record.attributes)):
                    if (parent_record == self.REF):
                        par_alleles[par_idx] = "REF"
                        if (child_alleles[0] == ref):
                            phase[par_idx][0] = YES
                            phase[par_idx][1] = NO
                        elif (child_alleles[1] == ref):
                            phase[par_idx][0] = NO
                            phase[par_idx][1] = YES
                        else:
                            # Parent is ref and child has no ref, mismatch
                            phase[par_idx][0] = NO
                            phase[par_idx][1] = NO
                    else:
                        # no call is default
                        par_alleles[par_idx] = "NO_CALL"
                else:
                    par_alleles[par_idx] = parent_record.attributes['alleles']
                    for a_idx in range(2): # for each child allele 'a_idx'
                        b_idx = (a_idx + 1) % 2
                        if (not child_alleles[a_idx] == ref):
                            # Child allele 'a_idx' is a het variant
                            matched = var_match(child_alleles[a_idx],
                                                    parent_record)
                            phase[par_idx][a_idx] = matched
                            # Other allele being ref provides more info
                            if (child_alleles[b_idx] == ref):
                                if (matched == YES):
                                    phase[par_idx][b_idx] = NO
                                elif (matched == MAYBE):
                                    other_mat = var_match(ref, parent_record)
                                    if (other_mat == NO): #maybe,no -> yes,no
                                        phase[par_idx][b_idx] = other_mat
                                        phase[par_idx][a_idx] = YES
                                    elif (other_mat == MAYBE):
                                        phase[par_idx][b_idx] = other_mat
                                else:
                                    other_mat = var_match(ref, parent_record)
                                    if (other_mat == YES or other_mat == MAYBE):
                                        #no,yes or no,maybe -> no,yes
                                        phase[par_idx][b_idx] = YES
                                        phase[par_idx][a_idx] = NO
                                    else:
                                        #no,no
                                        phase[par_idx][b_idx] = NO
                            
            # make final phase call and continue
            self.interpret_phase(phase, child_record, par_alleles)
            yield (str(child_record))
            child_record, should_process = self.advance_child('child')

    def interpret_phase(self, data, child_record, par_alleles, phase_block=1):
        """Takes the output data from call_phase and interprets it to make
        a final call (considering user options)."""
        NO, MAYBE, YES = range(-1, 2) #to match return values for var_match()

        # Possible values in 'data' for a single parent are:
        # Val          Example: Ref     Child   Parent
        # ---------------------------------------------
        # [None,None]           A       A/T     NOCALL
        # [YES,NO]              A       C/A     C/C
        # [NO,NO]               A       C/G     REF
        # [MAYBE,MAYBE]         A       A/T     A/T
        # [MAYBE,NO]            A       C/G     C/A
        
        # Convert [MAYBE,NO] to [YES,NO]
        for i in range(2):
            if (data[i] == [MAYBE, NO]):
                data[i] = [YES, NO]
            elif (data[i] == [NO, MAYBE]):
                data[i] = [NO, YES]

        # Check for consistency. If inconsistent, then assume a mendelian
        # inheritance error and follow user option 'mend_err'
        phased1 = phase_str(phase_block, data[0], False)
        phased2 = phase_str(phase_block, data[1], True)
        if (phased1 == None or phased2 == None):
            # Mendelian inheritance error
            if (self.mend_errs):
                child_record.attributes['MIE'] = "&".join(par_alleles)
            return
        
        cons_call = consistent_call(phased1, phased2)
        if (isinstance(cons_call, str)):
            if (cons_call != ""):
                child_record.attributes['phase'] = cons_call
        else:
            # Mendelian inheritance error
            if (self.mend_errs):
                child_record.attributes['MIE'] = "&".join(par_alleles)
        return
    
def var_match(child_var, parent_record):
    """Given a variant from the child and a GFF record for a parent, check
    if the parent contributed the variant. Return values:
    -1 - No
    0 - Maybe (requires parent is heterozygous)
    1 - Yes (requires parent is homozygous)"""
    NO, MAYBE, YES = range(-1, 2)
    
    par_alleles = parent_record.attributes['alleles'].strip('"').split('/')
    if (len(par_alleles) > 1):
        #Parent is heterozygous
        for par_all in par_alleles:
            if (par_all == child_var):
                return MAYBE
    else:
        #Parent is homozygous
        if (par_alleles[0] == child_var):
            return YES

    return NO

def is_heterozygous(record):
    """Given a single GFF record, determine if it is a heterozygous variant.
    Simply does a regex match for '/'."""
    return re.search('/', record.attributes['alleles'])

def within_position(pos1, pos2):
    """Compares positions (tuples of chromosome and position).
    Encodes if pos1 is within pos2.
    If pos1 is within pos2, returns 0. Otherwise, returns -1
    or 1 if pos1 is before or after pos2, respectively.
    Note: Equivalent positions is a special case of within."""
    if (pos1[0] != pos2[0]):
        return cmp(pos1[0], pos2[0])
    elif (pos1[1] >= pos2[1] and pos1[2] <= pos2[2]):
        return 0
    else:
        return cmp(pos1[1], pos2[1])

def consistent_call(str1, str2):
    """Check if the phase call from each parent is consistent. If
    it is, then we have the phase call. If it is not, then there is
    an inconsistency and we follow the action requested by the user
    via the 'mend_errs' option."""
    if (str1 == str2):
        return str1
    elif (str1 and not str2):
        return str1
    elif (str2 and not str1):
        return str2
    elif (not str1 and not str2):
        return str1

    # Otherwise inconsistent
    return False

def phase_str(phase_block, data, reverse=False):
    """Interpret the [YES,NO]-style pair into a string to be inserted
    into the 'phase' attribute of the GFF record."""
    NO, MAYBE, YES = range(-1, 2) #to match return values for var_match()
    ret_phase = ""
    if (data[0] == YES):
        ret_phase = "%s-%s/%s-%s" % (phase_block, 1, phase_block, 2)
    elif (data[1] == YES):
        ret_phase = "%s-%s/%s-%s" % (phase_block, 2, phase_block, 1)
    elif (data[0] == NO and data[1] == NO):
        ret_phase = None

    if (reverse and ret_phase):
        tmp = ret_phase.split('/')
        ret_phase = "%s/%s" % (tmp[1], tmp[0])

    return ret_phase

def main():
    """Main function."""
    usage = 'usage: %prog [options] gff_child gff_parentA [gff_parentB]'
    parser = optparse.OptionParser(usage=usage)
    parser.add_option('-o', '--output', help="Specificies an option output " \
        + "file name. Default is standard output.", dest='f_out',
        action='store')
    parser.add_option('-m', '--mend_errs', help="If set, report mendelian " \
        + "inheritance errors as an attribute. Default is to ignore them.",
        dest='mend_errs', action='store_true', default=False)
    (opts, args) = parser.parse_args()

    if (len(args) < 2):
        parser.error("Need atleast 2 input file arguments.")

    child = args[0]
    parent_a = args[1]
    parent_b = None
    if (len(args) > 2):
        parent_b = args[2]

    trioizer = PhaseTrio(child, parent_a, parent_b, opts.mend_errs)
    if opts.f_out:
        out = autozip.file_open(opts.f_out, 'w')
        for line in trioizer.call_phase():
            out.write('%s\n' % line)
    else:
        for line in trioizer.call_phase():
            print '%s\n' % line
    
if __name__ == "__main__":
    main()

################################################################################
