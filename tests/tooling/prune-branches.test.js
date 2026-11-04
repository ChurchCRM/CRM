const { describe, it, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const skip = process.platform === 'win32' && 'fake shell binaries need a POSIX shell';
const SCRIPT = path.join(__dirname, '../../scripts/maintainer/prune-branches.js');
const trees = [];
const cleanEnv = Object.fromEntries(Object.entries(process.env).filter(([key]) => !key.startsWith('GIT_')));

function sh(cwd, cmd, args) {
    const result = spawnSync(cmd, args, { cwd, encoding: 'utf8', env: cleanEnv });
    assert.equal(result.status, 0, `${cmd} ${args.join(' ')}: ${result.stderr}`);
    return result.stdout.trim();
}

// A clone whose origin URL names ChurchCRM on GitHub, redirected to a local bare repo.
function makeRepo({ open = [], merged = [] } = {}) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'crm-prune-'));
    trees.push(root);
    const bare = path.join(root, 'origin.git');
    const work = path.join(root, 'work');
    const bin = path.join(root, 'bin');
    fs.mkdirSync(bin);
    sh(root, 'git', ['init', '--bare', '-b', 'master', bare]);
    sh(root, 'git', ['init', '-b', 'master', work]);
    for (const [key, value] of [['user.email', 't@example.com'], ['user.name', 'T'], [`url.${bare}.insteadOf`, 'https://github.com/ChurchCRM/CRM.git']]) {
        sh(work, 'git', ['config', key, value]);
    }
    sh(work, 'git', ['remote', 'add', 'origin', 'https://github.com/ChurchCRM/CRM.git']);
    commit(work, 'base');
    sh(work, 'git', ['push', 'origin', 'master']);
    const gh = path.join(bin, 'gh');
    fs.writeFileSync(gh, '#!/bin/sh\nif echo "$@" | grep -q -- "--state open"; then cat "$OPEN"; else cat "$MERGED"; fi\n');
    fs.chmodSync(gh, 0o755);
    const env = { ...cleanEnv, PATH: `${bin}:${process.env.PATH}`, OPEN: path.join(root, 'open.json'), MERGED: path.join(root, 'merged.json') };
    setPrs({ env }, { open, merged });
    return { work, env, bare };
}

function setPrs({ env }, { open, merged }) {
    fs.writeFileSync(env.OPEN, JSON.stringify(open.map((headRefName) => ({ headRefName }))));
    fs.writeFileSync(env.MERGED, JSON.stringify(merged));
}

function commit(cwd, message) {
    fs.writeFileSync(path.join(cwd, `${message}.txt`), message);
    sh(cwd, 'git', ['add', '.']);
    sh(cwd, 'git', ['commit', '-m', message]);
    return sh(cwd, 'git', ['rev-parse', 'HEAD']);
}

function branchWithCommit(work, name) {
    sh(work, 'git', ['checkout', '-b', name, 'master']);
    const sha = commit(work, name.replace(/\W/g, '-'));
    sh(work, 'git', ['push', 'origin', name]);
    sh(work, 'git', ['checkout', 'master']);
    return sha;
}

function prune(repo, args = []) {
    return spawnSync(process.execPath, [SCRIPT, ...args], { cwd: repo.work, encoding: 'utf8', env: repo.env });
}

const planId = (result) => result.stdout.match(/--apply (\w+)/)[1];
const branches = (work) => sh(work, 'git', ['branch', '--format=%(refname:short)']).split('\n');
const mergedPr = (name, sha, owner = 'ChurchCRM') => ({ headRefName: name, headRefOid: sha, headRepositoryOwner: { login: owner } });

afterEach(() => {
    for (const root of trees.splice(0)) fs.rmSync(root, { recursive: true, force: true });
});

