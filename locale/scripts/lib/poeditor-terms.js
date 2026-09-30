'use strict';

const { parsePoEntries } = require('./poeditor-plurals');

const MIN_DELETION_LIMIT = 25;
const DELETION_LIMIT_SHARE = 0.02;

function termKey(term, context) {
    return `${context ?? ''}\u0004${term}`;
}

function sourceTerms(poText) {
    const terms = new Map();
    for (const entry of parsePoEntries(poText)) {
        if (entry.msgid === '') continue;
        terms.set(termKey(entry.msgid, entry.msgctxt), { term: entry.msgid, context: entry.msgctxt ?? '' });
    }
    return terms;
}

function defaultDeletionLimit(remoteCount) {
    return Math.max(MIN_DELETION_LIMIT, Math.ceil(remoteCount * DELETION_LIMIT_SHARE));
}

/**
 * What importing messages.po with sync_terms=1 would do to POEditor.
 * sync_terms deletes every term missing from the file, together with its translations in every
 * language, so a broken extraction must not be able to empty the project: the plan is blocked
 * when the file is empty or would delete more than `maxDeletions` terms.
 *
 * @param {string} poText contents of locale/messages.po
 * @param {{term: string, context?: string}[]} remoteTerms POEditor terms/list result
 * @param {number} [maxDeletions] override for an intentional large cleanup
 */
function planTermSync(poText, remoteTerms, maxDeletions) {
    const local = sourceTerms(poText);
    const remote = new Map(remoteTerms.map(t => [termKey(t.term, t.context), { term: t.term, context: t.context ?? '' }]));

    const added = [...local].filter(([key]) => !remote.has(key)).map(([, t]) => t);
    const removed = [...remote].filter(([key]) => !local.has(key)).map(([, t]) => t);
    const limit = Number.isInteger(maxDeletions) ? maxDeletions : defaultDeletionLimit(remote.size);

    let blockedReason = null;
    if (local.size === 0) {
        blockedReason = 'locale/messages.po has no terms';
    } else if (removed.length > limit) {
        blockedReason = `would delete ${removed.length} terms (limit ${limit})`;
    }

    return { added, removed, unchanged: local.size - added.length, limit, blockedReason };
}

module.exports = { planTermSync, sourceTerms, defaultDeletionLimit };
