#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

php -d browscap=$parent_path/data/browscap.ini $parent_path/scripts/parse.php "$@"
