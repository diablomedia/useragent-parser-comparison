var initStart = process.hrtime();
var parser = require('useragent');
parser(true);

// Trigger a parse to force cache loading
parser.parse('Test String');
var initTime = process.hrtime(initStart)[1] / 1000000000;

var package = require(require('path').dirname(require.resolve('useragent')) + '/package.json');
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
    'results': [],
    'parse_time': 0,
    'init_time': initTime,
    'memory_used': 0,
    'version': version
};

lineReader.on('line', function (line) {
    if (line === '') {
        return;
    }

    var start = process.hrtime();
    var r = parser.parse(line),
        os = r.os,
        device = r.device;
    var end = process.hrtime(start)[1] / 1000000000;

    output.parse_time += end;

    if (benchmark) {
        return;
    }

    var outputDevice = {
        'name': null,
        'brand': null,
        'type': null,
        'ismobile': null
    };

    if (device.major !== '0') {
        outputDevice.name = device.major;
        outputDevice.brand = device.family;
    } else if (device.family !== 'Other') {
        outputDevice.name = device.family;
    }

    var result = {
        'useragent': line,
        'parsed': {
            'browser': {
                'name': r.family,
                'version': r.toVersion() == '0.0.0' ? '' : r.toVersion()
            },
            'platform': {
                'name': os.family,
                'version': r.os.toVersion()
            },
            'device': outputDevice
        },
        'time': end
    };

    output.results.push(result);
});

lineReader.on('close', function () {
    output.memory_used = process.memoryUsage().heapUsed;
    console.log(JSON.stringify(output, null, 2));
});
