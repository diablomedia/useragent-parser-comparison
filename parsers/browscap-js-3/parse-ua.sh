#!/bin/bash

parent_path=$( cd "$(dirname "${BASH_SOURCE}")" ; pwd -P )

node $parent_path/scripts/parse-ua.js "$@"
