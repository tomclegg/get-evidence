#!/usr/bin/python

import os
import re
import sys
import MySQLdb, MySQLdb.cursors
from optparse import OptionParser
from utils import twobit

DB_HOST = 'localhost'
GETEVIDENCE_USER = 'evidence'
GETEVIDENCE_DATABASE = 'evidence'
GENOME_USER_OIDS = ['https://profiles.google.com/PGP.uploader', 'https://profiles.google.com/Public.Genome.uploader']
TWOBIT_PATH = '/home/trait/data/hg18.2bit'

def read_excluded_nicks(filename):
    """Read a file containing strings to be matched on for excluding genomes"""
    nicks_input = open(filename)
    nicks = []
    for line in nicks_input:
        nick = line.strip()
        if len(nick) > 0:
            nicks.append(nick)
    return nicks

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
        excluded_nicks = read_excluded_nicks(excludednickfile)
    else:
        excluded_nicks = []
    shasums = []
    for item in genomes_list:
        excluded = False
        for nick in excluded_nicks:
            if re.search(nick, item['nickname']):
                excluded = True
                #print "Exclude nick '" + nick + "': " + item['nickname']
        if not excluded:
            shasums.append(item['shasum'])
    return shasums

def parseline(GFF_record):
    """Parse string that is a line in a GFF file, return dict with data

    Dict contains:
    'chr': string of chromosome
    'start': int of start position (0-based; GFF is 1-based)
    'end': int of end position (1-based)
    'ref': boolean, true if line is matching-reference, false if variant
    'alleles': list containing two alleles (only defined for variants)
    """
    parsed = dict()
    data = GFF_record.strip().split('\t')
    parsed['chr'] = data[0]
    # 0-based start is easier to manipulate internally.
    parsed['start'] = int(data[3]) - 1
    parsed['end'] = int(data[4])
    if data[2] == "REF":
        parsed['ref'] = True
    else:
        parsed['ref'] = False
        attributes_data = data[8].split(';')
        for item in attributes_data:
            attribute_data = item.split()
            if attribute_data[0] == "alleles":
                parsed['alleles'] = attribute_data[1].split('/')
                if len(parsed['alleles']) == 1:
                    parsed['alleles'].append(parsed['alleles'][0])
                if parsed['alleles'][0] == '-':
                    parsed['alleles'][0] = ''
                if parsed['alleles'][1] == '-':
                    parsed['alleles'][1] = ''
            if attribute_data[0] == "amino_acid":
                parsed['amino_acid'] = [attribute_data[1], attribute_data[2]]
    return parsed

def cmp_ends(pos_1, pos_2):
    """
    Find if one position ends before other, input are dicts from parseline()
    """
    if pos_1['chr'] < pos_2['chr']:
        return -1
    elif pos_1['chr'] > pos_2['chr']:
        return 1
    else:
        if pos_1['end'] < pos_2['end']:
            return -1
        elif pos_1['end'] > pos_2['end']:
            return 1
        else:
            return 0

def check_covered(genome_positions, start, end):
    for position in genome_positions:
        if position['start'] <= start:
            if position['end'] >= end:
                return True
            elif position['end'] >= start:
                start = position['end']
    return False

def genotype_region(genome_positions, twobit_ref, start, end):
    var_pos = None
    if start == end:
        # look for just one insertion position
        for position in genome_positions:
            if position['start'] == position['end'] == start:
                var_pos = position
                return position['alleles'], var_pos
        return ['-', '-'], var_pos
    else:
        genotype_1_bynuc = dict()
        genotype_2_bynuc = dict()
        for position in genome_positions:
            if position['start'] == start and position['end'] == end and not position['ref']:
                var_pos = position
                return position['alleles'], var_pos
            elif position['start'] <= start and position['end'] >= end and position['ref']:
                ref_genotype = twobit_ref[position['chr']][start:end]
                return [ref_genotype, ref_genotype], var_pos
            # Complicated position, break it down by nucleotide.
            elif position['start'] <= end and position['end'] >= start:
                if position['ref']:
                    sub_start = max(position['start'], start)
                    sub_end = min(position['end'], end)
                    sub_genotype = twobit_ref[position['chr']][sub_start:sub_end]
                    genotype_1_bynuc[sub_start] = genotype_2_bynuc[sub_start] = sub_genotype
                else:
                    var_pos = position
                    genotype_1_bynuc[position['start']] = position['alleles'][0]
                    genotype_2_bynuc[position['start']] = position['alleles'][1]
        genotype_start_keys = genotype_1_bynuc.keys()
        genotype_start_keys.sort()
        genotype_1 = genotype_2 = ''
        for loc in genotype_start_keys:
            genotype_1 = genotype_1 + genotype_1_bynuc[loc]
            genotype_2 = genotype_2 + genotype_2_bynuc[loc]
        return [genotype_1, genotype_2], var_pos

