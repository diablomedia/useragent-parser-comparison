#!/usr/bin/env node

var initStart = process.hrtime();
var parser = require('ua-parser-js');
// Trigger a parse to force cache loading
parser('Test String');
var initTime = process.hrtime(initStart)[1] / 1000000000;

var package = require(require('path').dirname(require.resolve('ua-parser-js')) +
    '/../package.json');
var version = package.version;

var hasUa = false;
var uaPos = process.argv.indexOf('--ua');
var line = '';
if (uaPos >= 0) {
    line = process.argv[2];
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
    var browser = browscap.getBrowser(line);
    output.parse_time = process.hrtime(start)[1] / 1000000000;

    output.result = {
        useragent: line,
        parsed: {
            browser: {
                name: r.browser.name ? r.browser.name : '',
                version: r.browser.version ? r.browser.version : ''
            },
            platform: {
                name: r.os.name ? r.os.name : '',
                version: r.os.version ? r.os.version : ''
            },
            device: {
                name: r.device.model ? r.device.model : '',
                brand: r.device.vendor ? r.device.vendor : '',
                type: r.device.type ? r.device.type : '',
                ismobile:
                    r.device.type === 'mobile' ||
                    r.device.type === 'tablet' ||
                    r.device.type === 'wearable'
                        ? true
                        : false
            }
        },
        time: end
    };
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
