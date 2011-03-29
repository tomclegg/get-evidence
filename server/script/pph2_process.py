#!/usr/bin/python

import re
import sys
import zipfile
import gzip
import bz2
from optparse import OptionParser


def autozip_file_open(filename, mode='r'):
    """Return file obj, with compression if appropriate extension is given"""
    if re.search("\.zip", filename):
        archive = zipfile.ZipFile(filename, mode)
        if mode == 'r':
            files = archive.infolist()
            if len(files) == 1:
                if hasattr(archive, "open"):
                    return archive.open(files[0])
                else:
                    sys.exit("zipfile.ZipFile.open not available. Upgrade " + 
                             "python to 2.6 to work with zip-compressed " +
                             "files!")
            else:
                sys.exit("Zip archive " + filename + 
                         " has more than one file!")
        else:
            sys.exit("Zip archive only supported for reading.")
    elif re.search("\.gz", filename):
        return gzip.GzipFile(filename, mode)
    elif re.search("\.bz2", filename):
        return bz2.BZ2File(filename, mode)
    else:
        return open(filename, mode)

def process_kgwname(kgwname_file):
    """Return dict linking UCSC IDs to gene names from first column"""
    ucsc_to_name = dict()
    f_in = autozip_file_open(kgwname_file, 'r')
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
    f_in = autozip_file_open(kgxref_file, 'r')
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


def process_pph2(pph2_file, kgxref_file, kgwname_file):
    # Each UCSC key uniquely matches one tuple of (name, chr, start, end)
    ucsc_to_name = process_kgwname(kgwname_file)
    # Each name matches one or more Uniprot IDs. These are returned as tuples 
    # of (Uniprot_ID, chr, start, end), positions are to resolve ambiguities 
    # if multiple uniprots match a name.
    uniprot_to_genename = process_kgxref(kgxref_file, ucsc_to_name)
    pph2_in = autozip_file_open(pph2_file)
    for line in pph2_in:
        pph2_data = re.split(r' *\t *', line)
        if pph2_data[2] in uniprot_to_genename:
            yield '\t'.join([pph2_data[2], 
                             uniprot_to_genename[pph2_data[2]], 
                             pph2_data[3], pph2_data[4], 
                             pph2_data[5], pph2_data[7]])

def main():
    # Parse options 
    usage = ("\n%prog --pph2 PPH2 --kgxref kgxreffile " +
             "--kgwname kgwithnamefile [-o outputfile]\n" )
    parser = OptionParser(usage=usage)
    parser.add_option("--pph2", dest="pph2_file",
                      help="read data from PPH2FILE containing polyphen 2 " +
                      "data (automatically uncompress if *.zip, *.gz, *.bz2)", 
                      metavar="PPH2FILE")
    parser.add_option("--kgxref", dest="kgxref_file",
                      help="read kgxref data from KGXREFFILE (automatically " +
                      "compress if *.gz, or *.bz2)", metavar="KGXREFFILE")
    parser.add_option("--kgwithname", dest="kgwname_file",
                      help="read KGWNAMEFILE, created from a UCSC knowngene " +
                      "file with an added first column containing gene name", 
                      metavar="KGWNAMEFILE")
    parser.add_option("-o", "--output", dest="output",
                      help="write to OUTPUTFILE", metavar="OUTPUTFILE")
    options, args = parser.parse_args()
    # Handle input
    if options.pph2_file and options.kgxref_file and options.kgwname_file:
        out = process_pph2(options.pph2_file, options.kgxref_file, 
                           options.kgwname_file)
        if options.output:
            f_out = autozip_file_open(options.output, 'w')
            for line in out:
                f_out.write(line + '\n')
            f_out.close()
        else:
            for line in out:
                print line
    else:
        parser.print_help()

if __name__ == "__main__":
    main()
