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

import os, sys
from SimpleXMLRPCServer import SimpleXMLRPCServer as xrs
from utils import doc_optparse
from config import REFERENCE_GENOME

script_dir = os.path.dirname(sys.argv[0])

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
    
    def submit_local(genotype_file, reprocess_all=False):
        print "Calling submit_local with \'genotype_file\': \'" + str(genotype_file) + "\', \'reprocess_all\': \'" + str(reprocess_all) + "\'"

        # create output dir
        input_dir = os.path.dirname(genotype_file)
        output_dir = input_dir + "-out"
        try:
            if not os.path.exists(output_dir):
                os.makedirs(output_dir)
        except:
            print "Unexpected error:", sys.exc_info()[0]

        fetch_command = "cat"

        # letters refer to scripts; numbers refer to outputs
        args = { 'reprocess_all': reprocess_all,
             'A': os.path.join(script_dir, "gff_twobit_query.py"),
                 'B': os.path.join(script_dir, "gff_dbsnp_query_from_file.py"),
                 'C': os.path.join(script_dir, "gff_nonsynonymous_filter_from_file.py"),
                 'coverage_prog': os.path.join(script_dir, "gff_call_uncovered_json.py"),
                 'in': genotype_file,
             'fetch': fetch_command,
                 'reference': REFERENCE_GENOME,
                 '1': os.path.join(output_dir, "genotype.gff"),
                 'sorted': os.path.join(output_dir, "genotype_sorted.gff"),
                 'dbsnp_gff': os.path.join(output_dir, "genotype.dbsnp.gff"),
                 'ns_gff': os.path.join(output_dir, "ns.gff"),
                 'coverage_json': os.path.join(output_dir, "missing_coding.json"),
             'script_dir': script_dir,
             'output_dir': output_dir,
             'lockfile': os.path.join(output_dir, "lock"),
             'logfile': os.path.join(output_dir, "log")}

        cmd = '''(
        flock --nonblock --exclusive 2 || exit
        set -x
        set -e

        date 1>&2
        echo >&2 "#status 0/10 starting"

        cd '%(output_dir)s' || exit
        if [ ! -e '%(ns_gff)s' -o ! -e '%(1)s' -o '%(reprocess_all)s' != False ]
        then
            echo >&2 "#status 1 sorting input"
            %(fetch)s '%(in)s' | gzip -cdf | sort --key=1,1 --key=4n,4 | python '%(A)s' '%(reference)s' /dev/stdin | gzip -c >> '%(sorted)s'.tmp.gz
            mv '%(sorted)s'.tmp.gz '%(sorted)s'.gz

            zcat '%(sorted)s'.gz | python '%(coverage_prog)s' /dev/stdin > '%(coverage_json)s'.tmp
            mv '%(coverage_json)s'.tmp '%(coverage_json)s'

            echo >&2 "#status 2 looking up dbsnp IDs"
            zcat '%(sorted)s'.gz | perl -ne '@data=split("\\\\t"); if ($data[2] ne "REF") { print; }' | egrep 'ref_allele [-ACGTN]' | python '%(B)s' /dev/stdin > '%(dbsnp_gff)s'.tmp
            mv '%(dbsnp_gff)s'.tmp '%(dbsnp_gff)s'

            echo >&2 "#status 4 computing nsSNPs"
            python '%(C)s' '%(dbsnp_gff)s' '%(reference)s' print-all > '%(ns_gff)s'.tmp
            mv '%(ns_gff)s'.tmp '%(ns_gff)s'
        fi

        echo >&2 "#status 7 looking up GET-Evidence hits"
        for filter in get-evidence
        do
            python '%(script_dir)s'/gff_${filter}_map.py '%(ns_gff)s' > "$filter.json.tmp"
            mv "$filter.json.tmp" "$filter.json"
            date 1>&2
        done

        mv %(lockfile)s %(logfile)s
        echo >&2 "#status 10 finished"
        ) 2>>%(lockfile)s &''' % args
        os.system(cmd)
        return output_dir
    server.register_function(submit_local)

    # run the server's main loop
    server.serve_forever()

if __name__ == "__main__":
    main()
