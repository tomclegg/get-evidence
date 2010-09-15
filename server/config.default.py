#!/usr/bin/python
# Filename: config.py
import os

UPLOAD_DIR = os.getenv('UPLOAD')
DB_HOST = "localhost"

DB_READ_USER = "reader"
DB_READ_PASSWD = "shakespeare"
DB_READ_DATABASE = "caliban"

DB_UPDATE_USER = "updater"
DB_UPDATE_PASSWD = "shakespeare"
DB_UPDATE_DATABASE = "caliban"

DB_WRITE_USER = "writer"
DB_WRITE_PASSWD = "shakespeare"
DB_WRITE_DATABASE = "ariel"

DBSNP_USER = "reader"
DBSNP_PASSWD = "shakespeare"
DBSNP_DATABASE = "dbsnp"

HGMD_USER = "reader"
HGMD_PASSWD = "shakespeare"
HGMD_DATABASE = "hgmd_pro"

PHARMGKB_USER = "reader"
PHARMGKB_PASSWD = "shakespeare"
PHARMGKB_DATABASE = "pharmgkb"

GETEVIDENCE_USER = "updater"
GETEVIDENCE_PASSWD = "shakespeare"
GETEVIDENCE_DATABASE = "get_evidence"

HUGENET_USER = "updater"
HUGENET_PASSWD = "shakespeare"
HUGENET_DATABASE = "hugenet"

GENOTYPE_USER = "updater"
GENOTYPE_PASSWD = "shakespeare"
GENOTYPE_DATABASE = "genotypes"

REFERENCE_GENOME = os.getenv('DATA') + "/hg18.2bit"

WAREHOUSE_CONTROLLER = "templeton-controller:24848"
WAREHOUSE_CONFIGURL = "http://templeton-controller:44848/config.py"
