#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

import os

UPLOAD_DIR = os.getenv('UPLOAD')
DB_HOST = "localhost"

DBSNP_B36_SORTED = "b130_SNPChrPosOnRef_36_3_sorted.bcp"
DBSNP_B37_SORTED = "b132_SNPChrPosOnRef_37_1_sorted.bcp"
REFFLAT_HG18_SORTED = "refFlat_hg18_sorted.txt"
REFFLAT_HG19_SORTED = "refFlat_hg19_sorted.txt"
KNOWNGENE_HG18_SORTED = "knownGene_hg18_sorted.txt"
KNOWNGENE_HG19_SORTED = "knownGene_hg19_sorted.txt"
GENETESTS_DATA = "genetests-data.txt"
GETEV_FLAT = "getev-latest.json.gz"

GETEVIDENCE_USER = "evidence"
GETEVIDENCE_PASSWD = "shakespeare"
GETEVIDENCE_DATABASE = "evidence"

REFERENCE_GENOME_HG18 = os.getenv('DATA') + "/hg18.2bit"
REFERENCE_GENOME_HG19 = os.getenv('DATA') + "/hg19.2bit"

WAREHOUSE_CONTROLLER = "templeton-controller:24848"
WAREHOUSE_CONFIGURL = "http://templeton-controller:44848/config.py"
