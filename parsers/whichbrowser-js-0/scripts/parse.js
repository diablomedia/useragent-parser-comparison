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
                    name: '',
                    version: ''
                },
                platform: {
                    name: '',
                    version: ''
                },
                device: {
                    name: '',
                    brand: '',
                    type: '',
                    ismobile: ''
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
                    name: r.browser.name ? r.browser.name : '',
                    version: r.browser.version ? r.browser.version.value : ''
                },
                platform: {
                    name: r.os.name ? r.os.name : '',
                    version:
                        r.os.version && r.os.version.value
                            ? r.os.version.value
                            : ''
                },
                device: {
                    name: r.device.model ? r.device.model : '',
                    brand: r.device.manufacturer ? r.device.manufacturer : '',
                    type: r.device.type ? r.device.type : '',
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
