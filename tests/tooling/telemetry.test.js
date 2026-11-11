const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../webpack/telemetry.js'), 'utf8').replace('import posthog from "posthog-js";', '');

for (const profile of ['production', 'demo', 'test']) {
    test(`browser telemetry ${profile}`, () => {
        const calls = [];
        const properties = [];
        vm.runInNewContext(source, {
            window: { location: { origin: 'https://church.example', host: 'church.example', hostname: 'church.example' }, CRM: { root: '', version: '7.8.3', telemetry: { key: 'test-key', endpoint: 'https://example.invalid', level: 'full', install_profile: profile } } },
            posthog: {
                init: (...args) => calls.push(args),
                register: (value) => properties.push(value),
            },
        });
        const suppressed = profile === 'test';
        assert.equal(calls.length, suppressed ? 0 : 1);
        assert.equal(properties.length, suppressed ? 0 : 1);
        if (!suppressed) {
            assert.equal(properties[0].install_profile, profile);
            assert.equal(properties[0].crm_version, '7.8.3');
            assert.equal(calls[0][1].person_profiles, 'never');
            assert.equal(calls[0][1].ip, false);
            const event = calls[0][1].before_send({ event: 'x', properties: { $ip: '1.2.3.4', $pathname: '/cms/admin/x?id=5' } });
            assert.equal(event.properties.$geoip_disable, true);
            assert.equal(event.properties.$ip, undefined);
            assert.equal(event.properties.$pathname, '/cms/admin/x');
        }
    });
}

test('browser telemetry sends no host name or web address', () => {
    const calls = [];
    vm.runInNewContext(source, {
        window: {
            location: { origin: 'https://stmarys.example.org', host: 'stmarys.example.org', hostname: 'stmarys.example.org' },
            CRM: { root: '/cms', telemetry: { key: 'k', endpoint: 'https://example.invalid', level: 'errors', install_profile: 'production' } },
        },
        URL,
        posthog: { init: (...args) => calls.push(args), register: () => {} },
    });
    const event = calls[0][1].before_send({
        event: '$exception',
        properties: {
            $host: 'stmarys.example.org',
            $referring_domain: 'stmarys.example.org',
            $initial_host: 'stmarys.example.org',
            $referrer: 'https://stmarys.example.org/cms/v2/dashboard',
            $current_url: 'https://stmarys.example.org/cms/v2/people?id=5',
            $exception_list: [{ value: 'failed at stmarys.example.org', stacktrace: { frames: [{ filename: 'https://stmarys.example.org/cms/skin/v2/app.min.js' }] } }],
        },
    });
    assert.equal(event.properties.$current_url, '/v2/people');
    for (const key of ['$host', '$referring_domain', '$initial_host', '$referrer']) {
        assert.equal(event.properties[key], undefined, key);
    }
    assert.equal(event.properties.$exception_list[0].stacktrace.frames[0].filename, '/skin/v2/app.min.js');
    assert.equal(event.properties.$exception_list[0].value, 'failed at [host]');
    assert.doesNotMatch(JSON.stringify(event.properties), /stmarys/);
});
