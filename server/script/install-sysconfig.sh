#!/bin/sh

set -e

. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"

# 2010-09-12 Madeleine Price Ball
# There appears to be an error here: update-php-init perl script does not take 
# a second argument to write to. It writes to STDOUT. Thus, it appears that 
# php.ini is not being updated by this at all.
#
# Update php.ini
cp /etc/php5/apache2/php.ini /tmp
$SCRIPT_DIR/update-php-init php-ini-update.txt /tmp/php.ini
cp /tmp/php.ini /etc/php5/apache2/php.ini

# Create dirs
sudo -u "$USER" mkdir -p $TMP $UPLOAD $LOG $CONFIG $DATA
if [ "$USER" != www-data ]; then sudo -u "$USER" chmod a+rwxt $TMP $UPLOAD; fi

# 2010-09-12 Madeleine Price Ball
# Why is it important to refuse to wipe /tmp? I think we should make as few
# system configuration changes as possible -- only those that are necessary.
#
# Do not wipe /tmp on reboot
if egrep '^TMPTIME=[0-9]' /etc/default/rcS >/dev/null
then
  sudo perl -pi.bak -e 's/^TMPTIME=\d/TMPTIME=-1\n#$&/' /etc/default/rcS
fi

# Init script (runs the genome analysis server)
perl -p -e 's/%([A-Z]+)%/$ENV{$1}/g' < $SOURCE/server/script/trait-o-matic.in > /etc/init.d/trait-o-matic.tmp
chmod 755 /etc/init.d/trait-o-matic.tmp
chown 0:0 /etc/init.d/trait-o-matic.tmp
mv /etc/init.d/trait-o-matic.tmp /etc/init.d/trait-o-matic
update-rc.d trait-o-matic start 20 2 3 4 5 . stop 80 0 1 6 .

