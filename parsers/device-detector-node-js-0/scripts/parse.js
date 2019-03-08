const initStart = process.hrtime();
const DeviceDetector = require('device-detector-node');
const detector = new DeviceDetector();
// Trigger a parse to force cache loading
detector.detect('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(
    require.resolve('device-detector-node')
) + '/package.json');
const version = package.version;

let benchmark = false;
const benchmarkPos = process.argv.indexOf('--benchmark');
if (benchmarkPos >= 0) {
    process.argv.splice(benchmarkPos, 1);
    benchmark = true;
}

const lineReader = require('readline').createInterface({
    input: require('fs').createReadStream(process.argv[2])
});

const output = {
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

    const start = process.hrtime();
    let error = null,
        result = {},
        r = null;
    try {
        r = detector.detect(line);
    } catch (err) {
        error = err;

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
    const end = process.hrtime(start)[1] / 1000000000;

    output.parse_time += end;

    if (benchmark) {
        return;
    }

    if (r !== null) {
        result = {
            useragent: line,
            parsed: {
                browser: {
                    name: r.browser.name ? r.browser.name : null,
                    version: r.browser.version ? r.browser.version : null
                },
                platform: {
                    name: r.os.name ? r.os.name : null,
                    version: r.os.version ? r.os.version : null
                },
                device: {
                    name: r.device.model ? r.device.model : null,
                    brand: r.device.vendor ? r.device.vendor : null,
                    type: r.device.type ? r.device.type : null,
                    ismobile:
                        r.device.type === 'mobile' ||
                        r.device.type === 'tablet' ||
                        r.device.type === 'wearable'
                            ? true
                            : false
                }
            },
            time: end,
            error: error
        };
    } else {
        result.error = error;
        result.time = end;
    }

    output.results.push(result);
});

lineReader.on('close', function() {
    output.memory_used = process.memoryUsage().heapUsed;
    console.log(JSON.stringify(output));
});
