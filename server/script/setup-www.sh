#!/bin/bash

set -e
. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"

WGET='wget -nv'

if [ -z "$CI_VERSION" ]; then CI_VERSION=1.7.1; fi
if [ -z "$CI_MD5" ]; then CI_MD5=deca9709cf21b26dc0e4ec040b37e866; fi
if [ -z "$TEXTILE_VERSION" ]; then TEXTILE_VERSION=2.0.0; fi
if [ -z "$TEXTILE_MD5" ]; then TEXTILE_MD5=c4f2454b16227236e01fc1c761366fe3; fi

cd $TMP

# Todo: use "$WGET http://codeigniter.com/download.php", figure out
# which version was downloaded, and set CI_VERSION (if not provided in
# env)

# Download recommended version of CodeIgniter framework

CI_FILE=CodeIgniter_$CI_VERSION.zip
if ! md5sum $CI_FILE | grep -qw $CI_MD5 2>/dev/null
then
  rm -f $CI_FILE
  $WGET http://codeigniter.com/download_files/$CI_FILE
fi

# Download recommended version fo textile library
TEXTILE_FILE=textile_$TEXTILE_VERSION.tar.gz
if ! md5sum $TEXTILE_FILE | grep -qw $TEXTILE_MD5 2>/dev/null
then
  rm -f $TEXTILE_FILE
  $WGET http://textile.thresholdstate.com/file_download/2/$TEXTILE_FILE
fi

# Install CodeIgniter framework

rm -Rf CodeIgniter_$CI_VERSION
unzip -q $CI_FILE
cd CodeIgniter_$CI_VERSION
rm system/application/config/config.php
rm system/application/config/database.php
tar cf - index.php system | tar -C $WWW -xf -

# Install textile library

cd $TMP
rm -rf textile-$TEXTILE_VERSION
tar xzf $TEXTILE_FILE
cd textile-$TEXTILE_VERSION
cp classTextile.php $WWW/system/application/libraries/Textile.php

cd $SOURCE/web
tar cf - errors media scripts statistics system/application htaccess authenticate unauthenticate | tar -C $WWW -xf -

cd $WWW
perl -p -e 's/%([A-Z]+)%/$ENV{$1}/g' <htaccess >.htaccess

for conf in config database trait-o-matic
do
  # If existing config file is a regular file (not symlink), and there
  # is no site config, move the config to the site config dir
  if [ -e $WWW/system/application/config/$conf.php ] \
     && [ ! -L $WWW/system/application/config/$conf.php ] \
     && [ ! -e $CONFIG/$conf.php ] \
     && mv -i $WWW/system/application/config/$conf.php $CONFIG/$conf.php
  then
    echo >&2 "*** "
    echo >&2 "*** Moved $WWW/system/application/config/conf.php"
    echo >&2 "*** to $CONFIG/conf.php"
    echo >&2 "*** "
  fi

  # If it doesn't already exist, make a symlink from CI config dir to
  # the real site config dir
  if [ ! -e $WWW/system/application/config/$conf.php ] \
     && [ ! -L $WWW/system/application/config/$conf.php ]
  then
    ln -s $CONFIG/$conf.php $WWW/system/application/config/$conf.php
  fi

  # Put the latest defaults in $CONFIG
  cp -p $WWW/system/application/config/$conf.default.php $CONFIG/

  if [ ! -z "$BASE_URL" ]
  then
    perl -pi~ -e 's,http://\$_SERVER\[HTTP_HOST\]/,$ENV{BASE_URL},' $CONFIG/$conf.default.php
  fi

  if [ ! -e $CONFIG/$conf.php -a ! -L $CONFIG/$conf.php ]
  then
    dbpass=$(cat $CONFIG/dbpassword)
    [ $? = 0 ]
    sed -e "s/shakespeare/$dbpass/g" < $CONFIG/$conf.default.php > $CONFIG/$conf.php
    echo >&2 "*** "
    echo >&2 "*** Please edit $CONFIG/$conf.php to suit your installation."
    echo >&2 "*** "
  else
    echo >&2 "*** "
    echo >&2 "*** Please ensure $CONFIG/$conf.php is up-to-date."
    echo >&2 "*** Latest defaults can be found at"
    echo >&2 "***   $CONFIG/$conf.default.php"
    echo >&2 "*** "
  fi

  chmod 600 $CONFIG/$conf.php
done
perl -p -e 's/%([A-Z]+)%/$ENV{$1}/g' < $WWW/system/application/config/upload.php.in > $WWW/system/application/config/upload.php