def eval_var_freq(var_positions, genome_data, twobit_ref):
    """Evaluate allele frequency at input variant position"""
    # Find union of variant regions
    chrom = var_positions[0]['chr']
    start = end = req_start = req_end = None
    for position in var_positions:
        # Require a base before and after an insertion site.
        if position['start'] == position['end']:
            if start and end and req_start and req_end:
                start = min(start, position['start'])
                end = max(start, position['end'])
                req_start = min(req_start, position['start'] - 1)
                req_end = max(req_end, position['end'] + 1)
            else:
                start = position['start']
                end = position['end']
                req_start = position['start'] - 1
                req_end = position['end'] + 1
        else:
            if start and end and req_start and req_end:
                start = min(start, position['start'])
                end = max(start, position['end'])
                req_start = min(req_start, position['start'])
                req_end = max(req_end, position['end'])
            else:
                start = req_start = position['start']
                end = req_end = position['end']
    # Check each genome for required region, get genotype in target region
    counts = dict()
    sum_counts = 0
    genotype_to_ID = dict()
    for genome_id in genome_data:
        if check_covered(genome_data[genome_id], req_start, req_end):
            ref_genotype = "-"
            if (end > start):
                ref_genotype = twobit_ref[chrom][start:end]
            genotypes, var_pos = genotype_region(genome_data[genome_id], 
                                                 twobit_ref, start, end) 
            if var_pos and 'amino_acid' in var_pos:
                for genotype in genotypes:
                    if genotype != ref_genotype:
                        genotype_to_ID[genotype] = '-'.join(var_pos['amino_acid'])
                        break
            for genotype in genotypes:
                if genotype in counts:
                    counts[genotype] += 1
                    sum_counts += 1
                else:
                    counts[genotype] = 1
                    sum_counts += 1
    for genotype in counts:
        if genotype in genotype_to_ID:
            if genotype == '':
                print '\t'.join([genotype_to_ID[genotype], chrom, str(start), str(end), '-', str(counts[genotype]), str(sum_counts)])
            else:
                print '\t'.join([genotype_to_ID[genotype], chrom, str(start), str(end), genotype, str(counts[genotype]), str(sum_counts)])

def clean_out_prior_pos(genome_data, earliest_ends):
    earliest_start = earliest_ends[0]
    for pos in earliest_ends:
        if pos['chr'] < earliest_start['chr']:
            earliest_start = pos
        elif pos['start'] < earliest_start['start']:
            earliest_start = pos
    for genome_id in genome_data:
        to_remove = []
        for position in genome_data[genome_id]:
            if position['chr'] < earliest_start['chr']:
                to_remove.append(position)
            elif position['end'] < earliest_start['start']:
                to_remove.append(position)
        for item in to_remove:
            genome_data[genome_id].remove(item)

def get_allele_freqs(password, excluded=None):
    # Set up genome input, initialize "earliest end" position.
    genome_ids = get_genome_list(password, excluded)
    genome_files = {}
    genome_data = {}
    earliest_ends = []
    to_remove = []
    for genome_id in genome_ids:
        filepath = '/home/trait/upload/' + genome_id + '-out/ns.gff.gz'
        if os.path.exists(filepath):
            genome_file = os.popen('zcat ' + filepath)
            genome_files[genome_id] = genome_file
            genome_data[genome_id] = [ parseline(genome_file.next()) ]
            if earliest_ends:
                cmp_earliest = cmp_ends(genome_data[genome_id][-1], 
                                        earliest_ends[0]) 
                if cmp_earliest < 0:
                    earliest_ends = [ genome_data[genome_id][-1] ]
                elif cmp_earliest == 0:
                    earliest_ends.append( genome_data[genome_id][-1] )
            else:
                earliest_ends = [ genome_data[genome_id][-1] ]
        else:
            # Store keys for later removal; removing now messes up the loop.
            to_remove.append(genome_id)
    for item in to_remove:
        print "Removing " + item
        genome_ids.remove(item)

    # Set up twobit reference genome
    twobit_genome = twobit.input(TWOBIT_PATH)

    # Move through the genomes to find allele frequencies
    in_genomes = True
    while in_genomes:
        # Move past current "earliest ends" & note next earliest.
        next_earliest = []
        for genome_id in genome_ids:
            if not genome_data[genome_id]:
                genome_data[genome_id].append( parseline(genome_files[genome_id].next()) )
            cmp_earliest = cmp_ends(genome_data[genome_id][-1], earliest_ends[0])
            while cmp_earliest <= 0:
                genome_data[genome_id].append( parseline(genome_files[genome_id].next()) )
                cmp_earliest = cmp_ends(genome_data[genome_id][-1], earliest_ends[0])
            if next_earliest:
                cmp_next_earliest = cmp_ends(genome_data[genome_id][-1], 
                                            next_earliest[0] )
                if cmp_next_earliest < 0:
                    next_earliest = [ genome_data[genome_id][-1] ]
                elif cmp_next_earliest == 0:
                    next_earliest.append( genome_data[genome_id][-1] )
            else:
                next_earliest = [ genome_data[genome_id][-1] ]
        # Check all the "earliest ends" positions for variants.
        has_var = []
        for position in earliest_ends:
            if not position['ref']:
                has_var.append(position)
        # If there are any, calculate allele frequency.
        if has_var:
            eval_var_freq(has_var, genome_data, twobit_genome)
        clean_out_prior_pos(genome_data, earliest_ends)
        # Reset "earliest end" to next earliest positions.
        earliest_ends = next_earliest


def main():
    # Parse options                                                                                                                                       
    usage = "%prog -p password [-x excluded_nicks_file]"
    parser = OptionParser(usage=usage)
    parser.add_option("-p", "--password", dest="password",
                      help="password for GET-Evidence MySQL database",
                      metavar="PASSWORD")
    parser.add_option("-x", "--exclude", dest="excludefile",
                      help="file containing nicks of genomes to exclude",
                      metavar="EXCLUDEFILE")
    options, args = parser.parse_args()
    # Handle input
    if options.password:
        if options.excludefile:
            get_allele_freqs(password=options.password, 
                             excluded=options.excludefile)
        else:
            get_allele_freqs(password=options.password)
    else:
        parser.print_help()

if __name__ == "__main__":
    main()
