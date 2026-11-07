#!/usr/bin/env node

const { execFileSync } = require('node:child_process');

function gh(args) {
    const output = execFileSync('gh', ['api', ...args], { encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
    return output ? JSON.parse(output) : null;
}

function listAll(endpoint, api = gh) {
    const pages = api(['--paginate', '--slurp', endpoint]);
    if (!Array.isArray(pages) || !pages.every(Array.isArray)) {
        throw new Error(`Unexpected paginated response from ${endpoint}`);
    }
    return pages.flat();
}

function branchesToDelete(repository, pulls, branches, now = Date.now()) {
    if (!repository?.id || !repository.default_branch || !Array.isArray(pulls) || !Array.isArray(branches)) {
        throw new Error('Incomplete repository, pull request, or branch list');
    }

    const openHeads = new Set();
    for (const pull of pulls) {
        if (typeof pull.head?.ref !== 'string' || (pull.head.repo !== null && !pull.head.repo?.id)) {
            throw new Error('Incomplete open pull request head');
        }
        if (pull.head.repo?.id === repository.id) {
            openHeads.add(pull.head.ref);
        }
    }

    return branches.map((branch) => {
        if (typeof branch.name !== 'string' || typeof branch.protected !== 'boolean' || typeof branch.commit?.commit?.committer?.date !== 'string') {
            throw new Error('Incomplete branch data');
        }
        return branch;
    }).filter((branch) =>
        branch.name !== repository.default_branch && !branch.protected && !openHeads.has(branch.name) &&
        now - Date.parse(branch.commit.commit.committer.date) > 24 * 60 * 60 * 1000
    ).map((branch) => branch.name);
}

function main(api = gh, repo = process.env.GITHUB_REPOSITORY) {
    if (!repo) {
        throw new Error('GITHUB_REPOSITORY is required');
    }

    const repository = api([`repos/${repo}`]);
    const pulls = listAll(`repos/${repo}/pulls?state=open&per_page=100`, api);
    const branches = listAll(`repos/${repo}/branches?per_page=100`, api)
        .filter((branch) => !branch.protected && branch.name !== repository.default_branch)
        .map((branch) => ({ ...branch, commit: api([`repos/${repo}/commits/${branch.commit.sha}`]) }));
    const staleBranches = branchesToDelete(repository, pulls, branches);

    for (const branch of staleBranches) {
        const ref = branch.split('/').map(encodeURIComponent).join('/');
        api(['-X', 'DELETE', `repos/${repo}/git/refs/heads/${ref}`]);
        console.log(`Deleted ${branch}`);
    }
    console.log(`Deleted ${staleBranches.length} branch(es)`);
}

if (require.main === module) {
    main();
}

module.exports = { branchesToDelete, listAll, main };
