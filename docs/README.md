# ChurchCRM docs

Hand-written guides for maintainers and contributors. Agent rules live in `.agents/skills/`; link to them, do not restate them.

| Folder | Audience | Holds |
|--------|----------|-------|
| [`maintainers/`](maintainers/) | Maintainers | Runbooks: [release process](maintainers/release-process.md), [locale pipeline](maintainers/locale-pipeline.md), [GitHub Actions](maintainers/github-actions.md) |
| [`contributing/`](contributing/) | Contributors | [Issue readiness](contributing/issue-readiness.md), [PR review gates](contributing/pr-review.md) |
| [`decisions/`](decisions/) | Anyone | Numbered RFCs and ADRs (`NNNN-slug.md`) |
| [`design/`](design/) | Implementers | One design per epic |
| [`openapi/`](openapi/) | Tooling | Spec generator. `generated/` is committed by CI on `master` |

## Where a new doc goes

1. Pick the folder by audience.
2. A decision or design starts with `Status` (Proposed, Accepted, Active, Shipped, Archived), `Date` and an owner. Decisions take the next number.
3. When a design ships, move its durable rules into a skill and mark it Archived or delete it.
4. Split a doc past about 500 lines into a folder with one file per section.
