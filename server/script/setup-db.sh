#!/bin/bash

set -e
set -o pipefail
. "$(echo "$0" | sed -e 's/[^\/]*$//')defaults.sh"

pwprompt()
{
  cat >&2 <<"  EOF"
  ***
  *** When prompted, please enter your MySQL root password.
  ***
  EOF
}

if [ ! -e $DATA/mysql.stamp ]; then

  if [ ! -e $CONFIG/dbpassword ]
  then
    (
      umask 077
      head -c 2000 /dev/urandom | tr -dc A-Za-z0-9 | head -c 8 > $CONFIG/dbpassword
    )
  fi

  export dbpass=$(cat $CONFIG/dbpassword)
  [ $? = 0 ]

  pwprompt
  cat $SCRIPT_DIR/setup-db-users.sql $SCRIPT_DIR/setup-db-tables.sql | sed -e "s/shakespeare/$dbpass/g" | mysql -uroot -p -f
  touch $DATA/mysql.stamp
else
  export dbpass=$(cat $CONFIG/dbpassword)
  [ $? = 0 ]

  if ! mysql -uinstaller -p"$dbpass" <<"  EOF" >/dev/null
  select now()
  EOF
  then
    # no "installer" user -- old install, need to fix mysql permissions
    pwprompt
    cat $SCRIPT_DIR/setup-db-users.sql | sed -e 's/shakespeare/$ENV{"dbpass"}/g' | mysql -uroot -p -f
  elif ! mysql -uinstaller -p"$dbpass" <<"  EOF" get_evidence >/dev/null
  select now()
  EOF
  then
    # no "get_evidence" db -- fix
    pwprompt
    cat $SCRIPT_DIR/setup-db-users.sql | grep get_evidence | sed -e 's/shakespeare/$ENV{"dbpass"}/g' | mysql -uroot -p -f
  fi

  if ! mysql -uinstaller -p"$dbpass" <<"  EOF" hugenet >/dev/null
  select now()
  EOF
  then
    # no "get_evidence" db -- fix
    pwprompt
    cat $SCRIPT_DIR/setup-db-users.sql | grep hugenet | sed -e 's/shakespeare/$ENV{"dbpass"}/g' | mysql -uroot -p -f
  fi
fi

cat $SCRIPT_DIR/setup-db-permissions.sql | sed -e 's/shakespeare/$ENV{"dbpass"}/g' | mysql -uinstaller -p"$dbpass"

# upgrade-db.sql adds fields that were not present in some previous
# versions.  MySQL does not have a feature analogous to "if not
# exists" for adding columns, so we just use "--force" to "continue
# even if an SQL error occurs".

echo >&2 '*** Some "duplicate column name" and "duplicate key name" errors are normal here ***'
cat $SCRIPT_DIR/upgrade-db.sql | sed -e 's/shakespeare/$ENV{"dbpass"}/g' | mysql -uinstaller -p"$dbpass" --force
