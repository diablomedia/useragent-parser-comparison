<?php

file_put_contents(
    __DIR__ . '/../version.txt',
    substr(
        hash('sha512', file_get_contents(__DIR__ . '/../node_modules/uap-core/regexes.yaml')),
        0,
        7
    )
);
