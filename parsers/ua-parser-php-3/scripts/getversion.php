<?php

file_put_contents(
    __DIR__ . '/../version.txt',
    substr(
        hash('sha512', file_get_contents('https://raw.githubusercontent.com/ua-parser/uap-core/master/regexes.yaml')),
        0,
        7
    )
);