describe('prune-branches.js', { skip }, () => {
    it('previews without deleting and applies exactly the previewed plan', () => {
        const repo = makeRepo();
        const sha = branchWithCommit(repo.work, 'fix/landed');
        setPrs(repo, { open: [], merged: [mergedPr('fix/landed', sha)] });
        const preview = prune(repo);
        assert.match(preview.stdout, /Local branches to delete \(1\)\n {2}fix\/landed/);
        assert.ok(branches(repo.work).includes('fix/landed'));
        const applied = prune(repo, ['--apply', planId(preview)]);
        assert.equal(applied.status, 0, applied.stderr);
        assert.ok(!branches(repo.work).includes('fix/landed'));
    });

    it('refuses to apply when the plan changed since the preview', () => {
        const repo = makeRepo();
        const first = branchWithCommit(repo.work, 'fix/one');
        setPrs(repo, { open: [], merged: [mergedPr('fix/one', first)] });
        const id = planId(prune(repo));
        const second = branchWithCommit(repo.work, 'fix/two');
        setPrs(repo, { open: [], merged: [mergedPr('fix/one', first), mergedPr('fix/two', second)] });
        const applied = prune(repo, ['--apply', id]);
        assert.equal(applied.status, 1);
        assert.match(applied.stderr, /changed since the preview/);
        assert.ok(branches(repo.work).includes('fix/one') && branches(repo.work).includes('fix/two'));
    });

    it('keeps a branch with an open PR from any author, even when merged work shares its name', () => {
        const repo = makeRepo();
        const sha = branchWithCommit(repo.work, 'fix/shared');
        setPrs(repo, { open: ['fix/shared'], merged: [mergedPr('fix/shared', sha)] });
        const preview = prune(repo);
        assert.match(preview.stdout, /fix\/shared {2}\[kept: open PR\]/);
    });

    it('keeps a branch whose tip is not the merged PR head, even with a gone upstream', () => {
        const repo = makeRepo();
        const merged = branchWithCommit(repo.work, 'fix/reused');
        sh(repo.work, 'git', ['checkout', 'fix/reused']);
        commit(repo.work, 'extra-local-work');
        sh(repo.work, 'git', ['checkout', 'master']);
        sh(repo.bare, 'git', ['branch', '-D', 'fix/reused']);
        setPrs(repo, { open: [], merged: [mergedPr('fix/reused', merged)] });
        const preview = prune(repo);
        assert.match(preview.stdout, /fix\/reused {2}\[kept: not merged\]/);
        assert.match(preview.stdout, /Local branches to delete \(0\)/);
    });

    it('ignores merged PRs from another repository', () => {
        const repo = makeRepo();
        const sha = branchWithCommit(repo.work, 'fix/forked');
        setPrs(repo, { open: [], merged: [mergedPr('fix/forked', sha, 'someone-else')] });
        assert.match(prune(repo, ['--remote']).stdout, /Remote branches to delete \(0\)/);
    });

    it('deletes remote branches of merged PRs but not the current branch or security/*', () => {
        const repo = makeRepo();
        const landed = branchWithCommit(repo.work, 'fix/remote-landed');
        const secret = branchWithCommit(repo.work, 'security/fix');
        const checkedOut = branchWithCommit(repo.work, 'fix/checked-out');
        sh(repo.work, 'git', ['checkout', 'fix/checked-out']);
        setPrs(repo, { open: [], merged: [mergedPr('fix/remote-landed', landed), mergedPr('security/fix', secret), mergedPr('fix/checked-out', checkedOut)] });
        const preview = prune(repo, ['--remote']);
        assert.match(preview.stdout, /Remote branches to delete \(1\)\n {2}fix\/remote-landed/);
        const applied = prune(repo, ['--remote', '--apply', planId(preview)]);
        assert.equal(applied.status, 0, applied.stderr);
        const remoteBranches = sh(repo.bare, 'git', ['branch', '--format=%(refname:short)']).split('\n');
        assert.ok(!remoteBranches.includes('fix/remote-landed'));
        assert.ok(remoteBranches.includes('security/fix') && remoteBranches.includes('fix/checked-out'));
    });

    it('stops when the PR list may be truncated', () => {
        const repo = makeRepo();
        setPrs(repo, { open: Array.from({ length: 1000 }, (_, i) => `b${i}`), merged: [] });
        const result = prune(repo);
        assert.equal(result.status, 1);
        assert.match(result.stderr, /may be truncated/);
    });

    it('parses branch names that contain a pipe', () => {
        const repo = makeRepo();
        const sha = branchWithCommit(repo.work, 'fix/a|b');
        setPrs(repo, { open: [], merged: [mergedPr('fix/a|b', sha)] });
        assert.match(prune(repo).stdout, /Local branches to delete \(1\)\n {2}fix\/a\|b/);
    });
});
