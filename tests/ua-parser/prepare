#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )
cd "$parent_path"

npm install

composer_command="composer"

command -v "$composer_command" >/dev/null 2>&1 || {
    composer_command="composer.phar"
}

$composer_command install

php ./scripts/getversion.php
