#!/usr/bin/python
# Filename: json_allele_frequency_query.py

"""
usage: %prog json ... [options]
  -i, --in-place: move input into a backup file and write output in its place
"""

# Output allele frequency information in JSON format, if available
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import fileinput, os, string, sys
import MySQLdb
import simplejson as json
from utils import doc_optparse
from utils.biopython_utils import reverse_complement
from config import DB_HOST, DB_READ_USER, DB_READ_PASSWD, DB_READ_DATABASE

query = '''
SELECT strand, pop, ref_allele, ref_allele_freq, oth_allele, oth_allele_freq, ref_allele_count, oth_allele_count FROM hapmap27 WHERE
chr=%s AND start=%s AND end=%s;
'''

def main():
    # parse options
    option, args = doc_optparse.parse(__doc__)
    
    if len(args) < 1:
        doc_optparse.exit()
    
    # first, try to connect to the databases
    try:
        connection = MySQLdb.connect(host=DB_HOST, user=DB_READ_USER, passwd=DB_READ_PASSWD, db=DB_READ_DATABASE)
        cursor = connection.cursor()
    except MySQLdb.OperationalError, message:
        print "Error %d while connecting to database: %s" % (message[0], message[1])
        sys.exit()
    
    for line in fileinput.input(args, inplace=option.in_place):
        l = json.loads(line.strip())
        
        # sanity check--should always pass
        if not ("chromosome" in l or "coordinates" in l):
            continue
        
        # we can't handle variants longer than 1 bp
        unavailable = True
        if not "-" in "coordinates":
            chr = l["chromosome"]
            start = int(l["coordinates"]) - 1
            end = int(l["coordinates"])
            
            # get allele frequency data based on coordinates
            cursor.execute(query, (chr, start, end))
            data = cursor.fetchall()
            
            # move on if we don't have info
            if cursor.rowcount > 0:
                unavailable = False
        
        if unavailable:
            l["maf"] = "N/A"
            if "trait_allele" in l:
                l["taf"] = "N/A"
        else:
            # output minor allele frequency as a dictionary, with population abbrs as keys
            l["maf"] = dict(zip([d[1] for d in data], [float(min(d[3], d[5])) for d in data]))
            # output trait allele frequency as a dictionary; this one is a little trickier
            if "trait_allele" in l:
                l["taf"] = {"all_n": 0,
                        "all_d": 0}
                for d in data:
                    if d[0] == "+" and l["trait_allele"] == d[2] \
                      or d[0] == "-" and l["trait_allele"] == reverse_complement(d[2]):
                        l["taf"][d[1]] = float(d[3])
                        l["taf"]["all_n"] += d[6]
                    elif d[0] == "+" and l["trait_allele"] == d[4] \
                      or d[0] == "-" and l["trait_allele"] == reverse_complement(d[4]):
                        l["taf"][d[1]] = float(d[5])
                        l["taf"]["all_n"] += d[7]
                    l["taf"]["all_d"] += d[6]+d[7]
        print json.dumps(l)
    
    # close database cursor and connection
    cursor.close()
    connection.close()

if __name__ == "__main__":
    main()
