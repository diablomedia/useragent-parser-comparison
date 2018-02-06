const initStart = process.hrtime();
const DeviceDetector = require('device-detector-js');
const detector = new DeviceDetector({ skipBotDetection: true, cache: false });
// Trigger a parse to force cache loading
detector.parse('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(
    require.resolve('device-detector-js')
) + '/../package.json');
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
        r = detector.parse(line);
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
                    name: r.client && r.client.name ? r.client.name : null,
                    version:
                        r.client && r.client.version ? r.client.version : null
                },
                platform: {
                    name: r.os && r.os.name ? r.os.name : null,
                    version: r.os && r.os.version ? r.os.version : null
                },
                device: {
                    name: r.device && r.device.model ? r.device.model : null,
                    brand: r.device && r.device.brand ? r.device.brand : null,
                    type: r.device && r.device.type ? r.device.type : null,
                    ismobile:
                        r.device &&
                        (r.device.type === 'mobile' ||
                            r.device.type === 'mobilephone' ||
                            r.device.type === 'tablet' ||
                            r.device.type === 'wearable')
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
