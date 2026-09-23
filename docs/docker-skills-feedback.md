# Docker Skills feedback: strengths, gaps, and suggested improvements

Feedback for the maintainers of [docker/skills](https://github.com/docker/skills),
based on using the skills during a container security and build-quality review.
Reviewed on 2026-09-23.

The skills provide a useful baseline for Dockerfile and Compose reviews. Their
strongest contribution is making important checks explicit: non-root execution,
credential handling, build/runtime separation, and development network exposure.
The main opportunity is to make recommendations more conditional and give agents
stronger ways to verify that a change actually works.

## What worked well

| Skill | What helped | Why it is useful |
| --- | --- | --- |
| `docker-project-foundations` | Loopback development ports and private datastores | Makes network exposure part of the initial setup, rather than a later security pass |
| `docker-build-strategies` | Separate build/runtime stages and non-root execution | Gives agents concrete starting points for reducing runtime privileges and unnecessary dependencies |
| `docker-build-strategies` | Secret mounts and nested credential exclusions | Connects credential hygiene to actual build behavior, including intermediate layers and caches |
| `docker-build-strategies` | Base-image pins and cache-aware instruction ordering | Encourages deliberate dependency updates and efficient builds |
| `docker-compose-patterns` | Health-based dependencies, volume guidance, and avoiding unnecessary socket mounts | Broadens the review beyond the Dockerfile to service relationships and host access |
| Installation documentation | Distinguishing installed skills from skills actually loaded by an agent | Sets a useful evidence standard: a plausible answer alone does not demonstrate skill use |

The separation between foundations, build strategies, and Compose patterns also
helps an agent select relevant guidance without loading the whole collection.

## Highest-priority improvements

### 1. Qualify security claims in examples

Some guidance would be stronger with more precise wording:

- **SSH host keys:** `ssh-keyscan` retrieves a key; it does not authenticate it.
  Recommend a reviewed `known_hosts` entry or verification against an independently
  trusted fingerprint. Avoid describing retrieval alone as verified pinning.
- **Docker socket:** explicitly state that mounting `docker.sock:ro` does not make
  the Docker API read-only. Explain the different impact of rootful and rootless
  daemon access, including Docker-group membership.
- **Build secrets:** keep the prohibition on secret-valued `ARG`/`ENV`, but explain
  their different exposure paths. Not every ARG becomes a filesystem layer;
  commands, metadata, history and provenance can expose values, while ENV also
  persists in runtime configuration.
- **Non-root ownership:** distinguish writable application data from executable
  code. A non-root process that owns all application files can still persist a
  compromise by modifying code.

### 2. Turn blanket rules into compatibility-aware defaults

- Prefer the smallest **compatible** runtime. Alpine or distroless can introduce
  libc, extension, locale or diagnostic constraints that need validation.
- Do not exclude `LICENSE` or all `*.md` automatically. License notices, runtime
  documentation and packaged resources may need to remain in the image.
- Treat `compose.yaml` as a preferred name for new projects, not a reason to rename
  an established setup whose scripts and CI already reference another valid name.
- Before recommending read-only filesystems, inventory runtime writes and check
  installer, upload, plugin and upgrade behavior.

Suggested instruction pattern: **recommend a default, state its assumptions, then
name the check that proves it fits the project.**

### 3. Strengthen verification beyond “the image builds”

The skills would benefit from reusable checks that verify:

- The configured user can actually start the service with reduced capabilities.
- The final filesystem excludes unwanted compilers and development headers,
  including packages inherited from an upstream runtime image.
- Required extensions and shared libraries load in the final image on each
  supported architecture.
- Credential exclusions work in an actual BuildKit context, including nested files.
- Resolved Compose configuration has the intended port bindings and mount modes,
  without printing interpolated credentials.
- Data survives container recreation, and persistent volumes do not mask updated
  application code.

Build success, non-root execution and vulnerability scanning establish different
facts. Reports should distinguish them and explicitly identify untested behavior.
Scanner findings also need vendor-status and reachability triage; counts alone
are not proof of exploitability or security improvement.

## Where these skills did not help enough

These gaps concern the reviewed skills; they are not claims that every Docker
skill should cover every part of delivery.

| Area | Missing guidance | Suggested addition or handoff |
| --- | --- | --- |
| Release integrity | Connecting the triggering release to the exact artifact and resulting image tag | A release checklist: event → source revision → asset → digest verification → image tag |
| CI trust boundaries | Shell interpolation, mutable Actions, publication credentials and event-trigger behavior | Link to a CI/release-security skill rather than expanding every Dockerfile skill |
| Runtime compatibility | Inherited packages, extension ABI paths, and distribution/library transitions | Require upstream-image inspection and final-image dependency probes |
| Multiple services serving one application | Protocol distinctions and matching static assets across web-server and application containers | An example separating immutable application files from persistent user data |
| Applications that modify themselves | Installer/updater/plugin requirements conflict with immutable-code advice | A writable-path decision guide with explicit compatibility tests |

## Suggested evaluation cases for the skills

Use small fixture projects that test whether an agent can:

1. Restrict exposed development services without breaking deliberate remote access.
2. Remove inherited build dependencies while retaining required runtime libraries.
3. Start a service as non-root under dropped capabilities.
4. Keep nested credentials out of build contexts while preserving required notices.
5. Explain why a read-only socket mount still grants Docker API access.
6. Recognize that a persistent application-code volume can hide image upgrades.
7. Identify a release/artifact mismatch and hand off to appropriate CI guidance.
8. Report a build as successful without claiming deployment readiness or a clean
   vulnerability scan.

## Sources and limits of this feedback

Reviewed skill sources at revision
`dc609b29de948e774368fbda82c61a3040c59b6a`:

- [Docker Project Foundations](https://github.com/docker/skills/blob/dc609b29de948e774368fbda82c61a3040c59b6a/skills/docker-project-foundations/SKILL.md)
- [Docker Build Strategies](https://github.com/docker/skills/blob/dc609b29de948e774368fbda82c61a3040c59b6a/skills/docker-build-strategies/SKILL.md)
- [Docker Compose Patterns](https://github.com/docker/skills/blob/dc609b29de948e774368fbda82c61a3040c59b6a/skills/docker-compose-patterns/SKILL.md)
- [Docker documentation PR #26169](https://github.com/docker/docs/pull/26169),
  reviewed as installation/discovery guidance, not a hardening specification.

The supplied [skills.sh Socket page](https://www.skills.sh/docker/skills/docker-project-foundations/security/socket)
returned HTTP 403, so its contents were not evaluated. That access failure is not
an assessment of the underlying skill's quality.

The skills were read directly from GitHub; plugin installation and automatic skill
selection were not tested. This is qualitative feedback from one application
review, not a controlled comparison or a benchmark. Independent engineering
judgment also contributed. Project-specific evidence is available separately in
the [ChurchCRM audit](docker-security-audit.md).
