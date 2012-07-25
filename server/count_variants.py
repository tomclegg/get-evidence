#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

import re
import sys
import simplejson as json
import MySQLdb, MySQLdb.cursors
from optparse import OptionParser
from utils import twobit, autozip

DB_HOST = 'localhost'
GETEVIDENCE_USER = 'evidence'
GETEVIDENCE_DATABASE = 'evidence'
GENOME_USER_OIDS = ['https://profiles.google.com/PGP.uploader', 'https://profiles.google.com/Public.Genome.uploader']
TWOBIT_PATH = '/home/trait/data/hg18.2bit'
GENOMEFILE_PRE = '/home/trait/upload/'
GENOMEDATA_POST = '-out/ns.gff.gz'
GENOMEMETA_POST = '-out/metadata.json'

class GenomeData:
    """
    Handle genome sequence data from a singel genome, assumes PGP's GFF format

    self.id = genome ID
    self.f_in = file input object
    self.data = array containing parsed data from self.f_in
    self.metadata = dict containing metadata for this genome
    """
    def __init__(self, genome_id, chroms=None, getev_vars=None):
        self.id = genome_id
        self.chroms = chroms
        self.getev = getev_vars
        self.f_in = None
        self.data = []
        self.metadata = []
        self.readahead = None

        # Load data from files
        self.read_metadata(genome_id)
        self.open_genome_file(genome_id)
        # Do twice to fill the readahead buffer
        self.readline()
        self.readline()

    def read_metadata(self, genome_id):
        """Open file containing metadata, initializes self.metadata"""
        metadata_path = GENOMEFILE_PRE + genome_id + GENOMEMETA_POST
        f_meta = autozip.file_open(metadata_path)
        self.metadata = json.loads(f_meta.next())
        f_meta.close()

    def open_genome_file(self, genome_id):
        """Open file containing sequence data, initializes self.f_in"""
        genome_file_path = GENOMEFILE_PRE + genome_id + GENOMEDATA_POST
        self.f_in = autozip.file_open(genome_file_path)

    def _parsedata(self, data):
        parsed = { 'chrom': data[0],
                   'start': int(data[3]) - 1,
                   'end': int(data[4]),
                   'ref': data[2] == "REF" }
        if len(data) < 9 or data[8] == '.' or data[8] == '':
            return parsed
        attributes_data = data[8].split(';')
        attributes = dict()
        for attribute in attributes_data:
            if re.match('\w+\W+.*$', attribute):
                attribute_data = re.match('([^ \t]+)[ \t]+(.*)$', attribute).groups()
                attributes[attribute_data[0]] = attribute_data[1]
        if 'ref_allele' in attributes:
            parsed['ref_allele'] = attributes['ref_allele']
            if parsed['ref_allele'] == '-':
                parsed['ref_allele'] = ''
        else:
            return parsed
        if 'alleles' in attributes:
            parsed['alleles'] = attributes['alleles'].split('/')
            if (len(parsed['alleles']) == 1 and 
                self._should_double(parsed['chrom'], parsed['start'],
                                    parsed['end'])):
                parsed['alleles'].append(parsed['alleles'][0])
            for i in range(len(parsed['alleles'])):
                if parsed['alleles'][i] == '-':
                    parsed['alleles'][i] = ''
            # Don't try to assign amino_acid, dbsnp, or getev_id for het cases
            # where both alleles are nonreference. The two annotations get 
            # confused with each other, unfortunately, because those attributes 
            # haven't been stored cleanly on a per-allele basis. - MPB 4/11
            if (len(parsed['alleles']) > 1 and
                parsed['alleles'][0] != parsed['alleles'][1] and
                parsed['alleles'][0] != parsed['ref_allele'] and
                parsed['alleles'][1] != parsed['ref_allele']):
                return parsed
        else:
            return parsed
        if 'amino_acid' in attributes:
            # Ignore any beyond first transcript
            aa_data = attributes['amino_acid'].split('/')[0].split()
            if (len(aa_data) == 2):
                parsed['amino_acid'] = '-'.join(aa_data)
                if self.getev and parsed['amino_acid'] in self.getev:
                    parsed['getev_id'] = self.getev[parsed['amino_acid']]
        if 'db_xref' in attributes:
            dbsnp_data = attributes['db_xref'].split(',')
            for dbsnp_datum in dbsnp_data:
                dbsnp_split = dbsnp_datum.split(':')
                if self.getev and dbsnp_split[1] in self.getev:
                    parsed['getev_id'] = self.getev[dbsnp_split[1]]
                    parsed['dbsnp'] = dbsnp_split[1]
                else:
                    parsed['dbsnp'] = dbsnp_split[1]
        return parsed

    def readline(self):
        """Read a line from self.f_in, return parsed and add it to self.data"""
        try: 
            line = self.f_in.next()
        except StopIteration:
            if self.readahead:
                self.data.append(self.readahead)
                self.readahead = None
            return None
        split_line = line.strip().split('\t')
        if self.chroms and not split_line[0] in self.chroms:
            while not split_line[0] in self.chroms:
                try: 
                    line = self.f_in.next()
                except StopIteration:
                    if self.readahead:
                        self.data.append(self.readahead)
                    self.readahead = None
                    return None
                split_line = line.strip().split('\t')
        if self.readahead:
            self.data.append(self.readahead)
        self.readahead = self._parsedata(split_line)


    # TODO: Check PAR in X
    def _should_double(self, chrom, start, end):
        """Report if region should be diploid in this genome"""
        should_double = ((chrom != 'chrX' or not 
                          'chrY' in self.metadata['chromosomes']) and
                         chrom != 'chrY')
        return should_double

    def advance_past_end_pos(self, position):
        """Move forward, add data until last data ends after position end"""
        last_pos = None
        if self.data:
            last_pos = self.data[-1]
        while (self.readahead and (comp_pos_ends(last_pos, position) <= 0 or
                                   comp_pos_ends(last_pos, self.readahead) == 0)):
            self.readline()
            last_pos = self.data[-1]
        # Last positions could be multiple if ends are the same point.
        if not self.data:
            return None
        last_position_indexes = [ -1 ]
        while (len(self.data) > abs(last_position_indexes[-1]) and
               comp_pos_ends(last_pos, 
                             self.data[last_position_indexes[-1] - 1]) == 0): 
            last_position_indexes.append(last_position_indexes[-1] - 1)
        last_positions = [self.data[i] for i in last_position_indexes]
        #if len(last_positions) > 1:
        #    print "Multiple last positions for " + self.id + ": " + str(last_positions)
        return last_positions

    def check_covered(self, chrom, start, end):
        """Check if genome data covers region specified by chrom, start, end"""
        for position in self.data:
            if position['chrom'] == chrom:
                if position['start'] <= start:
                    if position['end'] >= end:
                        return True
                    elif position['end'] >= start:
                        start = position['end']
        return False

    def genotype_region(self, twobit_ref, chrom, start, end):
        """Return genotype and variant position for region, or None if fail"""
        var_pos = None
        if not self.data:
            return None, None
        genotype_1_bynuc = dict()
        if self._should_double(chrom, start, end):
            genotype_2_bynuc = dict()
        for position in self.data:
            if (position['start'] == start and position['end'] == end and 
                not position['ref']):
                var_pos = position
                return position['alleles'], var_pos
            # The following test requires the edges of a reference region to
            # extend beyond the target position if the target is an insertion.
            elif (position['ref'] and
                  ((start != end and 
                    position['start'] <= start and 
                    position['end'] >= end and position['ref']) or 
                   (position['start'] < start and position['end'] > end))):
                ref_genotype = twobit_ref[position['chrom']][start:end]
                if self._should_double(chrom, start, end):
                    return [ref_genotype, ref_genotype], var_pos
                else:
                    return [ref_genotype], var_pos
            # Complicated position, break it down, put together later.
            elif position['start'] <= end and position['end'] >= start:
                if position['ref']:
                    max_s = max(position['start'], start)
                    min_e = min(position['end'], end)
                    sub_genotype = twobit_ref[position['chrom']][max_s:min_e]
                    genotype_1_bynuc[max_s] = sub_genotype
                    if self._should_double(chrom, start, end):
                        genotype_2_bynuc[max_s] = sub_genotype
                else:
                    var_pos = position
                    var_s = position['start']
                    alleles = position['alleles']
                    genotype_1_bynuc[var_s] = alleles[0]
                    if self._should_double(chrom, start, end):
                        genotype_2_bynuc[var_s] = alleles[1]
        genotype_start_keys = genotype_1_bynuc.keys()
        genotype_start_keys.sort()
        if self._should_double(chrom, start, end):
            genotype_1 = genotype_2 = ''
            for loc in genotype_start_keys:
                genotype_1 = genotype_1 + genotype_1_bynuc[loc]
                genotype_2 = genotype_2 + genotype_2_bynuc[loc]
            return [genotype_1, genotype_2], var_pos
        else:
            genotype_1 = ''
            for loc in genotype_start_keys:
                genotype_1 = genotype_1 + genotype_1_bynuc[loc]
            return [genotype_1], var_pos

