# AI operating model

Use one accountable owner per task and one compact evidence packet across handoffs.
This role contract does not grant repository, mailbox, Discord, or publishing permissions.

| Work | Owner | Handoff |
|---|---|---|
| Development / CI escalations | Grok Bot | Investigate new technical failures and prepare a scoped implementation or release decision. Do not repeat Muse intake. |
| Complex engineering and docs | Claude Code | Resolve cross-layer design, security and data questions; write task-based docs grounded in released behavior. |
| Scoped implementation | Codex or Copilot | Implement one agreed code, test, tooling or documentation task with explicit acceptance checks. |
| Marketing and SEO | ChatGPT | Prepare verified campaign, blog, social and storyboard drafts using the owning repository's writing guides. |
| Independent review | CodeRabbit | Review a coherent diff; deterministic checks provide evidence and human maintainers decide approval and merge. |
| GitHub triage, community Gmail and other community operations | Muse | Deduplicate intake, prepare community replies and route one evidence packet to the next owner. |
| Discord support and opening GitHub issues from Discord | NanoClaw | Answer Discord questions and turn actionable Discord reports into linked issues within existing authorized scope. It does not own community Gmail or general GitHub triage. |

## Assignment and evidence

- Choose one implementer, not simultaneous competing agents for the same task.
- Include the issue/thread URL, source commit, reproduction, relevant files/task cards,
  acceptance criteria and focused validation commands. Check existing work first.
- Carry the original Discord thread and GitHub issue IDs so Muse does not repeat intake.
- Treat these as task owners, not claims about installed connectors, quotas or model quality.
- Root instructions and tool adapters link here rather than copying the role table.

## Execution and approval

Run tests, seeded captures, artifact validation, builds, link checks, counts and
bookkeeping directly in scripts or CI. Wake an LLM for a meaningful new failure,
ambiguous behavior, design or writing decision—not to watch successful runs.
Zero LLM tokens does not imply free compute, storage or APIs; a script that
requests an AI review still causes downstream inference.

Use each repository's canonical task and publishing guides. CRM owns product
capture specs; visuals publishes generated media; docs and the website consume
CDN references; marketing owns unpublished creative and the website published blogs.
Use the approved writing/brand guidance for that surface; do not invent product
claims, testimonials, metrics, logos or product UI. A shared voice consolidation
is separate work, not implemented by this role contract.

A maintainer-assigned task authorizes the agent to branch, commit, push and
open a draft PR inside that task's scope (`git-workflow.md` → Task approval
covers commit and push). External replies, issue creation, releases and
publication need the maintainer's specific authorization or existing
explicitly granted scope.
A role assignment is not permission to merge, close issues or send a message.
