#!/bin/bash

set -e

. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"

if [ "`id -u $USER`" != "$EUID" ]
then
  set -x
  exec sudo -u "$USER" DEBUG="$DEBUG" "$0"
fi

for var in WWW CONFIG TMP DATA HOME USER BASE_URL
do
  eval echo $var=\$`echo $var`
done >$CONFIG/config.sh

# 2010-11-03 Madeleine Price Ball
# No longer using SQL for trait-o-matic processing due to speed issues and
# redundancy with data in GET-Evidence's SQL data.
#$SCRIPT_DIR/setup-db.sh

$SCRIPT_DIR/setup-external-data.sh

if [ ! -L $CORE/config.py ]; then
  if [ -f $CORE/config.py ]; then
    mv -i $CORE/config.py $CONFIG/config.py
  fi

  # 2010-09-14 Madeleine Price Ball
  # next line now breaks because CORE is in main user's dir, not www-data's
  # for now, moving it into the main install.sh script 
  # so it is run by the correct user
  #
  # ln -sn $CONFIG/config.py $CORE/config.py
fi

# 2010-09-14 Madeleine Price Ball
# building python extensions breaks because CORE is in main user's dir, not
# in www-data's. Moving this into main install.sh script for now so it is 
# run by the correct user.
#
# Build python exts
#(cd $CORE ; python setup.py build_ext --inplace)

if [ ! -e $DATA/genome_stats.txt ]
then
    echo Making symlink to genome_stats.txt
    ln -s $SOURCE/server/genome_stats.txt $DATA/genome_stats.txt
fi

if [ ! -e $DATA/getev-latest.json.gz -a ! -h $DATA/getev-latest.json.gz ]
then
    echo Making symlink to getev-latest.json.gz
    ln -s $SOURCE/public_html/getev-latest.json.gz $DATA/getev-latest.json.gz
fi
