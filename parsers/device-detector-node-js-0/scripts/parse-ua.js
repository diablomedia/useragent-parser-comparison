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

let hasUa = false;
const uaPos = process.argv.indexOf('--ua');
let line = '';
if (uaPos >= 0) {
    line = process.argv[3];
    hasUa = true;
}

const output = {
    result: null,
    parse_time: 0,
    init_time: initTime,
    memory_used: 0,
    version: version
};

if (hasUa) {
    const start = process.hrtime();
    let error = null,
        result = {},
        r;
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

    if (typeof r !== 'undefined') {
        result = {
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
            time: end,
            error: error
        };
    } else {
        result.error = error;
        result.time = end;
    }
    output.parse_time = end;
    output.result = result;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
