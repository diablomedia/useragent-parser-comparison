#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

composer_command="composer"

command -v "$composer_command" >/dev/null 2>&1 || {
    composer_command="composer.phar"
}

$composer_command update --ignore-platform-reqs

./vendor/bin/browscap-php browscap:update --cache ./data --remote-file Full_PHP_BrowscapINI
