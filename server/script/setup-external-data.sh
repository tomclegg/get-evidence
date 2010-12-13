#!/bin/bash

set -e
set -o pipefail

. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"

cd $DATA

# Use "continue" flag on wget, so that we can just rerun this script and it will do the right thing
WGET='wget -c --progress=bar'
GUNZIP='gunzip -f'

# hg18.2bit genome
echo Getting hg18.2bit genome from UCSC
if [ ! -f hg18.2bit.stamp ]
then
  $WGET ftp://hgdownload.cse.ucsc.edu/goldenPath/hg18/bigZips/hg18.2bit
  touch hg18.2bit.stamp
fi
 
# dbSNP (only two tables)
echo Getting dbSNP from NIH
if [ ! -f dbSNP.stamp ]; then
  $WGET ftp://ftp.ncbi.nih.gov/snp/organisms/human_9606/database/b130_archive/OmimVarLocusIdSNP.bcp.gz
  $WGET ftp://ftp.ncbi.nih.gov/snp/organisms/human_9606/database/b130_archive/b130_SNPChrPosOnRef_36_3.bcp.gz
  touch dbSNP.stamp
fi
echo Sorting dbSNP
if [ ! -f dbSNP_sort.stamp ]; then
  $GUNZIP < b130_SNPChrPosOnRef_36_3.bcp.gz | sort --key=2,2 --key=3n,3 | perl -nae 'if ($#F == 3) { print; }' > b130_SNPChrPosOnRef_36_3_sorted.bcp
  touch dbSNP_sort.stamp
fi

# Get HGNC's list of canonical gene names
if [ ! -f hgnc_genenames.stamp ]; then
    $WGET -Ohgnc_genenames.txt 'http://www.genenames.org/cgi-bin/hgnc_downloads.cgi?title=HGNC+output+data&hgnc_dbtag=onlevel=pri&=on&order_by=gd_app_sym_sort&limit=&format=text&.cgifields=&.cgifields=level&.cgifields=chr&.cgifields=status&.cgifields=hgnc_dbtag&&where=&status=Approved&status_opt=1&submit=submit&col=gd_hgnc_id&col=gd_app_sym&col=gd_app_name&col=gd_status&col=gd_prev_sym&col=gd_aliases&col=gd_pub_chrom_map&col=gd_pub_acc_ids&col=gd_pub_refseq_ids'
    touch hgnc_genenames.stamp
fi

# knownGene/UCSC
echo Getting knownGene, knownCanonical, kgXref, and refFlat from UCSC
if [ ! -f ucsc.stamp ]; then
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/knownGene.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/knownCanonical.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/kgXref.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/refFlat.txt.gz 
  $GUNZIP -c knownGene.txt.gz > knownGene.txt
  $GUNZIP -c knownCanonical.txt.gz > knownCanonical.txt
  $GUNZIP -c kgXref.txt.gz > kgXref.txt
  $GUNZIP -c refFlat.txt.gz > refFlat.txt
  touch ucsc.stamp
fi

echo Attaching gene names to knownGene and sorting
if [ ! -f ucsc_sort.stamp ]; then
  perl $CORE/script/getCanonicalWithName.pl knownGene.txt knownCanonical.txt kgXref.txt refFlat.txt hgnc_genenames.txt | sort --key=3,3 --key=5n,5 > knownGene_sorted.txt
  touch ucsc_sort.stamp
fi
