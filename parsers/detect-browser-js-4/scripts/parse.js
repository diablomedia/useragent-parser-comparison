const initStart = process.hrtime();
const { parseUserAgent } = require('detect-browser');
// Trigger a parse to force cache loading
parseUserAgent('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(require.resolve('detect-browser')) + '/package.json');
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
    const r = parseUserAgent(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.parse_time += end;

    if (benchmark) {
        return;
    }

    const result = {
        useragent: line,
        parsed: {
            browser: {
                name: r !== null && r.name ? r.name : null,
                version: r !== null && r.version ? r.version : null
            },
            platform: {
                name: r !== null && r.os ? r.os : null,
                version: null
            },
            device: {
                name: null,
                brand: null,
                type: null,
                ismobile: null
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
