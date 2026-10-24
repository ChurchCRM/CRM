/**
 * Splits the specs of one Cypress run across CI shards by recorded duration.
 *
 * The CI matrix sets SPLIT (shard count) and SPLIT_INDEX (zero-based shard). applyShard() narrows
 * config.specPattern to this shard's specs. Durations come from cypress/split-timings.json, keyed by
 * repo-relative path; a spec missing from the file gets the average, and a missing file weighs every
 * spec the same. Nothing is written back: scripts/split-timings.js refreshes the file.
 */

const fs = require('fs');
const path = require('path');
const { minimatch } = require('minimatch');

const DEFAULT_TIMINGS = path.join('cypress', 'split-timings.json');

function readDurations(file) {
    let text;
    try {
        text = fs.readFileSync(file, 'utf8');
    } catch (error) {
        if (error.code === 'ENOENT') {
            return {};
        }
        throw error;
    }
    const { durations = [] } = JSON.parse(text);
    return Object.fromEntries(durations.map(({ spec, duration }) => [spec, duration]));
}

// Longest spec first onto the lightest shard. Ties go to the lowest shard index and the
// lowest path, so every shard computes the same split.
function partition(specs, durations, total, index) {
    const known = Object.values(durations);
    const average = known.length ? known.reduce((sum, value) => sum + value, 0) / known.length : 1;
    const weighted = specs
        .map((spec) => ({ spec, weight: durations[spec] ?? average }))
        .sort((a, b) => b.weight - a.weight || (a.spec < b.spec ? -1 : 1));

    const shards = Array.from({ length: total }, () => ({ weight: 0, specs: [] }));
    weighted.forEach(({ spec, weight }) => {
        const lightest = shards.reduce((best, shard) => (shard.weight < best.weight ? shard : best));
        lightest.weight += weight;
        lightest.specs.push(spec);
    });
    return { specs: shards[index].specs.sort(), weight: shards[index].weight };
}

function findSpecs(root, patterns) {
    const found = [];
    const walk = (dir) =>
        fs.readdirSync(dir, { withFileTypes: true }).forEach((entry) => {
            const full = path.join(dir, entry.name);
            const relative = path.relative(root, full).split(path.sep).join('/');
            if (entry.isDirectory()) {
                walk(full);
            } else if (patterns.some((pattern) => minimatch(relative, pattern))) {
                found.push(relative);
            }
        });
    walk(path.join(root, 'cypress', 'e2e'));
    return found.sort();
}

function applyShard(config, env = process.env) {
    const total = Number(env.SPLIT || 0);
    if (!(total > 1)) {
        return config;
    }
    const index = Number(env.SPLIT_INDEX || 0);
    if (!Number.isInteger(index) || index < 0 || index >= total) {
        throw new Error(`SPLIT_INDEX ${env.SPLIT_INDEX} is outside 0..${total - 1}`);
    }

    const root = config.projectRoot;
    const patterns = [].concat(config.specPattern);
    const specs = findSpecs(root, patterns);
    const durations = readDurations(path.join(root, env.SPLIT_FILE || DEFAULT_TIMINGS));
    const matched = specs.filter((spec) => spec in durations).length;
    const shard = partition(specs, durations, total, index);
    if (!shard.specs.length) {
        throw new Error(`Shard ${index + 1} of ${total} has no specs; ${patterns.join(', ')} matched ${specs.length}`);
    }

    console.log(
        `shard ${index + 1} of ${total}: ${shard.specs.length} of ${specs.length} specs, ` +
            `about ${Math.round(shard.weight / 1000)} s; ${matched} of ${specs.length} specs have a recorded duration`,
    );
    config.specPattern = shard.specs.map((spec) => path.join(root, spec));
    return config;
}

module.exports = { applyShard, findSpecs, partition, readDurations };
