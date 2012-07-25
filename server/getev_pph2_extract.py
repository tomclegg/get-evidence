#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""Extract scores from Polyphen 2 1.9GB data dump for GET-Evidence variants"""
import re
import tarfile
import simplejson as json
from optparse import OptionParser
from utils import autozip

def process_kgwname(kgwname_file):
    """Return dict linking UCSC IDs to gene names from first column"""
    ucsc_to_name = dict()
    f_in = autozip.file_open(kgwname_file, 'r')
    for line in f_in:
        data = line.split()
        ucsc_to_name[data[1]] = data[0]
    f_in.close()
    return ucsc_to_name

def process_kgxref(kgxref_file, ucsc_to_name):
    """Find and return one-to-one Uniprot ID / gene name mapping

    Using kgXref and our own gene name mappings, some gene names appear to
    correspond to more than one Uniprot ID (100 in hg18) and some Uniprot IDs
    appear to correspond to more than one gene name (51 in hg18). Because these
    are such a small fraction, we remove them and return the one-to-one mapping
    (18,453 in hg18) as a dict where both are keys (e.g. 36,906 keys for hg18).
    """
    name_to_uniprot = dict()
    uniprot_to_name = dict()
    name_unique = dict()   
    uniprot_unique = dict()
    f_in = autozip.file_open(kgxref_file, 'r')
    for line in f_in:
        data = line.rstrip('\n').split('\t')
        if data[2] and data[0] in ucsc_to_name:
            genename = ucsc_to_name[data[0]]
            uniprotname = data[2]
            if re.match(r'(.*?)-', data[2]):
                uniprotname = re.match(r'(.*?)-', data[2]).group(1)
            if genename in name_to_uniprot:
                if not name_to_uniprot[genename] == uniprotname:
                    name_unique[genename] = False
            else:
                name_to_uniprot[genename] = uniprotname
                name_unique[genename] = True
            if uniprotname in uniprot_to_name:
                if not uniprot_to_name[uniprotname] == genename:
                    uniprot_unique[uniprotname] = False
            else:
                uniprot_to_name[uniprotname] = genename
                uniprot_unique[uniprotname] = True
    final_dict = dict()
    for key in uniprot_to_name:
        if uniprot_unique[key] and name_unique[uniprot_to_name[key]]:
            final_dict[key] = uniprot_to_name[key]
    return final_dict

def match_getev_pph2(getev_file, pph2_file):
    # Use these files to create a link between uniprot ID and gene name.
    kgwname_file = '/home/trait/data/knownGene_hg18_sorted.txt'
    kgxref_file = '/home/trait/data/kgXref_hg18.txt.gz'
    ucsc_to_name = process_kgwname(kgwname_file)
    uniprot_to_genename = process_kgxref(kgxref_file, ucsc_to_name)

    # Read GET-Evidence flatfile
    getev_variants = dict()
    if isinstance(getev_file, str):
        getev_in = autozip.file_open(getev_file)
    else:
        getev_in = getev_file
    for line in getev_in:
        getev_data = json.loads(line)
        if 'gene' in getev_data and 'aa_change_short' in getev_data:
            gene_aachange_key = (getev_data['gene'] + '-' + 
                                 getev_data['aa_change_short'])
            getev_variants[gene_aachange_key] = getev_data['variant_id']
    getev_in.close()


    # Read Polyphen 2 data and return scores for GET-Ev variants
    pph2_tar = tarfile.open(name=pph2_file, mode='r:bz2')
    for taritem in pph2_tar:
        if re.match('pph2_whpss/(.*)\.pph2\.txt', str(taritem.name)):
            uniprot = re.match('pph2_whpss/(.*)\.pph2\.txt', taritem.name).group(1)
            if uniprot in uniprot_to_genename:
                gene = uniprot_to_genename[uniprot]
                pph2_genedata = pph2_tar.extractfile(taritem)
                for line in pph2_genedata:
                    pph2_data = re.split(' *\t *', line.rstrip('\n'))
                    key = gene + '-' + pph2_data[3] + pph2_data[2] + pph2_data[4]
                    if key in getev_variants and pph2_data[16]:
                        print '\t'.join([key, getev_variants[key], pph2_data[16]])


def main():
    # Parse options
    usage = ("%prog -p /path/to/polyphen2_dump.tar.bz2 -g /path/to/getev-json")
    parser = OptionParser(usage=usage)
    parser.add_option("-g", "--getev", dest="getev_file",
                      help="Path to GET-Evidence JSON flat file",
                      metavar="GETEVFILE")
    parser.add_option("-p", "--pph2", dest="pph2_file",
                      help="Path to Polyphen 2 bz2-compressed tar",
                      metavar="PPH2FILE")
    options, args = parser.parse_args()

    if options.pph2_file and options.getev_file:
        match_getev_pph2(options.getev_file, options.pph2_file)
    else:
        parser.print_help()

if __name__ == "__main__":
    main()