def comp_pos_ends(pos1, pos2):
    """Compare sequence data end positions"""
    if not pos1 or not pos2:
        return 0
    if pos1['chrom'] < pos2['chrom']:
        return -1
    elif pos1['chrom'] > pos2['chrom']:
        return 1
    else:
        if pos1['end'] < pos2['end']:
            return -1
        elif pos1['end'] > pos2['end']:
            return 1
        else:
            return 0

class GenomeSet:
    """Handle a set of GenomeData objects"""
    def __init__(self, genome_ids, chroms=None, getev_vars=None, verbose=False):
        self.genomes = []
        self.verbose = verbose
        self.load_genomes(genome_ids, chroms, getev_vars)

    def load_genomes(self, genome_ids, chroms, getev_vars):
        for genome_id in genome_ids:
            if self.verbose:
                print "Loading " + genome_id + "..."
            try:
                self.genomes.append(GenomeData(genome_id, chroms=chroms, 
                                               getev_vars=getev_vars))
            except IOError:
                if self.verbose:
                    print "Can't load " + genome_id
                continue

    def earliest_ends(self):
        earliest_ends = []
        for genome in self.genomes:
            if genome.data:
                if earliest_ends:
                    cmp_ends = comp_pos_ends(genome.data[-1], earliest_ends[0])
                    if cmp_ends < 0:
                        earliest_ends = [ genome.data[-1] ]
                    elif cmp_ends == 0:
                        earliest_ends.append( genome.data[-1] )
                else:
                    earliest_ends = [ genome.data[-1] ]
        return earliest_ends

    def advance_all_past_end_pos(self, position):
        earliest_ends = []
        remove_genomes = []
        for genome in self.genomes:
            last_ends = genome.advance_past_end_pos(position)
            if not last_ends:
                if not genome.readahead:
                    remove_genomes.append(genome)
                continue
            if earliest_ends:
                comp_earliest = comp_pos_ends(last_ends[0], earliest_ends[0])
                if comp_earliest < 0:
                    earliest_ends = last_ends
                elif comp_earliest == 0:
                    earliest_ends = earliest_ends + last_ends
            else:
                earliest_ends = last_ends
        if remove_genomes:
            for genome in remove_genomes:
                self.genomes.remove(genome)
        return earliest_ends

    def no_later_var(self, var_positions):
        start = min(pos['start'] for pos in var_positions)
        end = max(pos['end'] for pos in var_positions)
        for genome in self.genomes:
            for position in genome.data:
                # overlaps our known variant? This test is written so insertions
                # at start or end will be "overlapping".
                if ((position['start'] < end and position['end'] > start) or
                    (position['start'] == position['end'] and 
                     position['start'] >= start and position['end'] <= end)):
                    # not ref - a variant?
                    if not position['ref']:
                        # later than our known end!
                        if position['end'] > end:
                            return False
        return True

    def eval_var_freq(self, var_positions, twobit_ref):
        """Evaluate allele frequency at input variant position"""
        # Find union of variant regions
        chrom = var_positions[0]['chrom']
        start = var_positions[0]['start']
        end = var_positions[0]['end']
        is_mult = False
        for position in var_positions:
            if start != position['start'] or end != position['end']:
                is_mult = True
            start = min(start, position['start'])

        # Check each genome for required region, get genotype in target region
        counts = dict()
        genotype_to_ID = dict()
        genotype_info = dict()
        ref_genotype = ''
        if (end > start):
            #print "Finding ref genotype for: " + chrom + " " + str(start) + " " + str(end)
            ref_genotype = twobit_ref[chrom][start:end]
        counts[ref_genotype] = 0
        carrier_ids = { ref_genotype: [] }
        var_genotypes = []
        for genome in self.genomes:
            if genome.check_covered(chrom, start, end):
                genotypes, var_pos = genome.genotype_region(twobit_ref, chrom, 
                                                            start, end)
                if not genotypes:
                    continue
                for genotype in genotypes:
                    if genotype != ref_genotype:
                        if not genotype in var_genotypes:
                            var_genotypes.append(genotype)
                        if (not genotype in genotype_info or 
                            genotype_info[genotype] == 'none'):
                            if 'amino_acid' in var_pos:
                                genotype_info[genotype] = var_pos['amino_acid']
                            elif 'dbsnp' in var_pos:
                                genotype_info[genotype] = var_pos['dbsnp']
                            else:
                                genotype_info[genotype] = 'none'
                        if (not genotype in genotype_to_ID or 
                            genotype_to_ID[genotype] == 'unknown'):
                            if 'getev_id' in var_pos:
                                genotype_to_ID[genotype] = var_pos['getev_id']
                            else:
                                genotype_to_ID[genotype] = 'unknown'
                    if genotype in counts:
                        counts[genotype] += 1
                    else:
                        counts[genotype] = 1
                    if genotype in carrier_ids:
                        carrier_ids[genotype].append(genome.id)
                    else:
                        carrier_ids[genotype] = [ genome.id ]
        # Print data for variant position:
        # (1) chromosome (2) start (3) end (4) reference genotype
        # (5) # of reference observations (6) variant genotype(s)
        # (7) # of variant observatinos (8) variant GET-Evidence IDs 
        # (if available) (9) variant info (amino acid change or dbSNP ID)
        var_IDs = [genotype_to_ID[x] for x in var_genotypes]
        var_info = [genotype_info[x] for x in var_genotypes]
        var_counts = [counts[x] for x in var_genotypes]
        var_carriers = [','.join(carrier_ids[x]) for x in var_genotypes]
        ref_genotype_print = ref_genotype
        if ref_genotype_print == '':
            ref_genotype_print = '-'
        var_genotypes_print = var_genotypes[:]
        for i in range(len(var_genotypes_print)):
            if var_genotypes_print[i] == '':
                var_genotypes_print[i] = '-'
        return '\t'.join([chrom, str(start), str(end), ref_genotype_print, 
                         str(counts[ref_genotype]), ','.join(var_genotypes_print),
                         ','.join([str(x) for x in var_counts]),
                         ','.join(var_IDs), ','.join(var_info)])
                         #,
                         #','.join(carrier_ids[ref_genotype]),
                         #','.join(var_carriers)])

    def clean_out_prior_pos(self, earliest_ends):
        earliest_start = earliest_ends[0]
        for pos in earliest_ends:
            if pos['chrom'] < earliest_start['chrom']:
                earliest_start = pos
            elif pos['start'] < earliest_start['start']:
                earliest_start = pos
        genomes_to_remove = []
        for genome in self.genomes:
            pos_to_remove = []
            for position in genome.data:
                if position['chrom'] < earliest_start['chrom']:
                    pos_to_remove.append(position)
                elif position['end'] < earliest_start['start']:
                    pos_to_remove.append(position)
            for pos in pos_to_remove:
                genome.data.remove(pos)
            if not genome.data and not genome.readahead:
                genomes_to_remove.append(genome)
        for genome in genomes_to_remove:
            self.genomes.remove(genome)
            

                
