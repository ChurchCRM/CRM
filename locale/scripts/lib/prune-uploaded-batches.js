const fs = require('fs');
const path = require('path');

/**
 * Locales flagged skip_audit never get a POEditor "untranslated" export, so nothing else clears their
 * hand-added batches. A term is uploaded once the fresh download holds the same value; drop it, and drop
 * the file when nothing is left.
 */
function pruneUploadedBatches(batchDir, downloaded) {
    if (!fs.existsSync(batchDir)) return { removed: 0, files: 0 };
    let removed = 0;
    let files = 0;
    for (const name of fs.readdirSync(batchDir).filter((f) => f.endsWith('.json'))) {
        const file = path.join(batchDir, name);
        const batch = JSON.parse(fs.readFileSync(file, 'utf8'));
        const kept = Object.fromEntries(
            Object.entries(batch).filter(([term, value]) => JSON.stringify(downloaded[term]) !== JSON.stringify(value)),
        );
        removed += Object.keys(batch).length - Object.keys(kept).length;
        if (Object.keys(kept).length === 0) {
            fs.unlinkSync(file);
            files++;
        } else if (Object.keys(kept).length < Object.keys(batch).length) {
            fs.writeFileSync(file, JSON.stringify(kept, null, 2) + '\n');
        }
    }
    if (fs.readdirSync(batchDir).length === 0) fs.rmdirSync(batchDir);
    return { removed, files };
}

module.exports = { pruneUploadedBatches };
