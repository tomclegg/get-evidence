#!/bin/sh

set -e

APTGET="apt-get -qq"

$APTGET install \
wget \
curl \
rsync \
zip unzip \
apache2 \
apache2-threaded-dev \
mysql-server \
mysql-client \
libmysqlclient15-dev \
python-dev \
libapache2-mod-python \
python-mysqldb \
python-pyrex \
php5 \
php5-dev \
php5-mysql \
python-biopython \
php5-gd \
cron \
--fix-missing || \
cat >&2 <<EOF
***
*** If apt-get python-biopython failed, ensure that you have
*** universe in your apt source list
***
EOF
