#!/bin/bash

set -e
set -o pipefail

. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"

echo It is safe to restart this script - it keeps track of progress

mkdir -p $DATA
cd $DATA

cp $SCRIPT_DIR/load.sql $DATA/load.sql

try_whget ()
{
  if ! which wh >/dev/null
  then
    return 1
  fi
  manifest_name="$1"
  dest_dir="$2"
  manifest_hash=$(wh manifest lookup name="$manifest_name")
  if [ $? = 0 ]
  then
    mkdir -p $dest_dir
    echo "Retrieving $manifest_name -- $manifest_hash"
    if whget -r "$manifest_hash/" "$dest_dir/"
    then
      return 0
    fi
  fi
  return 1
}

# Use "continue" flag on wget, so that we can just rerun this script and it will do the right thing
WGET='wget -c --progress=bar'
GUNZIP='gunzip -f'
MYSQL_PASS=$(cat $CONFIG/dbpassword)

if [ ! -f hg18.2bit.stamp ]
then
  try_whget /Trait-o-matic/data/hg18.2bit . || \
  $WGET ftp://hgdownload.cse.ucsc.edu/goldenPath/hg18/bigZips/hg18.2bit
  touch hg18.2bit.stamp
fi
 
cd $DATA
 
# dbSNP (only two tables)
if [ ! -f dbSNP.stamp ]; then
  try_whget /Trait-o-matic/data/OmimVarLocusIdSNP . || \
  $WGET ftp://ftp.ncbi.nih.gov:21/snp/organisms/human_9606/database/organism_data/OmimVarLocusIdSNP.bcp.gz
  try_whget /Trait-o-matic/data/b129_SNPChrPosOnRef_36_3.bcp.gz . || \
  $WGET ftp://ftp.ncbi.nih.gov:21/snp/organisms/human_9606/database/organism_data/b129/b129_SNPChrPosOnRef_36_3.bcp.gz
  touch dbSNP.stamp
fi

# 2010-09-14 Madeleine Price Ball
# Commenting out as this inappropriately dies when file isn't retrieved
#
# # HuGENet GWAS
# if [ ! -f HuGENet.stamp ]; then
#   if ! ls GWAS_Hit_*.txt >/dev/null 2>/dev/null; then
#      try_whget /Trait-o-matic/data/HuGENet . || true
#   fi
#   csv="`ls -rt GWAS_Hit_*.txt | tail -n1`" || true
#   if [ "$csv" = "" ]; then
#     echo >&2 <<EOF
# 
# Could not download HuGENet csv from warehouse.  Open
# http://hugenavigator.net/ , then click "GWAS Integrator", then click
# the "All" button, then click "Download" on the results page.  Copy the
# downloaded file (GWAS_Hit_mm-dd-yyyy.txt) to $DATA/.
# Then restart the installer.
# EOF
#     exit 1
#   fi
#   python $CORE/import_hugenetgwas.py "$csv"
#   touch HuGENet.stamp
# fi

# HapMap (quick version)
if [ ! -f hapmap.stamp ] \
   && [ ! -z "$IMPORT_BINARY" ] \
   && try_whget /Trait-o-matic/data/hapmap27.bin . \
   && chmod 660 hapmap27.*
then
  touch hapmap.stamp hapmapquick.stamp
fi

# morbidmap/OMIM
if [ ! -e morbidmap.txt ]; then
  try_whget  /Trait-o-matic/data/morbidmap . || \
  $WGET ftp://ftp.ncbi.nih.gov/repository/OMIM/morbidmap
  ln -sf morbidmap morbidmap.txt
fi
 
# OMIM
if [ ! -f omim.stamp ]; then
  try_whget  /Trait-o-matic/data/omim.txt.Z . || \
  $WGET ftp://ftp.ncbi.nih.gov/repository/OMIM/omim.txt.Z
  $GUNZIP -c omim.txt.Z >omim.txt
  python $CORE/omim_print_variants.py omim.txt > omim.tsv
  rm omim.txt
  touch omim.stamp
fi
 
# refFlat/UCSC
if [ ! -f refFlat.stamp ]; then
  try_whget  /Trait-o-matic/data/refFlat.txt.gz . || \
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/refFlat.txt.gz 
  $GUNZIP -c refFlat.txt.gz > refFlat.txt
  touch refFlat.stamp
fi
 
