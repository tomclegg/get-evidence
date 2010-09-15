if [ -n "$DEBUG" ]
then
  set -x
fi

export SCRIPT_DIR="$(echo "$0" | sed -e 's/[^\/]*$//')"
SCRIPT_DIR=$(if [ ! -z "$SCRIPT_DIR" ]; then cd "$SCRIPT_DIR"; fi; pwd)

if [ ! -e $SCRIPT_DIR/config-local.sh ]
then
  cat >&2 <<EOF
***
You need to run $SCRIPT_DIR/configure.sh
before running this script.  
***
EOF
  exit 1
fi

. $SCRIPT_DIR/config-local.sh

if [ -z "$CORE" ]; then export CORE=$HOME/core; fi
if [ -z "$CONFIG" ]; then export CONFIG=$HOME/config; fi
if [ -z "$DATA" ]; then export DATA=$HOME/data; fi
if [ -z "$LOG" ]; then export LOG=$HOME/log; fi
if [ -z "$TMP" ]; then export TMP=$HOME/tmp; fi
if [ -z "$PORT" ]; then export PORT=80; fi
if [ -z "$UPLOAD" ]; then export UPLOAD=$HOME/upload; fi
if [ -z "$SOURCE" ]; then export SOURCE=$(dirname $(if [ ! -z $(dirname $0) ]; then cd $(dirname $0); fi; pwd)); fi
