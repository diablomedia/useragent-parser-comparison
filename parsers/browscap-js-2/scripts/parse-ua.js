#!/usr/bin/env node

var initStart = process.hrtime();
var Browscap = require('browscap-js');
var browscap = new Browscap();
// Trigger a parse to force cache loading
browscap.getBrowser('Test String');
var initTime = process.hrtime(initStart)[1] / 1000000000;

var browscapPackage = require(require('path').dirname(
    require.resolve('browscap-js')
) + '/package.json');
var version = browscapPackage.version;

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
    var browser = browscap.getBrowser(line);
    var end = process.hrtime(start)[1] / 1000000000;

    output.result = {
        useragent: line,
        parsed: {
            browser: {
                name: browser.Browser,
                version: browser.Version
            },
            platform: {
                name: browser.Platform,
                version: browser.Platform_Version
            },
            device: {
                name: browser.Device_Name,
                brand: browser.Device_Maker,
                type: browser.Device_Type,
                ismobile: browser.isMobileDevice ? true : false
            }
        },
        time: end
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
