#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

import re
import os
import sys
import MySQLdb, MySQLdb.cursors
from optparse import OptionParser
import simplejson as json
from utils import autozip

DB_HOST = 'localhost'
GETEVIDENCE_USER = 'evidence'
GETEVIDENCE_DATABASE = 'evidence'
GENOME_USER_OIDS = ['https://profiles.google.com/PGP.uploader']

def read_metadata(genome_id):
    """Open file containing metadata, return it"""
    metadata_path = '/home/trait/upload/' + genome_id + '-out/metadata.json'
    f_meta = autozip.file_open(metadata_path)
    metadata = json.loads(f_meta.next())
    f_meta.close()
    return metadata

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

def read_single_items(filename):
    """Read a file containing strings to be matched on for excluding genomes"""
    f_in = open(filename)
    items = []
    for line in f_in:
        item = line.strip()
        if len(item) > 0:
            items.append(item)
    return items

def move_gff_ahead(gff_in, gff_lookahead):
    gff_currdata = gff_lookahead
    try:
        gff_lookahead = gff_in.next().split()
    except StopIteration:
        gff_lookahead = None
    while ( gff_lookahead and (gff_lookahead[0] == gff_currdata[0]) and
            (int(gff_lookahead[3]) - 1) <= int(gff_currdata[4]) ):
        if int(gff_currdata[4]) < int(gff_lookahead[4]):
            gff_currdata[4] = gff_lookahead[4]
        try:
            gff_lookahead = gff_in.next().split()
        except StopIteration:
            gff_lookahead = None
    if gff_currdata and int(gff_currdata[4]) == int(gff_currdata[3]) - 1:
        gff_currdata, gff_lookahead = move_gff_ahead(gff_in, gff_lookahead)
    return gff_currdata, gff_lookahead

