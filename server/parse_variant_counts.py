#!/usr/bin/python
# This code is part of GET-Evidence.
# Copyright: see COPYING
# Authors: see git-blame(1)

"""Parse output from count_variants.py"""

from optparse import OptionParser

def parse_allele_counts(countsfile):
    f_in = open(countsfile, 'r')

    seen_before = dict()   

    for line in f_in:
        data = line.strip('\n').split('\t')
        parsed = {'chrom': data[0],
                  'start': data[1],
                  'end': data[2],
                  'ref_allele': data[3],
                  'ref_count': data[4],
                  'var_alleles': data[5].split(','),
                  'var_counts': data[6].split(','),
                  'getev_ids': data[7].split(','),
                  'reasons': data[8].split(',') }
        total = int(parsed['ref_count'])
        seen_here = dict()
        skip_site = False
        for i in range(len(parsed['var_alleles'])):
            getev_id = parsed['getev_ids'][i]
            var_count = int(parsed['var_counts'][i])
            var_allele = parsed['var_alleles'][i]
            total += var_count
            key = parsed['reasons'][i]
            # Ignore synonymous variants.
            if not key == 'none' and key in seen_before:
                skip_site = True
                break
            elif key in seen_here:
                seen_here[key]['count'] += var_count
                if not var_allele in seen_here[key]['alleles']:
                    seen_here[key]['alleles'].append(var_allele)
            else:
                seen_here[key] = { 'getev_id': getev_id,
                                   'count': var_count,
                                   'alleles': [ var_allele ] }
        if not skip_site:
            for key in seen_here:
                print '\t'.join([ seen_here[key]['getev_id'], key, 
                                  parsed['chrom'], parsed['start'], 
                                  parsed['end'],
                                  ','.join(seen_here[key]['alleles']),
                                  str(seen_here[key]['count']), str(total) ])
                seen_before[key] = seen_here[key]


def main():
    usage = "%prog -c allele_counts_file"
    parser = OptionParser(usage=usage)
    parser.add_option("-c", "--counts", dest="countsfile",
                      help="Allele counts as produced by count_allele_freq.py",
                      metavar="COUNTSFILE")
    options, args = parser.parse_args()

    if options.countsfile:
        parse_allele_counts(options.countsfile)
    else:
        parser.print_help()


if __name__ == "__main__":
    main()
