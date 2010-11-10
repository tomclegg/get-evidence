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

# Currently out because this takes too long and/or isn't necessary - MPB 11/03/2010
#
# morbidmap/OMIM
#echo Getting morbidmap from NIH
#if [ ! -e morbidmap.txt ]; then
#  try_whget  /Trait-o-matic/data/morbidmap . || \
#  $WGET ftp://ftp.ncbi.nih.gov/repository/OMIM/morbidmap
#  ln -sf morbidmap morbidmap.txt
#fi

# Currently out because this takes too long and/or isn't necessary - MPB 11/03/2010
# 
# OMIM
#echo Getting OMIM from NIH
#if [ ! -f omim.stamp ]; then
#  try_whget  /Trait-o-matic/data/omim.txt.Z . || \
#  $WGET ftp://ftp.ncbi.nih.gov/repository/OMIM/omim.txt.Z
#  $GUNZIP -c omim.txt.Z >omim.txt
#  python $CORE/omim_print_variants.py omim.txt > omim.tsv
#  rm omim.txt
#  touch omim.stamp
#fi
 
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

# Currently out because this takes too long and/or isn't necessary - MPB 11/03/2010
#
# snp/UCSC
#if [ ! -f snp129.stamp ]; then
#  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/snp129.txt.gz
#  touch snp129.stamp
#fi
 
# Currently out because this takes too long and/or isn't necessary - MPB 11/03/2010
#
# SNPedia
#if [ ! -f snpedia.stamp ]
#then
#  try_whget  /Trait-o-matic/data/snpedia.txt . || \
#  (
#    echo Downloading snpedia data, this could take 45 minutes
#    python $CORE/snpedia.py > snpedia.txt
#  )
#  touch snpedia.stamp
#fi
## -- clean up some descriptive text
#sed 's/ (None)//' < snpedia.txt \
# | awk 'BEGIN { FS = "\t" }; ($5 !~ /(^normal)|(^\?)/ || $5 ~ /;/)' \
# > snpedia.filtered.txt
#python $CORE/snpedia_print_genotypes.py snpedia.filtered.txt > snpedia.tsv.tmp
#[ -s snpedia.tsv.tmp ] || echo "snpedia.tsv.tmp is zero size -- something went wrong."
#mv snpedia.tsv.tmp snpedia.tsv

# Currently out because this takes too long and/or isn't necessary - MPB 11/03/2010
#
# HapMap 
#if [ ! -f hapmap.stamp ]; then
#  if [ ! -f hapmapfiles.stamp ]; then
#    $WGET -r -l1 --accept allele\* --no-parent ftp://ftp.ncbi.nlm.nih.gov/hapmap/frequencies/2009-02_phaseII+III/forward/non-redundant/
#    rm -f ftp.ncbi.nlm.nih.gov/hapmap/frequencies/2009-02_phaseII+III/forward/non-redundant/genotype* || true
#    touch hapmapfiles.stamp
#  fi
#  touch hapmap.stamp
#fi

