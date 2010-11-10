#!/usr/bin/python

"""
usage: %prog gwas_csv_file
"""

# Import HugeNET GWAS information (CSV) to database
# ---
# This code is part of the Trait-o-matic project and is governed by its license.

import os, string, sys, warnings
import MySQLdb
from config import DB_HOST, HUGENET_USER, HUGENET_PASSWD, HUGENET_DATABASE

# return if we don't have the correct arguments
if len(sys.argv) < 2:
        raise SystemExit(__doc__.replace("%prog", sys.argv[0]))

# try to connect to the database
try:
        connection = MySQLdb.connect(host=DB_HOST, user='installer', passwd=HUGENET_PASSWD, db=HUGENET_DATABASE)
        cursor = connection.cursor()
except MySQLdb.OperationalError, message:
        print "Error %d while connecting to database: %s" % (message[0], message[1])
        sys.exit()

warnings.filterwarnings("ignore", "Unknown table.*")

connection.cursor().execute('''create temporary table hng (
rsids_region varchar(255),
gene varchar(128),
gene_region varchar(32),
trait varchar(128),
firstauthor varchar(255),
journal varchar(128),
published_year varchar(16),
pubmed_id varchar(16),
sample_sizes varchar(255),
risk_allele_prevalence varchar(64),
or_or_beta_ci varchar(64),
p_value varchar(64),
platform varchar(64),
or_or_beta_is_or char(1)
)
''')
print 'loading data...',
print connection.cursor().execute('''load data local infile %s into table hng fields terminated by ',' optionally enclosed by '"' lines terminated by '\n' ignore 1 lines''', (os.path.realpath(sys.argv[1])))

print 'splitting multi-dbsnp rows: 4...',
connection.cursor().execute('''create temporary table hng2 like hng''')
print connection.cursor().execute('''insert into hng2 select substring_index(rsids_region,',',-4),gene,gene_region,trait,firstauthor,journal,published_year,pubmed_id,sample_sizes,risk_allele_prevalence,or_or_beta_ci,p_value,platform,or_or_beta_is_or from hng where rsids_region like '%,%,%,%,%' '''),

print '   3...',
print connection.cursor().execute('''insert into hng2 select substring_index(rsids_region,',',-3),gene,gene_region,trait,firstauthor,journal,published_year,pubmed_id,sample_sizes,risk_allele_prevalence,or_or_beta_ci,p_value,platform,or_or_beta_is_or from hng where rsids_region like '%,%,%,%' '''),

print '   2...',
print connection.cursor().execute('''insert into hng2 select substring_index(rsids_region,',',-2),gene,gene_region,trait,firstauthor,journal,published_year,pubmed_id,sample_sizes,risk_allele_prevalence,or_or_beta_ci,p_value,platform,or_or_beta_is_or from hng where rsids_region like '%,%,%' '''),

print '   1...',
print connection.cursor().execute('''insert into hng2 select substring_index(rsids_region,',',-1),gene,gene_region,trait,firstauthor,journal,published_year,pubmed_id,sample_sizes,risk_allele_prevalence,or_or_beta_ci,p_value,platform,or_or_beta_is_or from hng where rsids_region like '%,%' '''),

print ''
print 'copying back to hng...',
print connection.cursor().execute('''insert into hng select * from hng2''')

print 'stripping all but first rsid...',
print connection.cursor().execute('''update hng set rsids_region=substring_index(rsids_region,',',1)''')

print 'stripping "(foo)" and leading space from rsid...',
print connection.cursor().execute('''update hng set rsids_region=substring_index(rsids_region,'(',1)'''),
print '...',
print connection.cursor().execute('''update hng set rsids_region=substring(rsids_region,2) where rsids_region like ' %' ''')

print 'changing "null" rsid -> NULL...',
print connection.cursor().execute('''update hng set rsids_region=NULL where rsids_region in ('null','NR','NA') ''')

print 'converting rsid to numeric...',
print connection.cursor().execute('''alter table hng change rsids_region rsid varchar(16)''')

connection.cursor().execute ('drop table if exists hugenet_gwas')
print 'copying to live table...',
print connection.cursor().execute ('create table hugenet_gwas as select * from hng')

print 'indexing...',
print connection.cursor().execute ('alter table hugenet_gwas add index `rsid_index` (rsid)')
