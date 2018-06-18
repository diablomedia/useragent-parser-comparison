var initStart = process.hrtime();
var WhichBrowser = require('which-browser');
// Trigger a parse to force cache loading
new WhichBrowser('Test String');
var initTime = process.hrtime(initStart)[1] / 1000000000;

var package = require(require('path').dirname(
    require.resolve('which-browser')
) + '/../package.json');
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
    var start = process.hrtime(),
        result = {};

    try {
        var r = new WhichBrowser(line);
    } catch (err) {
        result = {
            useragent: line,
            parsed: {
                browser: {
                    name: '',
                    version: ''
                },
                platform: {
                    name: '',
                    version: ''
                },
                device: {
                    name: '',
                    brand: '',
                    type: '',
                    ismobile: ''
                }
            }
        };
    }
    var end = process.hrtime(start)[1] / 1000000000;

    var mobileDeviceTypes = [
        'mobile',
        'tablet',
        'watch',
        'media',
        'ereader',
        'camera'
    ];

    output.result = {
        useragent: line,
        parsed: {
            browser: {
                name: r.browser.name ? r.browser.name : '',
                version: r.browser.version ? r.browser.version.value : ''
            },
            platform: {
                name: r.os.name ? r.os.name : '',
                version:
                    r.os.version && r.os.version.value ? r.os.version.value : ''
            },
            device: {
                name: r.device.model ? r.device.model : '',
                brand: r.device.manufacturer ? r.device.manufacturer : '',
                type: r.device.type ? r.device.type : '',
                ismobile:
                    mobileDeviceTypes.indexOf(r.device.type) !== -1 ||
                    (r.device.subtype && r.device.subtype === 'portable')
                        ? true
                        : false
            }
        },
        time: end
    };
    output.parse_time = end;
}

output.memory_used = process.memoryUsage().heapUsed;
console.log(JSON.stringify(output, null, 2));
