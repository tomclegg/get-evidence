#!/bin/sh

set -e
. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"

if [ ! -z "$NO_CLONETRACK" ]
then
  exit
fi

origin=$(cd $SOURCE; git config --get remote.origin.url)

id=$(cat $SCRIPT_DIR/clonetrack.id || true)

curl -s -Furl="$BASE_URL" -Forigin="$origin" -Fid="$id" http://clonetrack.scalablecomputingexperts.com/update.php >$SCRIPT_DIR/clonetrack.id.$$

if [ "`wc -c < $SCRIPT_DIR/clonetrack.id.$$`" = 33 ]
then
  echo Updated clonetrack.scalablecomputingexperts.com.
  mv $SCRIPT_DIR/clonetrack.id.$$ $SCRIPT_DIR/clonetrack.id
fi

rm -f $SCRIPT_DIR/clonetrack.id.*

