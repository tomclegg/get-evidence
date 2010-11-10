#!/bin/sh

chmod +x $SOURCE/.git/hooks/post-update

for hook in post-commit post-merge post-receive
do
  if [ ! -e $SOURCE/.git/hooks/$hook ]
  then
    echo '#!/bin/sh' > $SOURCE/.git/hooks/$hook
  fi
  if ! grep -qx 'git update-server-info' $SOURCE/.git/hooks/$hook
  then
    echo >&2 "Adding 'git update-server-info' to git $hook hook."
    chmod +x $SOURCE/.git/hooks/$hook
    echo git update-server-info | tee -a $SOURCE/.git/hooks/$hook >/dev/null
  fi
done

set -e
cd $SOURCE
git update-server-info
