const initStart = process.hrtime();
const parser = require('bowser');
// Trigger a parse to force cache loading
const r = parser.getParser('Test String');
r.parse();
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(require.resolve('bowser')) + '/package.json');
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
    const browser = parser.getParser(line);
    const r = browser.parse().parsedResult;
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
                version: r.browser.version ? r.browser.version : null
            },
            platform: {
                name: r.os.name ? r.os.name : null,
                version: r.os.version ? r.os.version : null
            },
            device: {
                name: r.platform.model ? r.platform.model : null,
                brand: r.platform.vendor ? r.platform.vendor : null,
                type: r.platform.type ? r.platform.type : null,
                ismobile:
                    r.platform.type === 'mobile' ||
                    r.platform.type === 'tablet' ||
                    r.platform.type === 'wearable'
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
