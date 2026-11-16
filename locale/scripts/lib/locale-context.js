'use strict';

/**
 * Where a term is used in the code, for translators.
 *
 * Batch files are bare strings, and a short string ("Latest Notes",
 * "%s by %s") is ambiguous without seeing where it appears. locale:missing
 * writes locale/terms/context.json — { term: ["src/path.php:123", ...] } —
 * alongside the queue, and locale-translate --export includes it in the
 * payload a translator works from. Locations come from `git grep` on the
 * best literal fragment of the term (messages.po carries no locations),
 * the same approach as locale-terms-diff.js.
 */

const { execFileSync } = require('child_process');
const fs = require('fs');
const { searchFragment } = require('./locale-terms-diff');

const SEARCH_PATHS = ['src', 'webpack', ':!src/locale', ':!src/skin/external', ':!src/vendor', ':!*.min.js', ':!*.map'];
const MAX_LOOKUPS = 400;
const MAX_LOCATIONS = 3;

function gitGrep(fragment) {
    try {
        return execFileSync('git', ['grep', '-n', '-I', '-F', '-e', fragment, '--', ...SEARCH_PATHS], { encoding: 'utf8' });
    } catch {
        return ''; // git grep exits 1 when nothing matches
    }
}

/** Up to MAX_LOCATIONS "path:line" locations for a term, [] when none are found. */
function findTermLocations(term, grep = gitGrep) {
    const fragment = searchFragment(term);
    if (!fragment) return [];
    const seen = new Set();
    const locations = [];
    for (const line of grep(fragment).split('\n')) {
        if (!line) continue;
        const loc = line.split(':').slice(0, 2).join(':');
        if (seen.has(loc)) continue;
        seen.add(loc);
        locations.push(loc);
        if (locations.length >= MAX_LOCATIONS) break;
    }
    return locations;
}

/** { term: [locations] } for the terms that have any; lookups are capped like the terms diff. */
function buildContext(terms, grep = gitGrep) {
    const context = {};
    for (const term of [...terms].sort((a, b) => a.localeCompare(b)).slice(0, MAX_LOOKUPS)) {
        const locations = findTermLocations(term, grep);
        if (locations.length) context[term] = locations;
    }
    return context;
}

/** Writes the context file for the queued terms; removes it when the queue is empty. Returns the term count written. */
function writeContext(file, terms, grep = gitGrep) {
    const context = buildContext(terms, grep);
    if (Object.keys(context).length === 0) {
        if (fs.existsSync(file)) fs.unlinkSync(file);
        return 0;
    }
    fs.writeFileSync(file, `${JSON.stringify(context, null, 2)}\n`, 'utf8');
    return Object.keys(context).length;
}

module.exports = { findTermLocations, buildContext, writeContext, MAX_LOOKUPS };