def add_coverage(shasum, coveragefile):
    metadata = read_metadata(shasum)
    if (not metadata or not 'genome_build' in metadata or 
        metadata['genome_build'] != 'b36'):
        return coveragefile

    coverage_in = autozip.file_open(coveragefile)
    gff_in = autozip.file_open('/home/trait/upload/' + shasum + '-out/ns.gff.gz')
    covdir, covfile = os.path.split(coveragefile)
    covfile_pre = covfile
    if re.match('(.*)\.gz', covfile):
        covfile_pre = re.match('(.*)\.gz', covfile).groups()[0]
    coverage_out_path = os.path.join(covdir, covfile_pre + '_' + shasum[0:6] + '.gz')
    coverage_out = autozip.file_open(coverage_out_path, 'w')

    cov_header = coverage_in.next().rstrip().split()
    coverage_out.write(' '.join(cov_header + [shasum]) + '\n')

    gff_lookahead = gff_in.next().split()
    while gff_lookahead and re.match('#', gff_lookahead[0]):
        gff_lookahead = gff_in.next().split()
    gff_currdata, gff_lookahead = move_gff_ahead(gff_in, gff_lookahead)

    coverage_currdata = coverage_in.next().split()
    cov_blank = ['0' for x in coverage_currdata[3:]]

    while coverage_currdata or gff_currdata:
        # Skip data that are zero or negative (??) coverage
        if (gff_currdata and 
            int(gff_currdata[4]) - (int(gff_currdata[3]) - 1) <= 0):
            gff_currdata, gff_lookahead = move_gff_ahead(gff_in, gff_lookahead)
            continue
        if (coverage_currdata and
            int(coverage_currdata[2]) - int(coverage_currdata[1]) <= 0):
            try:
                coverage_currdata = coverage_in.next().split()
            except StopIteration:
                coverage_currdata = None
            continue
        # If coverage file is done, output GFF line
        if not coverage_currdata:
            output = [gff_currdata[0]] + gff_currdata[3:5] + cov_blank + ['1']
            coverage_out.write(' '.join(output) + '\n')
            gff_currdata, gff_lookahead = move_gff_ahead(gff_in, gff_lookahead)
            continue
        # If GFF file is done, output coverage file line
        if not gff_currdata:
            output = coverage_currdata + ['0']
            coverage_out.write(' '.join(output) + '\n')
            try:
                coverage_currdata = coverage_in.next().split()
            except StopIteration:
                coverage_currdata = None
            continue
        # If they aren't on the same chromosome, move one of them forward.
        if coverage_currdata[0] != gff_currdata[0]:
            if coverage_currdata[0] < gff_currdata[0]:
                output = coverage_currdata + ['0']
                coverage_out.write(' '.join(output) + '\n')
                try:
                    coverage_currdata = coverage_in.next().split()
                except StopIteration:
                    coverage_currdata = None
            else:
                output = ([gff_currdata[0]] + [str(int(gff_currdata[3]) - 1)] + 
                          [gff_currdata[4]] + cov_blank + ['1'])
                coverage_out.write(' '.join(output) + '\n')
                gff_currdata, gff_lookahead = move_gff_ahead(gff_in, gff_lookahead)
            continue
        # If we get here, we have both files & both are on the same chrom
        if int(coverage_currdata[1]) < (int(gff_currdata[3]) - 1):
            # Coverage file start is before GFF start.
            if int(coverage_currdata[2]) <= (int(gff_currdata[3]) - 1):
                # Whole coverage file data is before GFF line.
                output = coverage_currdata + ['0']
                coverage_out.write(' '.join(output) + '\n')
                try:
                    coverage_currdata = coverage_in.next().split()
                except StopIteration:
                    coverage_currdata = None
            else:
                # Print uncovered up to the GFF start.
                output = (coverage_currdata[0:2] + 
                          [str(int(gff_currdata[3]) - 1)] + 
                          coverage_currdata[3:] + ['0'])
                coverage_out.write(' '.join(output) + '\n')
                coverage_currdata[1] = str(int(gff_currdata[3]) - 1)
                if int(coverage_currdata[2]) <= int(coverage_currdata[1]):
                    try:
                        coverage_currdata = coverage_in.next().split()
                    except StopIteration:
                        coverage_currdata = None
        elif int(coverage_currdata[1]) > (int(gff_currdata[3]) - 1):
            # GFF start is before coverage file start.
            if int(coverage_currdata[1]) > int(gff_currdata[4]):
                # Whole GFF file data is before coverage file data
                output = ([gff_currdata[0]] + [str(int(gff_currdata[3]) - 1)] +
                          [gff_currdata[4]] + cov_blank + ['1'])
                coverage_out.write(' '.join(output) + '\n')
                gff_currdata, gff_lookahead = move_gff_ahead(gff_in, gff_lookahead)
            else:
                # Print uncovered GFF up to coverage file start
                output = ([gff_currdata[0]] + [str(int(gff_currdata[3]) - 1)] +
                          [coverage_currdata[1]] + cov_blank + ['1'])
                coverage_out.write(' '.join(output) + '\n')
                gff_currdata[3] = str(int(coverage_currdata[1]) + 1)
        else:
            # Coverage file and GFF data have same start.
            if int(coverage_currdata[2]) < int(gff_currdata[4]):
                # Coverage file ends first: output, update GFF, advance coverage
                output = coverage_currdata + ['1']
                coverage_out.write(' '.join(output) + '\n')
                gff_currdata[3] = str(int(coverage_currdata[2]) + 1)
                try:
                    coverage_currdata = coverage_in.next().split()
                except StopIteration:
                    coverage_currdata = None
            elif int(coverage_currdata[2]) > int(gff_currdata[4]):
                # GFF ends first: output, update coverage, advance GFF
                output = (coverage_currdata[0:2] + [gff_currdata[4]] + 
                          coverage_currdata[3:] + ['1'])
                coverage_out.write(' '.join(output) + '\n')
                coverage_currdata[1] = gff_currdata[4]
                if int(coverage_currdata[2]) <= int(coverage_currdata[1]):
                    try:
                        coverage_currdata = coverage_in.next().split()
                    except StopIteration:
                        coverage_currdata = None
                gff_currdata, gff_lookahead = move_gff_ahead(gff_in, gff_lookahead)
            else:
                # Both end at the same point: Output and advance both.
                output = coverage_currdata + ['1']
                coverage_out.write(' '.join(output) + '\n')
                try:
                    coverage_currdata = coverage_in.next().split()
                except StopIteration:
                    coverage_currdata = None
                gff_currdata, gff_lookahead = move_gff_ahead(gff_in, gff_lookahead)
    coverage_out.close()
    gff_in.close()
    coverage_in.close()
    return coverage_out_path


def main():
    # Parse options
    usage = "\n%prog -c basecovfile -p password [-x excludednicksfile]"
    parser = OptionParser(usage=usage)
    parser.add_option("-c", "--coverage", dest="basecovfile",
                      help="base hg18 coverage file", metavar="COVFILE")
    parser.add_option("-p", "--password", dest="password",
                      help="password for GET-Evidence MySQL database",
                      metavar="PASSWORD")
    parser.add_option("-x", "--exclude", dest="excludefile",
                      help="file containing nicks of genomes to exclude",
                      metavar="EXCLUDEFILE")
    options, args = parser.parse_args()
    if options.password and options.basecovfile:
        if options.excludefile:
            shasums = get_genome_list(options.password, options.excludefile)
        else:
            shasums = get_genome_list(options.password)
        covfile = options.basecovfile
        for shasum in shasums:
            print "Examining " + shasum
            newcovfile = add_coverage(shasum, covfile)
            covfile = newcovfile
    else:
        print "Run with -h to see options"

if __name__ == "__main__":
    main()
