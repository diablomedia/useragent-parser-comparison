<?php
declare(strict_types = 1);
file_put_contents(
    __DIR__ . '/../version.txt',
    mb_substr(
        hash('sha512', file_get_contents('https://raw.githubusercontent.com/ua-parser/uap-core/master/regexes.yaml')),
        0,
        7
    )
);
