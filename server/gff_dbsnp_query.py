#!/usr/bin/python
# Filename: gff_query_dbsnp.py

"""
usage: %prog gff_file
"""

# Append db_xref (or, for GFF3, Dbxref) attribute with dbSNP information, if available
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, sys
import MySQLdb
from utils import gff
from config import DB_HOST, DBSNP_USER, DBSNP_PASSWD, DBSNP_DATABASE

dbsnp_query = '''
SELECT snp_id FROM SNPChrPosOnRef WHERE chr=%s AND pos=%s LIMIT 1;
'''

def main():
    # return if we don't have the correct arguments
    if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))
    
    # try opening the database connection; fail if unable to open
    try:
        dbsnp_connection = MySQLdb.connect(host=DB_HOST, user=DBSNP_USER, passwd=DBSNP_PASSWD, db=DBSNP_DATABASE)
        dbsnp_cursor = dbsnp_connection.cursor()
    except MySQLdb.OperationalError, message:
        print "Error %d while connecting to database: %s" % (message[0], message[1])
        sys.exit()
    
    # now read the file and loop through
    f = gff.input(sys.argv[1])
    for record in f:
        # the database shows unplaced SNPs as having 0-based position 0
        # (i.e. 1-based position 1), so looking up position 1 would be
        # unfortunate
        if record.start == 1:
            print record
            continue
        
        if record.seqname.startswith("chr"):
            chr = record.seqname[3:]
        else:
            chr = record.seqname
        
        # recall that record.start is 1-based, but the database is not
        dbsnp_cursor.execute(dbsnp_query, (chr, record.start - 1))
        data = dbsnp_cursor.fetchone()
        
        # set the attribute if we can
        if data is not None:
            if record.version >= 3:
                record.attributes["Dbxref"] = "dbsnp:rs%s" % data[0]
            else:
                record.attributes["db_xref"] = "dbsnp:rs%s" % data[0]
        
        # output regardless
        print record
    
    # close database cursor and connection
    dbsnp_cursor.close()
    dbsnp_connection.close()

if __name__ == "__main__":
    main()
