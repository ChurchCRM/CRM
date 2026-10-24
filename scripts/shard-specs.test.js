const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { test } = require('node:test');
const { applyShard, partition } = require('./shard-specs');

const names = (count) => Array.from({ length: count }, (_, i) => `cypress/e2e/ui/s${String(i).padStart(2, '0')}.spec.js`);

test('every spec lands in exactly one shard', () => {
    const specs = names(23);
    const durations = Object.fromEntries(specs.map((spec, i) => [spec, (i % 5) * 1000 + 500]));
    const shards = [0, 1, 2, 3].map((index) => partition(specs, durations, 4, index).specs);
    assert.deepEqual(shards.flat().sort(), specs);
});

test('shards weigh about the same', () => {
    const specs = names(40);
    const durations = Object.fromEntries(specs.map((spec, i) => [spec, 1000 + ((i * 7) % 13) * 900]));
    const weights = [0, 1, 2].map((index) => partition(specs, durations, 3, index).weight);
    assert.ok(Math.max(...weights) - Math.min(...weights) <= 12 * 900, weights.join(' '));
});

test('a spec without a recorded duration gets the average', () => {
    const specs = ['a', 'b', 'c', 'd'];
    const durations = { a: 4000, b: 2000 };
    const weights = [0, 1].map((index) => partition(specs, durations, 2, index).weight);
    assert.deepEqual(weights.sort(), [6000, 6000]);
});

test('the split is the same on every call', () => {
    const specs = names(17);
    assert.deepEqual(partition(specs, {}, 3, 1), partition(specs, {}, 3, 1));
});

function repoWith(specs, durations) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'shard-'));
    specs.forEach((spec) => {
        fs.mkdirSync(path.dirname(path.join(root, spec)), { recursive: true });
        fs.writeFileSync(path.join(root, spec), '');
    });
    if (durations) {
        fs.mkdirSync(path.join(root, 'cypress'), { recursive: true });
        fs.writeFileSync(path.join(root, 'cypress', 'split-timings.json'), JSON.stringify({ durations }));
    }
    return root;
}

test('applyShard narrows specPattern to absolute paths and covers all specs', () => {
    const specs = ['cypress/e2e/ui/a.spec.js', 'cypress/e2e/ui/x/b.spec.js', 'cypress/e2e/ui/c.spec.js', 'cypress/e2e/api/d.spec.js'];
    const root = repoWith(specs, [
        { spec: specs[0], duration: 9000 },
        { spec: specs[1], duration: 1000 },
        { spec: specs[2], duration: 1000 },
    ]);
    const run = (index) =>
        applyShard({ projectRoot: root, specPattern: ['cypress/e2e/ui/**/*.spec.js'] }, { SPLIT: '2', SPLIT_INDEX: String(index) })
            .specPattern;
    const first = run(0);
    const second = run(1);
    assert.deepEqual(first, [path.join(root, specs[0])]);
    assert.deepEqual(second, [path.join(root, specs[2]), path.join(root, specs[1])]);
});

test('applyShard does nothing without a shard count or with a count of one', () => {
    const config = { projectRoot: '/nowhere', specPattern: ['x'] };
    assert.deepEqual(applyShard({ ...config }, {}), config);
    assert.deepEqual(applyShard({ ...config }, { SPLIT: '1', SPLIT_INDEX: '0' }), config);
});

test('applyShard rejects a shard index outside the count and an empty shard', () => {
    const root = repoWith(['cypress/e2e/ui/a.spec.js'], null);
    const config = () => ({ projectRoot: root, specPattern: ['cypress/e2e/ui/**/*.spec.js'] });
    assert.throws(() => applyShard(config(), { SPLIT: '2', SPLIT_INDEX: '2' }), /outside 0\.\.1/);
    assert.throws(() => applyShard(config(), { SPLIT: '2', SPLIT_INDEX: '1' }), /has no specs/);
});
