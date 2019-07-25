const initStart = process.hrtime();
const parser = require('express-useragent-exposer');
// Trigger a parse to force cache loading
parser.parse('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(
    require.resolve('express-useragent-exposer')
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
    const r = parser.parse(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.parse_time += end;

    if (benchmark) {
        return;
    }

    const result = {
        useragent: line,
        parsed: {
            browser: {
                name: r.browser ? r.browser : null,
                version: r.browserversion ? r.browserversion : null
            },
            platform: {
                name: r.os ? r.os : null,
                version: r.osversion ? r.osversion : null
            },
            device: {
                name: r.platform ? r.platform : null,
                brand: null,
                type: null,
                ismobile: r.isMobile
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
