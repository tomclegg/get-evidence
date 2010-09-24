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

$SCRIPT_DIR/setup-db.sh
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

cp -p $SOURCE/server/config.default.py $CONFIG/config.default.py
if [ ! -e $CONFIG/config.py -a ! -L $CONFIG/config.py ]
then
  dbpass=$(cat $CONFIG/dbpassword)
  [ $? = 0 ]
  sed -e "s/shakespeare/$dbpass/g" < $CONFIG/config.default.py > $CONFIG/config.py
  echo >&2 "*** "
  echo >&2 "*** Please edit $CONFIG/config.py to suit your installation."
  echo >&2 "*** "
else
  echo >&2 "*** "
  echo >&2 "*** Please ensure $CONFIG/config.py is up-to-date."
  echo >&2 "*** Latest defaults can be found at:"
  echo >&2 "***   $CONFIG/config.default.py"
  echo >&2 "*** "
fi
chmod 600 $CONFIG/config.py

cat >&2 <<EOF
***
*** If everything went well, you should see the Trait-o-matic web
*** interface at http://`hostname`/.
***
*** You still need to obtain and import the reference data before any
*** processing can occur.  See http://`hostname`/docs/install
***
EOF
