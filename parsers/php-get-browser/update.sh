#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"/data

wget --quiet -Onewversion.txt "http://browscap.org/version-number"

diff --brief version.txt newversion.txt >/dev/null
comp_value=$?

if [ $comp_value -eq 1 ]
then
    echo "Updating browscap data file"
    wget --quiet -Obrowscap.ini "http://browscap.org/stream?q=Full_PHP_BrowsCapINI"
    wget --quiet -Oversion.txt "http://browscap.org/version-number"
else
    echo "No update of data file necessary"
fi
