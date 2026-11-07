# scripts/

Repo tooling. Node by default; PHP only when a script must load the app or ORM; shell only to bootstrap before Node exists (`code-standards.md`).

| Folder | What goes here |
|--------|----------------|
| `lint/` | Checks that run in `npm run lint`: one file per check, one entry in the `checks` map in `lint/lint.js` |
| `build/` | Building, packaging and cleaning the app |
| `ci/` | Helpers the test and CI workflows share with local runs: docker config, test database, Cypress sharding, nightly gating |
| `release/` | Version bumps, changelog and release-notes input, docs notification |
| `marketing/` | The Playwright capture pipeline: screenshots, videos, manifest, visual guard |
| `maintainer/` | Repo upkeep run by a workflow or a maintainer: dependency and review bots, branch cleanup |
| `dev/` | Contributor setup and environment checks |
| `plugin/` | Tools for community plugin authors |

Tests for these scripts, workflow YAML and server config go in [`tests/tooling/`](../tests/tooling/). Locale tooling lives in [`locale/scripts/`](../locale/scripts/) and the Docker release build context in [`docker/`](../docker/).
