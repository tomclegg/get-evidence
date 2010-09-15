#!/bin/sh

set -e

. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"

if [ `tr -dc . </etc/hostname |wc -c` -lt 2 ]
then
  cat >&2 <<EOF
***
Warning: /etc/hostname does not contain two dots.  It might not be a
fully qualified host name.
EOF
fi

if ! grep -wq `cat /etc/hostname` /etc/hosts
then
  cat >&2 <<EOF
***
Warning: /etc/hosts does not list an IP address for `cat /etc/hostname`.
Consider adding it.
EOF
fi
