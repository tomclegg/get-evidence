#!/usr/bin/python
# Filename: server.py

"""
usage: %prog [options]
  --pidfile=PATH: location of pid file
  --stderr=PATH: location of log file
  -h, --host=STRING: the host on which to listen
  -p, --port=NUMBER: the port on which to listen
"""

# Start an XMLRPC server for Trait-o-matic
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import json, multiprocessing, os, random, sys, time, socket, fcntl, gzip
from SimpleXMLRPCServer import SimpleXMLRPCServer
from utils import doc_optparse
from config import GENETESTS_DATA, GETEV_FLAT
from config import DBSNP_B36_SORTED, KNOWNGENE_HG18_SORTED, REFERENCE_GENOME_HG18
from config import DBSNP_B37_SORTED, KNOWNGENE_HG19_SORTED, REFERENCE_GENOME_HG19
from progresstracker import Logger, ProgressTracker

import get_metadata, gff_call_uncovered, gff_twobit_query, gff_dbsnp_query, gff_nonsynonymous_filter, gff_getevidence_map

script_dir = os.path.dirname(sys.argv[0])

def genome_analyzer(server, genotype_file):
    server.server_close()
    # Set all the variables we'll use.
    input_dir = os.path.dirname(genotype_file)
    output_dir = input_dir + "-out"
    try:
        os.mkdir (output_dir)
    except OSError:
        pass
    lockfile = os.path.join(output_dir, "lock")
    logfile = os.path.join(output_dir, "log")
    log_handle = open(lockfile, "a+", 0)
    try:
        fcntl.flock(log_handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except:
        print 'Lockfile really is locked.  Quitting.'
        return
    log_handle.seek(0)
    log_handle.truncate(0)
    log = Logger(log_handle)

    os.close(sys.stderr.fileno())
    os.close(sys.stdout.fileno())
    os.close(sys.stdin.fileno())
    os.dup2(log_handle.fileno(), sys.stderr.fileno())
    os.dup2(log_handle.fileno(), sys.stdout.fileno())

    # Set up arguments used by processing commands and scripts.
    # TODO: Fix getev_flat so it's stored somewhere more consistent with the
    # other data files. Probably "make daily" stuff should be going to 'DATA'
    # instead or in addition to public_html, but www-data would need to own it.
    args = { 'genotype_input': str(genotype_file),
             'miss_out': os.path.join(output_dir, 'missing_coding.json'),
             'sorted_out': os.path.join(output_dir, 'source_sorted.gff.gz'),
             'nonsyn_out': os.path.join(output_dir, 'ns.gff.gz'),
             'getev_out': os.path.join(output_dir, 'get-evidence.json'),
             'metadata_out': os.path.join(output_dir, 'metadata.json'),
             'genome_stats': os.path.join(script_dir, 'genome_stats.txt'),
             'genetests': os.path.join(os.getenv('DATA'), GENETESTS_DATA),
             'getev_flat': os.path.join(os.getenv('CORE'), '../public_html/', GETEV_FLAT) }

    # Make output directory if needed
    try:
        if not os.path.exists(output_dir):
            os.makedirs(output_dir)
    except:
        print "Unexpected error:", sys.exc_info()[0]

    # Sort input genome data
    log.put ('#status 0/100 sorting input file')
    sort_source_cmd = '''(
cat '%(genotype_input)s' | gzip -cdf | egrep "^#";
cat '%(genotype_input)s' | gzip -cdf | egrep -v "^#" | sort --buffer-size=20%% --key=1,1 --key=4n,4
) | gzip -c > '%(sorted_out)s' ''' % args
    os.system(sort_source_cmd)

    # Get header metadata from whole genome before processing (to get build)
    genome_data = get_metadata.header_data(args['sorted_out'], check_ref=100)

    # Set up build-dependent file locations
    if (genome_data['build'] == "b36"):
        args['dbsnp'] = os.path.join(os.getenv('DATA'), DBSNP_B36_SORTED)
        args['reference'] = os.path.join(os.getenv('DATA'), REFERENCE_GENOME_HG18)
        args['transcripts'] = os.path.join(os.getenv('DATA'), KNOWNGENE_HG18_SORTED)
    elif (genome_data['build'] == "b37"):
        args['dbsnp'] = os.path.join(os.getenv('DATA'), DBSNP_B37_SORTED)
        args['reference'] = os.path.join(os.getenv('DATA'), REFERENCE_GENOME_HG19)
        args['transcripts'] = os.path.join(os.getenv('DATA'), KNOWNGENE_HG19_SORTED)
    else:
        raise Exception("genome build data is invalid")

    # It might be more elegant to extract this from metadata.
    chrlist = map (lambda x: 'chr'+str(x), range(1,22)+['X','Y'])

    # Process genome through a series of GFF-formatted string generators.
    log.put('#status 4 processing genome data (get reference alleles, '
            + 'dbSNP IDs, nonsynonymous changes, etc.)')
    pt = ProgressTracker(log_handle, [5,99], expected=chrlist, 
                         metadata=genome_data)
    # Record chromosomes seen and genome coverage.
    metadata_gen = get_metadata.genome_metadata(args['sorted_out'],
                                                args['genome_stats'],
                                                progresstracker=pt
                                                )
    # Report coding regions that lack coverage.
    uncov_gen = gff_call_uncovered.report_uncovered(metadata_gen,
                                                    args['transcripts'], 
                                                    args['genetests'], 
                                                    output_file=args['miss_out']
                                                    )
    # Find reference allele.
    twobit_gen = gff_twobit_query.match2ref(uncov_gen, args['reference'])
    # Look up dbSNP IDs
    dbsnp_gen = gff_dbsnp_query.match2dbSNP(twobit_gen, args['dbsnp'])
    # Check for nonsynonymous SNP
    nonsyn_gen = gff_nonsynonymous_filter.predict_nonsynonymous(dbsnp_gen, args['reference'], args['transcripts'])
    # Pull off GET-Evidence hits
    nonsyn_gen2 = gff_getevidence_map.match_getev(nonsyn_gen, args['getev_flat'], output_file=args['getev_out'] + ".tmp", progresstracker=pt)

    # Printing to output, pulls data through the generator chain.
    ns_out = gzip.open(args['nonsyn_out'], 'w')
    for line in nonsyn_gen2:
        ns_out.write(line + "\n")
    ns_out.close()
    os.system("mv " + args['getev_out'] + ".tmp " + args['getev_out'])

    # Print metadata
    metadata_f_out = open(args['metadata_out'], 'w')
    pt.write_metadata(metadata_f_out)
    metadata_f_out.close()

    log.put ('#status 100 finished')

    os.rename(lockfile, logfile)
    log_handle.close()
    print "Finished processing file " + str(genotype_file)


def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)
    
    if option.stderr:
        errout = open(option.stderr,'a+',0)
        os.dup2 (errout.fileno(), sys.stdout.fileno())
        os.dup2 (errout.fileno(), sys.stderr.fileno())

    if option.pidfile:
        file(option.pidfile,'w+').write("%d\n" % os.getpid())

    # figure out the host and port
    host = option.host or "localhost"
    port = int(option.port or 8080)
    
    # create server
    server = SimpleXMLRPCServer((host, port))
    server.register_introspection_functions()
    
    def submit_local(genotype_file):
        p = multiprocessing.Process(target=genome_analyzer, args=(server,genotype_file,))
        p.start()
        print "Job submitted for genotype_file: \'" + str(genotype_file) + "\', process ID: \'" + str(p.pid) + "\'"
        return str(p.pid)

    server.register_function(submit_local)

    # run the server's main loop
    try:
        server.serve_forever()
    except:
        server.server_close()

if __name__ == "__main__":
    main()
