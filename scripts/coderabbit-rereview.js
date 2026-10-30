// A CodeRabbit CHANGES_REQUESTED review stays until CodeRabbit reviews again, even when every
// thread is resolved. When all threads are resolved, ask it to review again so its state shows
// whether anything still blocks. The review itself stays CodeRabbit's decision.
const REVIEWER = 'coderabbitai';
const COMMAND = '@coderabbitai review';
const MARKER = '<!-- coderabbit-rereview -->';
const BLOCKING_STATES = ['APPROVED', 'CHANGES_REQUESTED', 'DISMISSED'];

function needsRereview({ reviews, threads, comments }) {
    const decisions = reviews.filter((r) => r.author?.login === REVIEWER && BLOCKING_STATES.includes(r.state));
    const latest = decisions.at(-1);
    if (!latest || latest.state !== 'CHANGES_REQUESTED') return false;
    if (threads.length === 0 || threads.some((t) => !t.isResolved)) return false;
    return !comments.some((c) => c.body.includes(MARKER) && c.createdAt > latest.submittedAt);
}

const QUERY = `query($owner:String!, $repo:String!) {
  repository(owner:$owner, name:$repo) {
    pullRequests(states: OPEN, first: 50) {
      nodes {
        number
        isDraft
        reviewThreads(first: 100) { nodes { isResolved } }
        reviews(last: 100) { nodes { author { login } state submittedAt } }
        comments(last: 50) { nodes { body createdAt } }
      }
    }
  }
}`;

async function run({ github, context, core }) {
    const { owner, repo } = context.repo;
    const data = await github.graphql(QUERY, { owner, repo });
    for (const pr of data.repository.pullRequests.nodes) {
        if (pr.isDraft) continue;
        const input = {
            reviews: pr.reviews.nodes,
            threads: pr.reviewThreads.nodes,
            comments: pr.comments.nodes,
        };
        if (!needsRereview(input)) continue;
        await github.rest.issues.createComment({
            owner,
            repo,
            issue_number: pr.number,
            body: `${COMMAND}\n\nAll review threads are resolved. Asking for a fresh review so the blocking state is current.\n${MARKER}`,
        });
        core.info(`Asked CodeRabbit to review #${pr.number} again`);
    }
}

module.exports = { needsRereview, run, MARKER };
