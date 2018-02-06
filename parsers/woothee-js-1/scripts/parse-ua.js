#!/usr/bin/env node

var initStart = process.hrtime();
var parser = require('woothee');
// Trigger a parse to force cache loading
parser.parse('Test String');
var initTime = process.hrtime(initStart)[1] / 1000000000;

var package = require(require('path').dirname(require.resolve('woothee')) +
    '/../package.json');
var version = package.version;

var hasUa = false;
var uaPos = process.argv.indexOf('--ua');
var line = '';
if (uaPos >= 0) {
    line = process.argv[3];
    hasUa = true;
}

var output = {
    result: null,
    parse_time: 0,
    init_time: initTime,
    memory_used: 0,
    version: version
};

if (hasUa) {
    var start = process.hrtime();
    var r = parser.parse(line);
    var end = process.hrtime(start)[1] / 1000000000;

    output.result = {
        useragent: line,
        parsed: {
            browser: {
                name: r.name,
                version: r.version
            },
            platform: {
                name: r.os,
                version: r.os_version
            },
            device: {
                name: null,
                brand: null,
                type: r.category,
                ismobile: null
            }
        },
        time: end
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
