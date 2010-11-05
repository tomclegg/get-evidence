#!/usr/bin/python
# Filename: config.py
import os

UPLOAD_DIR = os.getenv('UPLOAD')
DB_HOST = "localhost"

DBSNP_SORTED = "b130_SNPChrPosOnRef_36_3_sorted.bcp"
REFFLAT_SORTED = "refFlat_sorted.txt"

GETEVIDENCE_USER = "evidence"
GETEVIDENCE_PASSWD = "shakespeare"
GETEVIDENCE_DATABASE = "evidence"

REFERENCE_GENOME = os.getenv('DATA') + "/hg18.2bit"

WAREHOUSE_CONTROLLER = "templeton-controller:24848"
WAREHOUSE_CONFIGURL = "http://templeton-controller:44848/config.py"
