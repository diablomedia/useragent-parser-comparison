const initStart = process.hrtime();
const { parseUserAgent } = require('detect-browser');
// Trigger a parse to force cache loading
parseUserAgent('Test String');
const initTime = process.hrtime(initStart)[1] / 1000000000;

const package = require(require('path').dirname(
    require.resolve('detect-browser')
) + '/package.json');
const version = package.version;

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
    const start = process.hrtime();
    const r = parseUserAgent(line);
    const end = process.hrtime(start)[1] / 1000000000;

    output.result = {
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
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
