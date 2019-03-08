const initStart = process.hrtime();
const parser = require('ua-device');
// Trigger a parse to force cache loading
const tmp = new parser('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(require.resolve('ua-device')) +
    '/package.json');
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
    let r;
    try {
        r = new parser(line);
    } catch (e) {
        return;
    }
    const end = process.hrtime(start)[1] / 1000000000;

    output.parse_time += end;

    if (benchmark) {
        return;
    }

    const result = {
        useragent: line,
        parsed: {
            browser: {
                name: r.browser.name ? r.browser.name : null,
                version:
                    typeof r.browser.version !== 'undefined' &&
                    r.browser.version !== null &&
                    typeof r.browser.version.original !== 'undefined'
                        ? r.browser.version.original
                        : null
            },
            platform: {
                name: r.os.name ? r.os.name : null,
                version:
                    typeof r.os.version !== 'undefined' &&
                    r.os.version !== null &&
                    typeof r.os.version.original !== 'undefined'
                        ? r.os.version.original
                        : null
            },
            device: {
                name: r.device.model ? r.device.model : null,
                brand: r.device.manufacturer ? r.device.manufacturer : null,
                type: r.device.type ? r.device.type : null,
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

    output.results.push(result);
});

lineReader.on('close', function() {
    output.memory_used = process.memoryUsage().heapUsed;
    console.log(JSON.stringify(output, null, 2));
});
