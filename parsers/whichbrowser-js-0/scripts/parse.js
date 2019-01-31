var initStart = process.hrtime();
var WhichBrowser = require('which-browser');
// Trigger a parse to force cache loading
new WhichBrowser('Test String');
var initTime = process.hrtime(initStart)[1] / 1000000000;

var package = require(require('path').dirname(
    require.resolve('which-browser')
) + '/../package.json');
var version = package.version;

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

    var start = process.hrtime(),
        result = {};

    try {
        var r = new WhichBrowser(line);
    } catch (err) {
        result = {
            useragent: line,
            parsed: {
                browser: {
                    name: null,
                    version: null
                },
                platform: {
                    name: null,
                    version: null
                },
                device: {
                    name: null,
                    brand: null,
                    type: null,
                    ismobile: null
                }
            }
        };
    }
    var end = process.hrtime(start)[1] / 1000000000;

    output.parse_time += end;

    if (benchmark) {
        return;
    }

    if (typeof r !== 'undefined') {
        var mobileDeviceTypes = [
            'mobile',
            'tablet',
            'watch',
            'media',
            'ereader',
            'camera'
        ];

        var result = {
            useragent: line,
            parsed: {
                browser: {
                    name: r.browser.name ? r.browser.name : null,
                    version: r.browser.version ? r.browser.version.value : null
                },
                platform: {
                    name: r.os.name ? r.os.name : null,
                    version:
                        r.os.version && r.os.version.value
                            ? r.os.version.value
                            : null
                },
                device: {
                    name: r.device.model ? r.device.model : null,
                    brand: r.device.manufacturer ? r.device.manufacturer : null,
                    type: r.device.type ? r.device.type : null,
                    ismobile:
                        mobileDeviceTypes.indexOf(r.device.type) !== -1 ||
                        (r.device.subtype && r.device.subtype === 'portable')
                            ? true
                            : false
                }
            },
            time: end
        };
    }

    output.results.push(result);
});

lineReader.on('close', function() {
    output.memory_used = process.memoryUsage().heapUsed;
    console.log(JSON.stringify(output, null, 2));
});
