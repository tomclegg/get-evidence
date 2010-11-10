#!/bin/sh

set -e

CONFIG_LOCAL="$(echo "$0" | sed -e 's/[^\/]*$//')config-local.sh"

perl -e '
  for(qw(HOME USER SOURCE CORE CONFIG DATA LOG TMP PORT UPLOAD NO_CLONETRACK))
  {
    if (exists $ENV{$_})
    {
      print "export $_=\"$ENV{$_}\"\n";
    }
  }
 ' >$CONFIG_LOCAL
