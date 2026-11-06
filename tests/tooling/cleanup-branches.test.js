const test = require('node:test');
const assert = require('node:assert/strict');
const { branchesToDelete, main } = require('../../scripts/maintainer/cleanup-branches');
const oldDate = new Date(Date.now() - 25 * 60 * 60 * 1000).toISOString();
const recentDate = new Date(Date.now() - 23 * 60 * 60 * 1000).toISOString();

const repository = { id: 1, default_branch: 'master' };
const branches = [
    { name: 'master', protected: false, commit: { commit: { committer: { date: oldDate } } } },
    { name: 'release/7.8', protected: true, commit: { commit: { committer: { date: oldDate } } } },
    { name: 'feature/with-pr', protected: false, commit: { commit: { committer: { date: oldDate } } } },
    { name: 'feature/without-pr', protected: false, commit: { commit: { committer: { date: oldDate } } } },
    { name: 'locale/translate/old', protected: false, commit: { commit: { committer: { date: oldDate } } } },
    { name: 'feature/topic#1', protected: false, commit: { commit: { committer: { date: oldDate } } } },
    { name: 'feature/recent', protected: false, commit: { commit: { committer: { date: recentDate } } } },
];
const pulls = [
    { head: { ref: 'feature/with-pr', repo: { id: 1 } } },
    { head: { ref: 'feature/without-pr', repo: { id: 2 } } },
];

test('deletes all repository branches without an open PR except default and protected branches', () => {
    assert.deepEqual(branchesToDelete(repository, pulls, branches), [
        'feature/without-pr',
        'locale/translate/old',
        'feature/topic#1',
    ]);
});

test('fetches every page before deleting and encodes branch path segments', () => {
    const calls = [];
    const api = (args) => {
        calls.push(args);
        if (args[0] === 'repos/ChurchCRM/CRM') return repository;
        if (args[2]?.includes('/pulls?')) return [[pulls[0]], [pulls[1]]];
        if (args[2]?.includes('/branches?')) {
            const bare = branches.map(({ name, protected: isProtected }) => ({ name, protected: isProtected, commit: { sha: name } }));
            return [[bare[0], bare[1]], bare.slice(2)];
        }
        if (args[0].includes('/commits/')) return branches.find((b) => b.name === args[0].split('/commits/')[1]).commit;
        return null;
    };

    main(api, 'ChurchCRM/CRM');
    assert.deepEqual(calls.filter((c) => c[0] === '-X'), [
        ['-X', 'DELETE', 'repos/ChurchCRM/CRM/git/refs/heads/feature/without-pr'],
        ['-X', 'DELETE', 'repos/ChurchCRM/CRM/git/refs/heads/locale/translate/old'],
        ['-X', 'DELETE', 'repos/ChurchCRM/CRM/git/refs/heads/feature/topic%231'],
    ]);
});

test('does not delete when a list is incomplete', () => {
    assert.throws(() => branchesToDelete(repository, pulls, [{ name: 'work', protected: false }]), /Incomplete branch data/);
    assert.throws(() => branchesToDelete(repository, [{ head: {} }], branches), /Incomplete open pull request head/);

    const api = (args) => {
        if (args[0] === 'repos/ChurchCRM/CRM') return repository;
        if (args[2]?.includes('/pulls?')) return [pulls];
        throw new Error('GitHub API failed');
    };
    assert.throws(() => main(api, 'ChurchCRM/CRM'), /GitHub API failed/);
});
