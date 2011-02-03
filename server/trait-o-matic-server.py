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

import multiprocessing, os, random, sys, time
from SimpleXMLRPCServer import SimpleXMLRPCServer as xrs
from utils import doc_optparse
from config import DBSNP_SORTED, KNOWNGENE_SORTED, REFERENCE_GENOME

import gff_call_uncovered, gff_twobit_query, gff_dbsnp_query, gff_nonsynonymous_filter, gff_getevidence_map

script_dir = os.path.dirname(sys.argv[0])

def add_to_log(filename, log_data):
    log = open(filename, 'a')
    log.write(str(log_data) + "\n")
    log.close()

def genome_analyzer(genotype_file):  
    # Set all the variables we'll use.
    input_dir = os.path.dirname(genotype_file)
    output_dir = input_dir + "-out"
    lockfile = os.path.join(output_dir, "lock")
    logfile = os.path.join(output_dir, "log")
    temp_prefix = 'temp' + str(hex(random.randint(4096,65535)))[2:] + "_"
    args = { 'genotype_input': str(genotype_file),
                'coverage_out': os.path.join(output_dir, 'missing_coding.json'),
                'sorted_out': os.path.join(output_dir, 'source_sorted.gff.gz'),
                'getref_out': os.path.join(output_dir, temp_prefix + 'twobit.gff'),
                'dbsnp_out': os.path.join(output_dir, temp_prefix + 'dbsnp.gff'),
                'nonsyn_out': os.path.join(output_dir, 'ns.gff'),
                'getev_out': os.path.join(output_dir, 'get-evidence.json'),
            'dbsnp': os.path.join(os.getenv('DATA'), DBSNP_SORTED),
                'reference': os.path.join(os.getenv('DATA'), REFERENCE_GENOME),
                'transcripts': os.path.join(os.getenv('DATA'), KNOWNGENE_SORTED) }
    start_time = time.time()
    # Make output directory if needed
    try:
        if not os.path.exists(output_dir):
            os.makedirs(output_dir)
    except:
        print "Unexpected error:", sys.exc_info()[0]

    # Sort input genome data
    add_to_log(lockfile, "#status 0/10 starting (time = %.2f seconds)" % (time.time() - start_time) )
    add_to_log(lockfile, "#status 1 sorting input (time = %.2f seconds)" % (time.time() - start_time) )
    sort_source_cmd = '''cat '%(genotype_input)s' | gzip -cdf | grep "^#" | gzip -c > '%(sorted_out)s' 
                            cat '%(genotype_input)s' | gzip -cdf | grep -v "^#" | sort --key=1,1 --key=4n,4 | \
                                gzip -c >> '%(sorted_out)s' ''' % args
    os.system(sort_source_cmd)

    # Report of uncovered blocks in coding
    add_to_log(lockfile, "#status 2 report uncovered coding (time = %.2f seconds)" % (time.time() - start_time) )
    gff_call_uncovered.report_uncovered_to_file(args['sorted_out'], args['transcripts'], args['coverage_out'])

    # Get reference alleles for non-reference variants
    add_to_log(lockfile, "#status 3 looking up reference alleles (time = %.2f seconds)" % (time.time() - start_time) )
    gff_twobit_query.match2ref_to_file(args['sorted_out'], args['reference'], args['getref_out'])

    # Look up dbSNP IDs
    add_to_log(lockfile, "#status 4 looking up dbsnp IDs (time = %.2f seconds)" % (time.time() - start_time) )
    gff_dbsnp_query.match2dbSNP_to_file(args['getref_out'], args['dbsnp'], args['dbsnp_out'])

    # Check for nonsynonymous SNPs
    add_to_log(lockfile, "#status 6 computing nsSNPs (time = %.2f seconds)" % (time.time() - start_time) )
    gff_nonsynonymous_filter.predict_nonsynonymous_to_file(args['dbsnp_out'], args['reference'], args['transcripts'], args['nonsyn_out'])

    # Match against GET-Evidence database
    add_to_log(lockfile,"#status 8 looking up GET-Evidence hits (time = %.2f seconds)" % (time.time() - start_time) )
    gff_getevidence_map.match_getev_to_file(args['nonsyn_out'], args['getev_out'] + ".tmp")
    # Using .tmp because this is slow to generate & is used for the genome report web page display
    os.system("mv " + args['getev_out'] + ".tmp " + args['getev_out'])

    add_to_log(lockfile,"#status 10 Done, cleaning up! (time = %.2f seconds)" % (time.time() - start_time) )

    os.remove(args['getref_out'])
    os.remove(args['dbsnp_out'])
    os.rename(lockfile, logfile)
    print "Done processing " + str(genotype_file)


def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)
    
    if option.stderr:
        sys.stdout = open(option.stderr,'a+',0)
        sys.stderr = open(option.stderr,'a+',0)

    if option.pidfile:
        file(option.pidfile,'w+').write("%d\n" % os.getpid())

    # figure out the host and port
    host = option.host or "localhost"
    port = int(option.port or 8080)
    
    # create server
    server = xrs((host, port))
    server.register_introspection_functions()
    
    def submit_local(genotype_file):
        p = multiprocessing.Process(target=genome_analyzer, args=(genotype_file,))
        p.start()
        print "Job submitted for genotype_file: \'" + str(genotype_file) + "\', process ID: \'" + str(p.pid) + "\'"

    server.register_function(submit_local)

    # run the server's main loop
    server.serve_forever()

if __name__ == "__main__":
    main()
