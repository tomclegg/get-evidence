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

# refFlat/UCSC
echo Getting refFlat from UCSC
if [ ! -f refFlat.stamp ]; then
  try_whget  /Trait-o-matic/data/refFlat.txt.gz . || \
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/refFlat.txt.gz 
  $GUNZIP -c refFlat.txt.gz > refFlat.txt
  touch refFlat.stamp
fi
echo Sorting refFlat
if [ ! -f refFlat_sort.stamp ]; then
  sort --key=3,3 --key=5n,5 refFlat.txt > refFlat_sorted.txt
  touch refFlat_sort.stamp
fi
