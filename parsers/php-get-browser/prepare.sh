#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"/data

if [ ! -e "browscap.ini" ]; then
    echo "Updating browscap data file"
    wget --quiet -Obrowscap.ini "https://browscap.org/stream?q=Full_PHP_BrowsCapINI"
    wget --quiet -Oversion.txt "https://browscap.org/version-number"
fi

cd "$parent_path"

composer_command="composer"

command -v "$composer_command" >/dev/null 2>&1 || {
    composer_command="composer.phar"
}

$composer_command install
