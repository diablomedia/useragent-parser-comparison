var initStart = process.hrtime();
var parser = require('useragent');
parser(true);

// Trigger a parse to force cache loading
parser.parse('Test String');
var initTime = process.hrtime(initStart)[1] / 1000000000;

var package = require(require('path').dirname(require.resolve('useragent')) +
    '/package.json');
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
    var r = parser.parse(line),
        os = r.os,
        device = r.device;
    var end = process.hrtime(start)[1] / 1000000000;

    var outputDevice = {
        name: '',
        brand: '',
        type: null,
        ismobile: null
    };

    if (device.major !== '0') {
        outputDevice.name = device.major;
        outputDevice.brand = device.family;
    } else if (device.family !== 'Other') {
        outputDevice.name = device.family;
    }

    output.result = {
        useragent: line,
        parsed: {
            browser: {
                name: r.family,
                version: r.toVersion() === '0.0.0' ? '' : r.toVersion()
            },
            platform: {
                name: os.family,
                version: r.os.toVersion()
            },
            device: outputDevice
        },
        time: end
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
