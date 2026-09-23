# Feedback for Docker Skills maintainers: ChurchCRM audit

Shareable field report, 2026-09-23. This reports one repository audit, not a
benchmark of model quality. No private credentials or member data are included.

## Task and sources

We audited [ChurchCRM PR #9667](https://github.com/ChurchCRM/CRM/pull/9667):
release-zip packaging into Apache/PHP-FPM images, plus existing Compose setups.
Docker Skills was read directly from GitHub, not installed as a plugin. The
repository revision observed during the audit was
`dc609b29de948e774368fbda82c61a3040c59b6a`.

Read and applied:

- [docker-build-strategies/SKILL.md](https://github.com/docker/skills/blob/dc609b29de948e774368fbda82c61a3040c59b6a/skills/docker-build-strategies/SKILL.md)
- [docker-compose-patterns/SKILL.md](https://github.com/docker/skills/blob/dc609b29de948e774368fbda82c61a3040c59b6a/skills/docker-compose-patterns/SKILL.md)
- [docker-project-foundations/SKILL.md](https://github.com/docker/skills/blob/dc609b29de948e774368fbda82c61a3040c59b6a/skills/docker-project-foundations/SKILL.md)
- [Docker docs PR #26169](https://github.com/docker/docs/pull/26169), head
  `7a5080be3acf6b64af45929d5606eb9cc3307e29`, open at review time.

The supplied [skills.sh Socket URL](https://www.skills.sh/docker/skills/docker-project-foundations/security/socket)
returned HTTP 403 with and without `www`. We cannot assess its content, and do not
attribute Docker socket recommendations or findings to it. A security-audit page
about a skill must also not be assumed to be container-daemon hardening guidance
without reading it.

## Where the skills helped

| Skill guidance | Concrete use in this audit | Evidence |
| --- | --- | --- |
| Loopback development ports; keep datastores private | Found all-interface MariaDB/Mailpit/app bindings; changed defaults to loopback with explicit opt-in | Compose tests cover rendered configurations, not just text |
| Non-root final USER | Changed Apache production runtime to UID 33 and port 8080; standardized FPM UID | Both image smoke tests pass with all capabilities dropped and no-new-privileges |
| Separate build/runtime dependencies | Found Apache inheriting compiler/header dependencies; inspected upstream runtime base too | Real builds plus probes for missing gcc/g++/make and loaded PHP extensions |
| Image pins and compatible runtimes | Replaced floating PHP references with explicit Trixie/PHP version and multi-platform digests | Inspected current registry manifests and runtime OS/library versions |
| Build context credential hygiene | Found incomplete ignore rules; excluded nested environment/registry/auth/key files | BuildKit scratch exports test actual inclusion behavior |
| Compose volume boundaries | Changed seed mounts to read-only; documented writable source mounts as dev-only | Rendered Compose assertions and scope review |
| Avoid socket mounts | Gave a clear invariant for app services; repository had no socket mount to fix | Static Docker/Compose search; no claim of an escape exploit |
| Skills do not replace verification | Kept builds and runtime checks as the acceptance criterion | Caught differences between generic advice, review-bot assumptions and this app |

These are guidance-assisted findings. We cannot causally attribute every finding
to a skill: prior Docker knowledge, ChurchCRM source inspection and existing local
edits also contributed. In particular, the pre-existing local edits already added
Apache extension parity, a separate Apache runtime stage, an ABI-path fix,
prerelease gating and parts of the documentation correction.

## Where guidance was absent or insufficient

1. **Release provenance across systems.** The most consequential bugs were in
   GitHub Actions: omitted release tag, unsafe shell interpolation, fragile ZIP
   flattening, mutable Actions and `GITHUB_TOKEN` event suppression. The Dockerfile
   checklist did not cover that path. Add a release-artifact handoff checklist
   covering event → source revision → exact asset → checksum → image tag.
2. **PHP runtime dependencies.** “Use a minimal runtime” did not identify inherited
   compiler packages in official PHP images, PHP ABI directory coupling or Debian
   suite/library transitions. Suggest inspecting the upstream Dockerfile and
   checking extension loading in the final image on each architecture.
3. **Legacy writable applications.** Generic non-root and ownership examples do
   not distinguish code ownership from required runtime writes. ChurchCRM's setup,
   updater and plugins require an explicit compatibility design before an
   immutable root filesystem can be recommended. Ask for a writable-path inventory
   and test installer/upload/plugin/upgrade workflows.
4. **Multi-container application contents.** FPM does not serve HTTP, and nginx
   needs matching static assets. The guidance did not explain why mounting old
   application code in a persistent volume can defeat image upgrades. Add a
   FastCGI example with clear static-code and persistent-data ownership.
5. **Source accessibility.** The supplied Socket page was inaccessible. The docs
   PR supplied installation/discovery guidance, not a security specification. A
   direct canonical source/permalink for security guidance would improve audits.
6. **Verification integration.** Skill scripts are useful beginnings; a build
   alone does not establish daemon isolation, correct USER startup, ABI health,
   deployment readiness or data durability. Include targeted runtime assertions
   and make the untested surface explicit. A Trivy scan found leftover libc/Linux
   headers after purging PHPIZE_DEPS: removing a virtual package name had not
   removed the concrete package. The final images still have reported runtime
   library advisories, including a libxml2 critical finding without a listed fix.
   Build success and a smaller attack surface must not be presented as a clean
   vulnerability scan.

## Recommendations requiring qualification

- **Do not remove LICENSE or all `*.md` by default.** The build skill's exclusion
  list includes those names. Projects may need license notices, runtime help or
  packaged docs. Classify required artifacts first; this audit did not adopt a
  blanket exclusion.
- **Choose a compatible base before the smallest base.** Alpine/distroless advice
  needs an ABI/libc/locales qualifier for PHP extensions and gettext. This change
  retained Debian and locales; shrinking them requires functional coverage.
- **`ARG` and `ENV` are unsafe secret channels, but not identically stored.** Both
  can leak through metadata/history/provenance or build commands; ENV also persists
  in runtime configuration. Avoid claiming every ARG necessarily becomes a
  filesystem layer or every secret is always visible via one command.
- **`ssh-keyscan` does not authenticate a host key.** The build skill says it “pins
  the known fingerprint at build time.” Fetching a key from the same untrusted
  network is trust-on-first-use, not verification against a trusted fingerprint.
  Recommend a reviewed `known_hosts` entry or independently verified fingerprint.
- **Read-only socket mount is not a read-only API.** Add this explicitly beside
  “Do not mount the Docker socket unless ...”. Include rootful/rootless impact and
  why Docker-group membership is a daemon-control boundary.
- **Non-root plus blanket application ownership is partial hardening.** Compromised
  PHP can still modify writable code. Distinguish writable data from executable
  files rather than recommending application-wide chown without that caveat.
- **Treat ownership/COPY advice as testable.** The build skill states named
  `--chown` users are unavailable with `COPY --link`. This audit did not validate
  that assertion; verify it against current BuildKit documentation/tests before
  making it an unconditional rule. Numeric IDs remain useful for stable volumes.
- **Naming is not a security finding.** Existing `docker-compose.yaml` paths are
  referenced throughout CI. Renaming solely to satisfy the preferred filename
  would add churn without changing exposure.

## Suggested skill acceptance scenarios

- Development Compose with unauthenticated mail inbox and DB exposed on all hosts.
- Official PHP runtime that inherits compilers despite a separate build stage.
- Apache startup as non-root with all capabilities dropped on a high port.
- Release ZIP with wrapper directory, hidden `.htaccess`, links and traversal names.
- Retrying an old stable release after a newer release becomes latest.
- FPM plus nginx where deleting a shared volume removes nginx's static assets.
- Installer/updater application with writable PHP code and persistent user uploads.
- Secret in nested `.npmrc` and a runtime-required license/documentation file.

The [companion audit](docker-security-audit.md) contains findings, draft review
comments, actual checks and remaining limits. The changes are proposed for review;
this report does not certify ChurchCRM as production-secure.
