const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '../..');
const workflow = fs.readFileSync(path.join(root, '.github/workflows/release-bookkeeping.yml'), 'utf8');

describe('release-bookkeeping.yml milestone management', () => {
    it('dates and closes milestones on stable release publication and supports manual retries', () => {
        assert.match(workflow, /^ {4}if: \(github\.event_name == 'release' && !github\.event\.release\.prerelease\) \|\| github\.event_name == 'workflow_dispatch'$/m);
        const job = workflow.slice(workflow.indexOf('  manage-milestones:'));
        assert.match(job, /gh release view "\$CURRENT_TAG" --json publishedAt/);
        assert.match(job, /if \[ "\$open_items" = "0" \]/);
        assert.match(job, /-f state=closed/);
    });
});
