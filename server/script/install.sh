#!/bin/bash

set -e

. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"
echo "Done with defaults $SOURCE";

sudo DEBUG="$DEBUG" $SCRIPT_DIR/prereqs-ubuntu.sh
echo "Done with prereqs-ubuntu $SOURCE ";

sudo DEBUG="$DEBUG" $SCRIPT_DIR/install-sysconfig.sh
echo "Done with install-sysconfig.sh";


$SCRIPT_DIR/install-user.sh
ln -sn $CONFIG/config.py $CORE/config.py
echo "Doing python set up..."
(cd $CORE ; python setup.py build_ext --inplace)
echo "Done with install-user.sh"

$SCRIPT_DIR/install-git-hooks.sh
