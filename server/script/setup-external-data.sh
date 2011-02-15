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

# hg19.2bit genome
echo Getting hg19.2bit genome from UCSC
if [ ! -f hg19.2bit.stamp ]
then
  $WGET ftp://hgdownload.cse.ucsc.edu/goldenPath/hg19/bigZips/hg19.2bit
  touch hg19.2bit.stamp
fi
 
# dbSNP (only two tables)
echo Getting dbSNP from NIH
if [ ! -f dbSNP.stamp ]; then
  $WGET ftp://ftp.ncbi.nih.gov/snp/organisms/human_9606/database/organism_data/OmimVarLocusIdSNP.bcp.gz
  $WGET ftp://ftp.ncbi.nih.gov/snp/organisms/human_9606/database/b130_archive/b130_SNPChrPosOnRef_36_3.bcp.gz
  $WGET ftp://ftp.ncbi.nih.gov/snp/organisms/human_9606/database/organism_data/b132_SNPChrPosOnRef_37_1.bcp.gz
  touch dbSNP.stamp
fi

echo Sorting dbSNP
if [ ! -f dbSNP_sort.stamp ]; then
  for bcp in b130_SNPChrPosOnRef_36_3.bcp b132_SNPChrPosOnRef_37_1.bcp
  do
    $GUNZIP < $bcp.gz | sort --buffer-size=20% --key=2,2 --key=3n,3 | perl -nae 'if ($#F == 3) { print; }' > $bcp.tmp
    mv $bcp.tmp $bcp
  done
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
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/knownGene.txt.gz -OknownGene_hg18.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/knownCanonical.txt.gz -OknownCanonical_hg18.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/kgXref.txt.gz -OkgXref_hg18.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg18/database/refFlat.txt.gz -OrefFlat_hg18.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg19/database/knownGene.txt.gz -OknownGene_hg19.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg19/database/knownCanonical.txt.gz -OknownCanonical_hg19.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg19/database/kgXref.txt.gz -OkgXref_hg19.txt.gz
  $WGET http://hgdownload.cse.ucsc.edu/goldenPath/hg19/database/refFlat.txt.gz -OrefFlat_hg19.txt.gz
  $GUNZIP -c knownGene_hg18.txt.gz > knownGene_hg18.txt
  $GUNZIP -c knownCanonical_hg18.txt.gz > knownCanonical_hg18.txt
  $GUNZIP -c kgXref_hg18.txt.gz > kgXref_hg18.txt
  $GUNZIP -c refFlat_hg18.txt.gz > refFlat_hg18.txt
  $GUNZIP -c knownGene_hg19.txt.gz > knownGene_hg19.txt
  $GUNZIP -c knownCanonical_hg19.txt.gz > knownCanonical_hg19.txt
  $GUNZIP -c kgXref_hg19.txt.gz > kgXref_hg19.txt
  $GUNZIP -c refFlat_hg19.txt.gz > refFlat_hg19.txt
  touch ucsc.stamp
fi

echo Attaching gene names to knownGene and sorting
if [ ! -f ucsc_sort.stamp ]; then
  perl $CORE/script/getCanonicalWithName.pl knownGene_hg18.txt knownCanonical_hg18.txt kgXref_hg18.txt refFlat_hg18.txt hgnc_genenames.txt | \
    grep -v "chr[0-9MXY]*_.*" | awk '{ if ( !( ($3 == "chrY") && (($7 >= 0 && $8 <= 2709520) || ($7 >= 57443437 && $8 <= 57772954 )) )) print }' | \
    sort --key=3,3 --key=5n,5 > knownGene_hg18_sorted.txt
  perl $CORE/script/getCanonicalWithName.pl knownGene_hg19.txt knownCanonical_hg19.txt kgXref_hg19.txt refFlat_hg19.txt hgnc_genenames.txt | \
    grep -v "chr[0-9MXY]*_.*" | awk '{ if ( !( ($3 == "chrY") && (($7 >= 10000 && $8 <= 2649520) || ($7 >= 59034049 && $8 <= 59363566 )) )) print }' | \
    sort --key=3,3 --key=5n,5 > knownGene_hg19_sorted.txt
    # Some command line stuff is added afterwards:
    # grep removes alternate assemblies and unplaced scaffolds
    # awk removes the chrY pseudoautosomal region (coordinates based on Complete Genomics data, 
    # Complete Genomics reports these as chrX and UCSC's transcripts have duplicate annotation)
  touch ucsc_sort.stamp
fi