# snp/UCSC (quick version)
if [ ! -f snp129.stamp ] \
   && [ ! -z "$IMPORT_BINARY" ] \
   && try_whget /Trait-o-matic/data/snp129.bin . \
   && chmod 660 snp129.frm snp129.MYD snp129.MYI
then
  touch snp129.stamp snp129quick.stamp
fi
 
# snp/UCSC
if [ ! -f snp129.stamp ]; then
  try_whget  /Trait-o-matic/data/snp129 . || \
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/snp129.txt.gz
  echo Importing snp129 data into MySQL.
  $GUNZIP -c snp129.txt.gz | mysql -uinstaller -p"$MYSQL_PASS" -e "USE caliban; TRUNCATE snp129; LOAD DATA LOCAL INFILE '/dev/stdin' INTO TABLE snp129 FIELDS TERMINATED BY '\t' LINES TERMINATED BY '\n';"
  touch snp129.stamp
  rm -f snp129.txt
fi
 
# Commenting this out because it takes too long
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

echo Sorting refFlat
if [! -f refFlat_sort.stamp ]; then
  sort --key=3,3 --key=5n,5 refFlat.txt > refFlat_sorted.txt
  touch refFlat_sort.stamp
fi

echo Sorting dbSNP
if [ ! -f dbSNP_sort.stamp ]; then
  $GUNZIP < b129_SNPChrPosOnRef_36_3.bcp.gz | sort --key=2,2 --key=3n,3 | perl -nae 'if ($#F == 3) { print; }' > b129_SNPChrPosOnRef_36_3_sorted.bcp
  touch dbSNP_sort.stamp
fi

echo Loading morbidmap, omim, refFlat, snpedia, dbSNP data into MySQL
cd .
if [ ! -f load.stamp ]; then
  $GUNZIP < OmimVarLocusIdSNP.bcp.gz > OmimVarLocusIdSNP.bcp
  rm -f b129.fifo
  mkfifo b129.fifo
  $GUNZIP < b129_SNPChrPosOnRef_36_3.bcp.gz | sort --key=2,2 --key=3n,3 > b129.fifo
  mysql -uinstaller -p$MYSQL_PASS < $DATA/load.sql
  rm -f b129.fifo
  touch load.stamp
fi

# HapMap (slow version)
if [ ! -f hapmap.stamp ]; then
  if [ ! -f hapmapfiles.stamp ]; then
    try_whget /Trait-o-matic/data/ncbi-hapmap ftp.ncbi.nlm.nih.gov || \
    $WGET -r -l1 --accept allele\* --no-parent ftp://ftp.ncbi.nlm.nih.gov/hapmap/frequencies/2009-02_phaseII+III/forward/non-redundant/
    rm -f ftp.ncbi.nlm.nih.gov/hapmap/frequencies/2009-02_phaseII+III/forward/non-redundant/genotype* || true
    touch hapmapfiles.stamp
  fi
  echo
  echo "*** Loading HapMap data, this could take hours..."
  echo
  for file in ftp.ncbi.nlm.nih.gov/hapmap/frequencies/2009-02_phaseII+III/forward/non-redundant/allele_* ; do
    cat=cat
    if [ "${file##*.}" = bz2 ]; then cat="bzip2 -cd"; fi
    if [ "${file##*.}" = gz ]; then cat="gzip -cd"; fi
    if [ ! -f $file.stamp ]; then
      $cat $file | python $CORE/hapmap_load_database.py && touch $file.stamp
    fi
  done
  touch hapmap.stamp
fi

if [ -f snp129quick.stamp ] && [ -f snp129.MYD ]
then
  cat >&2 <<EOF
***
*** IMPORTANT: in order to complete the snp129 import, you need to execute
*** the following commands:
***
    (
    cd $DATA
    sudo chown mysql:mysql snp129.MYD snp129.MYI snp129.frm
    sudo mv snp129.MYD snp129.MYI snp129.frm /var/lib/mysql/caliban/
    sudo /etc/init.d/mysql restart
    )
***
EOF
fi

if [ -f hapmapquick.stamp ] && [ -f hapmap27.MYD ]
then
  cat >&2 <<EOF
***
*** IMPORTANT: in order to complete the HapMap import, you need to execute
*** the following commands:
***
    (
    cd $DATA
    sudo chown mysql:mysql hapmap27.*
    sudo mv hapmap27.* /var/lib/mysql/caliban/
    sudo /etc/init.d/mysql restart
    )
***
EOF
fi
