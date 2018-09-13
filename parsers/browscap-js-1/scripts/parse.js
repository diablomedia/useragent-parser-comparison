var initStart = process.hrtime();
var Browscap = require('browscap-js');
var browscap = new Browscap();
// Trigger a parse to force cache loading
browscap.getBrowser('Test String');
var initTime = process.hrtime(initStart)[1] / 1000000000;

var browscapPackage = require(require('path').dirname(require.resolve('browscap-js')) + '/package.json');
var version = browscapPackage.version;

var benchmark = false;
var benchmarkPos = process.argv.indexOf('--benchmark');
if (benchmarkPos >= 0) {
    process.argv.splice(benchmarkPos, 1);
    benchmark = true;
}

var lineReader = require('readline').createInterface({
    input: require('fs').createReadStream(process.argv[2])
});

var output = {
    results: [],
    parse_time: 0,
    init_time: initTime,
    memory_used: 0,
    version: version
};

lineReader.on('line', function(line) {
    if (line === '') {
        return;
    }

    var start = process.hrtime();
    var browser = browscap.getBrowser(line);
    var end = process.hrtime(start)[1] / 1000000000;

    output.parse_time += end;

    if (benchmark) {
        return;
    }

    var result = {
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

    output.results.push(result);
});

lineReader.on('close', function() {
    output.memory_used = process.memoryUsage().heapUsed;
    console.log(JSON.stringify(output, null, 2));
});
