const { describe, it } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.join(__dirname, '..');

function read(relative) {
    return fs.readFileSync(path.join(root, relative), 'utf8');
}

function jobs(relative) {
    const text = read(relative);
    const jobsIndex = text.indexOf('\njobs:\n');
    assert.notEqual(jobsIndex, -1, `${relative} has no jobs block`);
    const jobsText = text.slice(jobsIndex + '\njobs:\n'.length);
    const marks = [...jobsText.matchAll(/^  ([A-Za-z0-9_-]+):\n/gm)];
    const map = {};
    marks.forEach((mark, index) => {
        const end = index + 1 < marks.length ? marks[index + 1].index : jobsText.length;
        map[mark[1]] = jobsText.slice(mark.index, end);
    });
    return map;
}

function shellLines(text, scriptIndent) {
    const lines = text.split('\n');
    const out = [];
    let inScript = false;
    const body = new RegExp(`^ {${scriptIndent}}`);
    for (const line of lines) {
        if (/^\s+run: \|$/.test(line)) {
            inScript = true;
            continue;
        }
        if (/^\s+run: /.test(line)) {
            out.push(line);
            inScript = false;
            continue;
        }
        if (!inScript) continue;
        if (line.trim() === '' || body.test(line)) out.push(line);
        else inScript = false;
    }
    return out;
}

describe('marketing-capture-assets.yml', () => {
    const workflow = jobs('.github/workflows/marketing-capture-assets.yml');

    it('runs the other locales only after English', () => {
        assert.equal(workflow.capture_en.includes('\n    needs:'), false);
        assert.match(workflow.capture, /^ {4}needs: capture_en$/m);
    });

    it('merges only after English and the other locales', () => {
        assert.match(workflow.merge, /^ {4}needs: \[capture_en, capture\]$/m);
    });

    it('publishes only on master, after the merge', () => {
        assert.match(workflow.publish, /^ {4}needs: merge$/m);
        assert.match(workflow.publish, /^ {4}if: github\.ref == 'refs\/heads\/master'$/m);
    });
});

describe('release-publish.yml marketing capture', () => {
    const job = jobs('.github/workflows/release-publish.yml').marketing_capture;
    const script = shellLines(job, 10).join('\n');

    it('dispatches marketing-capture-assets.yml on master', () => {
        assert.match(script, /gh workflow run marketing-capture-assets\.yml --ref master/);
        assert.equal(script.includes('${{'), false);
        assert.match(job, /TARGET_COMMIT: \$\{\{ needs\.publish\.outputs\.target_commit \}\}/);
    });

    it('reports a failed dispatch and leaves the release workflow green', () => {
        assert.match(job, /^ {4}continue-on-error: true$/m);
        assert.match(script, /::warning::Could not start marketing-capture-assets\.yml on master/);
        assert.match(script, /exit 1/);
    });
});

describe('marketing-capture action', () => {
    const action = read('.github/actions/marketing-capture/action.yml');

    it('passes the locale to the shell through the environment', () => {
        assert.match(action, /LOCALE: \$\{\{ inputs\.locale \}\}/);
        assert.match(action, /capture-all-locales\.sh "\$LOCALE"/);
        for (const line of shellLines(action, 8)) {
            assert.equal(line.includes('${{'), false, line);
        }
    });
});
