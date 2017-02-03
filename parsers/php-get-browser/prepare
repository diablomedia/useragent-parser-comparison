#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"/data

if [ ! -e "browscap.ini" ]; then
    echo "Updating browscap data file"
    wget --quiet -Obrowscap.ini "http://browscap.org/stream?q=Full_PHP_BrowsCapINI"
    wget --quiet -Oversion.txt "http://browscap.org/version-number"
fi