def read_single_items(filename):
    """Read a file containing strings to be matched on for excluding genomes"""
    f_in = open(filename)
    items = []
    for line in f_in:
        item = line.strip()
        if len(item) > 0:
            items.append(item)
    return items

def get_genome_list(password, excludednickfile=None):
    """Get list of shasum IDs which will be used for allele frequencies"""
    # Try to connect to the database.
    try:
        connection = MySQLdb.connect(cursorclass=MySQLdb.cursors.DictCursor, 
                                     host=DB_HOST, user=GETEVIDENCE_USER, 
                                     passwd=password, db=GETEVIDENCE_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        sys.stderr.write ("Error %d while connecting to database: %s\n" % 
                          (message[0], message[1]))
        sys.exit()
    genomes_query =('SELECT * FROM private_genomes WHERE oid IN (' +
                    ','.join(["'" + x + "'" for x in GENOME_USER_OIDS]) + ')')
    cursor.execute(genomes_query)
    genomes_list = cursor.fetchall()

    if excludednickfile:
        excluded_nicks = read_single_items(excludednickfile)
    else:
        excluded_nicks = []
    shasums = dict()
    for item in genomes_list:
        excluded = False
        for nick in excluded_nicks:
            if re.search(nick, item['nickname']):
                excluded = True
        if not excluded:
            shasums[item['shasum']] = item['nickname']
    return shasums


def load_getev(getev_file):
    """Read GET-Evidence flatfile"""
    getev_variants = dict()
    if isinstance(getev_file, str):
        getev_in = autozip.file_open(getev_file)
    else:
        getev_in = getev_file
    for line in getev_in:
        getev_data = json.loads(line)
        has_variant_id = ('variant_id' in getev_data and
                          getev_data['variant_id'])
        has_aachange = ('gene' in getev_data and getev_data['gene'] and
                        'aa_change_short' in getev_data and
                        getev_data['aa_change_short'])
        has_dbsnp = ('dbsnp_id' in getev_data and getev_data['dbsnp_id'])
        if has_aachange and has_variant_id:
            gene_aachange_key = (getev_data['gene'] + '-' +
                                 getev_data['aa_change_short'])
            getev_variants[gene_aachange_key] = getev_data['variant_id']
        elif has_dbsnp and has_variant_id:
            dbsnp_key = getev_data['dbsnp_id']
            getev_variants[dbsnp_key] = getev_data['variant_id']
    getev_in.close()
    return getev_variants

def get_allele_freqs(password, getev_file, excluded=None, chromfile=None, 
                     outputfile=None):
    # Set up output, genome inputs, GET-Evidence variants, and twobit reference.
    if outputfile:
        print "Setting up output file"
        f_out = autozip.file_open(outputfile, 'w')
    else:
        f_out = None
    genome_ids = get_genome_list(password, excluded)
    if chromfile:
        if f_out:
            print "Getting chromosomes..."
        chroms = read_single_items(chromfile)
    else:
        chroms = None
    if f_out:
        print "Reading GET-Ev flat file (takes a couple minutes)..."
    getev_variants = load_getev(getev_file)
    if f_out:
        print "Loading twobit genome..."
    twobit_genome = twobit.input(TWOBIT_PATH)
    if f_out:
        print("Setting up GenomeSet (may be slow if each genome has to advance " +
              "to target chromosomes)...")
        genome_set = GenomeSet(genome_ids, chroms=chroms, getev_vars=getev_variants,
                               verbose=True)
    else:
        genome_set = GenomeSet(genome_ids, chroms=chroms, getev_vars=getev_variants)
    if f_out:
        print "Find earliest ends"
    earliest_ends = genome_set.earliest_ends() 
    #print earliest_ends

    # Move through the genomes to find allele frequencies
    while genome_set.genomes:
        # Move ahead of all "earliest ends" & save new earliest.
        next_earliest = genome_set.advance_all_past_end_pos(earliest_ends[0])

        # Check all old "earliest ends" positions for interesting variants.
        has_var = []
        is_interesting = False
        for position in earliest_ends:
            #print position
            if not position['ref']:
                has_var.append(position)
                #is_interesting = True
                if 'amino_acid' in position or 'getev_id' in position:
                    is_interesting = True

        #if is_interesting:
        #    print "Earliest ends: " + str(earliest_ends)
        #    print [(x.id, x.data[-1]) for x in genome_set.genomes]
        #    if has_var:
        #        print "Var pos: " + str(has_var)

        # If there are interesting variants, calculate allele frequency.
        if has_var and is_interesting:
            # Check if another genomes has an overlapping variant extending 
            # beyond this position, we're not ready to evaluate this yet 
            # (it will be caught when the later overlapping one comes up).
            if genome_set.no_later_var(has_var):
                freqout = genome_set.eval_var_freq(has_var, twobit_genome)
                if f_out:
                    f_out.write(freqout + '\n')
                else:
                    print freqout

        genome_set.clean_out_prior_pos(earliest_ends)

        # Reset "earliest end" to next earliest positions.
        earliest_ends = next_earliest


def main():
    # Parse options                
    usage = "%prog -p password -g getev_file [-o outputfile -x excluded_nicks_file -c chromosome]"
    parser = OptionParser(usage=usage)
    parser.add_option("-p", "--password", dest="password",
                      help="password for GET-Evidence MySQL database",
                      metavar="PASSWORD")
    parser.add_option("-g", "--getev", dest="getev_file",
                      help="Path to GET-Evidence JSON flat file",
                      metavar="GETEVFILE")
    parser.add_option("-x", "--exclude", dest="excludefile",
                      help="file containing nicks of genomes to exclude",
                      metavar="EXCLUDEFILE")
    parser.add_option("-c", "--chrom", dest="chromfile",
                      help="file containing names of chromosomes to examine",
                      metavar="CHROMOSOME")
    parser.add_option("-o", "--out", dest="outputfile",
                      help="file to output allele frequency data (if specified" +
                      "status updates are sent to stdout)", metavar="OUTFILE")
    options, args = parser.parse_args()
    # Handle input
    if options.password and options.getev_file:
        excludefile = chromfile = outputfile = None
        if options.excludefile:
            excludefile = options.excludefile
        if options.chromfile:
            chromfile = options.chromfile
        if options.outputfile:
            outputfile = options.outputfile
        get_allele_freqs(password=options.password,
                         getev_file=options.getev_file,
                         excluded=excludefile,
                         chromfile=chromfile,
                         outputfile=outputfile)
    else:
        parser.print_help()

if __name__ == "__main__":
    main()
