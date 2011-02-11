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

import json, multiprocessing, os, random, sys, time, socket, fcntl
from SimpleXMLRPCServer import SimpleXMLRPCServer
from utils import doc_optparse
from config import DBSNP_B36_SORTED, GENETESTS_DATA, KNOWNGENE_HG18_SORTED, REFERENCE_GENOME_HG18
from config import DBSNP_B37_SORTED, KNOWNGENE_HG19_SORTED, REFERENCE_GENOME_HG19

import get_metadata, gff_call_uncovered, gff_twobit_query, gff_dbsnp_query, gff_nonsynonymous_filter, gff_getevidence_map

script_dir = os.path.dirname(sys.argv[0])

def add_to_log(log_handle, log_data):
    log_handle.write(str(log_data) + "\n")

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

    os.close(sys.stderr.fileno())
    os.close(sys.stdout.fileno())
    os.close(sys.stdin.fileno())
    os.dup2(log_handle.fileno(), sys.stderr.fileno())
    os.dup2(log_handle.fileno(), sys.stdout.fileno())

    args = { 'genotype_input': str(genotype_file),
             'coverage_out': os.path.join(output_dir, 'missing_coding.json'),
             'sorted_out': os.path.join(output_dir, 'source_sorted.gff.gz'),
             'nonsyn_out': os.path.join(output_dir, 'ns.gff'),
             'getev_out': os.path.join(output_dir, 'get-evidence.json'),
             'metadata_out': os.path.join(output_dir, 'metadata.json'),
             'genome_stats': os.path.join(script_dir, 'genome_stats.txt'),
             'genetests': os.path.join(os.getenv('DATA'), GENETESTS_DATA) }
    start_time = time.time()
    # Make output directory if needed
    try:
        if not os.path.exists(output_dir):
            os.makedirs(output_dir)
    except:
        print "Unexpected error:", sys.exc_info()[0]

    # Sort input genome data
    add_to_log(log_handle, "#status 0/10 starting (time = %.2f seconds)" % (time.time() - start_time) )
    add_to_log(log_handle, "#status 1 sorting input (time = %.2f seconds)" % (time.time() - start_time) )
    sort_source_cmd = '''(
cat '%(genotype_input)s' | gzip -cdf | egrep "^#";
cat '%(genotype_input)s' | gzip -cdf | egrep -v "^#" | sort --key=1,1 --key=4n,4
) | gzip -c > '%(sorted_out)s' ''' % args
    os.system(sort_source_cmd)

    # Get metadata from whole genome
    add_to_log(log_handle, "#status 2 getting metadata (time = %.2f seconds)" % (time.time() - start_time) )
    genome_data = get_metadata.genome_metadata(args['sorted_out'], args['genome_stats'])
    # Print metadata because we're impatient for stats
    f = open(args['metadata_out'], 'w')
    f.write(json.dumps(genome_data) + "\n")
    f.close()

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

    # Report of uncovered blocks in coding
    add_to_log(log_handle, "#status 3 report uncovered coding (time = %.2f seconds)" % (time.time() - start_time) )
    if (genome_data['has_ref']):
        gff_call_uncovered.report_uncovered_to_file(args['sorted_out'], args['transcripts'], args['genetests'], args['coverage_out'])
        exome_data = get_metadata.coding_metadata(args['coverage_out'], genome_data['chromosomes'])
        for key in exome_data.keys():
            genome_data[key] = exome_data[key]
    # Print metadata again
    f = open(args['metadata_out'], 'w')
    f.write(json.dumps(genome_data) + "\n")
    f.close()

    # Generator chaining...
    # Get reference alleles for non-reference variants
    twobit_gen = gff_twobit_query.match2ref(args['sorted_out'], args['reference'])
    # Look up dbSNP IDs
    dbsnp_gen = gff_dbsnp_query.match2dbSNP(twobit_gen, args['dbsnp'])
    # Check for nonsynonymous SNP
    nonsyn_gen = gff_nonsynonymous_filter.predict_nonsynonymous(dbsnp_gen, args['reference'], args['transcripts'])

    ns_out = open(args['nonsyn_out'], 'w')
    for line in nonsyn_gen:
        ns_out.write(line + "\n")
    ns_out.close()

    # Match against GET-Evidence database
    add_to_log(log_handle,"#status 8 looking up GET-Evidence hits (time = %.2f seconds)" % (time.time() - start_time) )
    gff_getevidence_map.match_getev_to_file(args['nonsyn_out'], args['getev_out'] + ".tmp")
    # Using .tmp because this is slow to generate & is used for the genome report web page display
    os.system("mv " + args['getev_out'] + ".tmp " + args['getev_out'])

    add_to_log(log_handle,"#status 10 Done, cleaning up! (time = %.2f seconds)" % (time.time() - start_time) )

    os.rename(lockfile, logfile)
    log_handle.close()
    print "Done processing " + str(genotype_file)


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
