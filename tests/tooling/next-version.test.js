const test = require('node:test');
const assert = require('node:assert');
const { pickNextVersion } = require('../../scripts/release/next-version');

test('master already bumped: use it', () => {
    assert.strictEqual(pickNextVersion('7.8.3', '7.8.4', []), '7.8.4');
});

test('master not bumped: use the highest build/<next> branch above the tag', () => {
    const branches = ['master', 'build/7.8.2', 'build/7.8.4', 'build/7.9.0', 'fix/x'];
    assert.strictEqual(pickNextVersion('7.8.3', '7.8.3', branches), '7.9.0');
    assert.strictEqual(pickNextVersion('7.8.3', '7.8.3', ['build/7.8.10', 'build/7.8.4']), '7.8.10');
});

test('master not bumped and no branch: no answer', () => {
    assert.strictEqual(pickNextVersion('7.8.3', '7.8.3', ['master', 'build/7.8.3']), null);
});
