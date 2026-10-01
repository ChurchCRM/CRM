# ChurchCRM Docker audit — PR #9667

Audit date: 2026-09-23. PR head: `bad07dcc4c53e19e22286cde38e0e93054fb6502`.
Master baseline: `d9d1e7b44`. Four pre-existing uncommitted edits were preserved
in the original checkout and incorporated into the separate PR-fix branch;
those edits are not attributed to this audit. No PR review was posted.

## Guidance and checklist

Docker's build skill recommends separate build/runtime stages, small compatible
bases, version/digest pins, dependency-first cache ordering, BuildKit secrets,
credential exclusions and a non-root final USER. Its Compose skill recommends
health-based dependencies, persistent data volumes and avoiding unnecessary
socket mounts. Foundations adds loopback development ports and private datastores.
See [the source-specific feedback](docker-skills-feedback.md) for citations and
limitations; the linked Docker docs PR is an installation guide.

| Check | Published PR / repository finding | Disposition |
| --- | --- | --- |
| Release identity | `gh release download` omits the triggering tag; retries/backports can package the latest release under the wrong version | Fix PR binds the exact tag and asset name; verifies GitHub asset SHA-256 |
| Archive layout | Shell `mv wrapper/*` drops dotfiles; subsequent `rmdir` expands to application directories and fails | Validated Python extraction preserves `.htaccess`; traversal, links, missing runtime files and common credential files are rejected |
| CI trust | Release tag interpolated directly into shell; Actions use mutable tags; checkout persists credentials | Environment handoff, version validation, SHA-pinned Actions, no persisted checkout credentials |
| Stable tags | Prereleases and old-release retries can replace stable tags | Reject prereleases; only GitHub's current latest release updates latest tags; serialize runs |
| Runtime identity | Apache starts as root; FPM uses a dynamically allocated `www` identity | Both prod images use Debian `www-data` UID/GID 33; Apache listens on 8080; test/dev retain 80 |
| Dependencies | Apache prod inherits its build stage; fresh upstream PHP images also contain compiler packages | Separate runtime base and purge inherited PHPIZE dependencies; verify required extensions still load |
| ABI and base suite | Floating `php:8.4-*`; FPM hardcodes extension ABI directory | Pin PHP 8.4.25/Trixie multi-platform digest; copy extension directory without hardcoded ABI |
| Network exposure | Dev database, application and unauthenticated Mailpit bind all interfaces | Master PR defaults to loopback, with explicit interface override |
| Host mounts | Dev application and whole repository bind-mounted read/write; seeds writable | Seeds become read-only; source stays writable for builds and remains development-only |
| Daemon/host access | No Docker socket, DOCKER_HOST, privileged mode, host PID/network or added capabilities found in checked Docker/Compose files | No application need for daemon access; prohibit introducing it as a convenience fix |
| Credentials | Production examples fall back to known passwords; ignore files miss common credentials | Fail closed on missing passwords; test nested credential exclusions with BuildKit |
| Artifact contents | Apache config copied into document root | Fix removes config from runtime document root; it remains in an underlying image layer and is not treated as a secret |
| FPM topology | Docs map HTTP to FastCGI and point to incomplete nginx example | Correct protocol/ports; explain static assets and missing nginx config; no claim of turnkey production deployment |
| Writable code | Entire application remains owned by web process | Open design issue: reconcile setup/updater/plugins with immutable code; non-root alone does not prevent persistent PHP compromise |
| Availability | No production resource policy or application readiness checks | Deployment follow-up; smoke tests are not database readiness checks |
| Persistence | Release preview has no DB/data lifecycle; reused whole-code volumes can mask new image code | Document targeted persistence, backup and image-based upgrades; restore acceptance test still needed |
| Supply chain | No explicit SBOM/provenance in original workflow | Enable both; signatures, scan gates and registry immutability remain separate follow-ups |

## Severity and draft review comments

These comments refer to the original PR head and are drafts for a maintainer.

1. **High — `.github/workflows/docker-release.yml:48`.** Please pass the triggering
   tag to `gh release download` and request the exact versioned asset. Without a
   tag, retrying an older release builds the latest release's code with an older
   image tag. Check the asset digest before extraction.
2. **High — `.github/workflows/docker-release.yml:51-52`.** The packaging script
   writes a `churchcrm/` wrapper including `.htaccess`. `mv wrapper/*` omits hidden
   files, and the following directory glob includes newly moved app directories.
   Replace this with validated extraction and a regression test for hidden files.
3. **High — `.github/workflows/docker-release.yml:37`.** Move release context into
   an environment variable and validate it before using it in shell or image tags.
   Exploitation requires control over a release/tag; this is not an anonymous
   application vulnerability. Pin the third-party Actions used by this job.
4. **Medium — `docker/Dockerfile.churchcrm-apache-php8:55`.** The prod stage inherits
   build dependencies and root identity. Use a separate compatible runtime stage,
   remove inherited toolchain packages, and test Apache as an unprivileged user.
5. **Medium — `docker/DOCKER_RELEASE.md:19`.** FPM's 9000 is FastCGI, not HTTP.
   Publishing it directly both fails the browser quick start and exposes a backend
   interface. Provide Apache HTTP instructions and document private FPM routing.
6. **Follow-up — production deployment examples.** Before recommending these for
   live member data, validate setup, login, uploads, volume ownership, recreation,
   backups/restoration and image upgrades with the intended reverse proxy.

## Socket and permission boundaries

A client able to command a rootful Docker daemon can create privileged containers
or mount host paths. Mounting `docker.sock:ro` does not make its API read-only.
Rootless Docker reduces host-root impact but still exposes the daemon user's
containers and accessible files. A Docker group grants daemon access; non-root
container USER does not compensate for it. No exploit or container-escape attempt
was performed. Absence of a socket does not prove immunity to kernel/runtime bugs.

Writable repository mounts are an independent host-write path: a compromised dev
web process can alter host files allowed by the mount and file permissions.
`no-new-privileges` and dropped capabilities do not make those mounts read-only.
The whole-repository mount is therefore not appropriate for production.

## Existing review advice that needed correction

The review bot claims `php:8.4-fpm` is necessarily Bookworm and recommends ICU 72
and libzip4. Current upstream manifests and a successful runtime probe identify
Debian 13 Trixie, ICU 76 and libzip5. Pin the suite and digest rather than following
an assumption about a moving tag. Also, a missing Docker COPY source fails a build;
it does not silently produce an image without extensions.

An empty named volume normally gets a copy of image data on first mount. Bind
mounts obscure it; existing named volumes preserve old data across image upgrades.
Deleting the FPM shared-code volume does not provision nginx's static files.

## Image vulnerability scan

Trivy 0.74.0 scanned both rebuilt ARM64 images on 2026-09-23 with a freshly
fetched database, `--scanners vuln --severity HIGH,CRITICAL`. These scans used the
7.7.0 release fixture, not an unpublished future release. Results:

| Image | High package/advisory occurrences | Critical occurrences | Unique CVE IDs |
| --- | ---: | ---: | ---: |
| Apache | 66 | 1 | 24 |
| FPM | 58 | 1 | 20 |

The remaining critical finding is `CVE-2026-6653` in Debian `libxml2`
`2.12.7+dfsg+really2.9.14-2.1+deb13u3`. Trivy reports it as affected; no fixed
version was supplied for any listed finding. No high/critical findings were
reported in the scanned Composer inventory. This is not a statement that the
application or source-built PHP is free of vulnerabilities. Package detection,
vendor status and application reachability need separate review.

[The grouped scan results](docker-vulnerability-summary.csv) retain identifiers,
packages, versions and scanner status. These are scanner observations, not
confirmed exploit paths. Do not claim a clean security scan or publish on that
basis; triage the runtime-library findings and track upstream fixes first.

An earlier scan also found Linux/libc development headers left behind because
PHPIZE_DEPS contains the virtual `libc-dev` name. Explicit concrete-package
removal eliminated those unnecessary headers. Kernel CVEs attached to header
packages do not demonstrate a vulnerable running host kernel, and lowering that
count is not proof of closing a container escape.

## Validation and remaining limits

- Master: resolved Compose tests cover development/test/three CI profiles,
  loopback override, read-only seeds, and required production passwords.
- Master: real BuildKit scratch exports confirm both ignore files exclude nested
  credentials while keeping a public file and `.env.example`.
- PR fix: five archive tests pass; actual 7.7.0 release extracts with `.htaccess`.
- PR fix: both ARM64 prod builds succeed. Runtime probes pass as UID 33 with all
  capabilities dropped and `no-new-privileges`; all required PHP extensions load,
  compiler tools and Xdebug are absent, Apache returns a valid initial HTTP status.
- Both branches: `npm run lint` passes. Release workflows pass actionlint;
  shell syntax and diff whitespace checks pass.
- Added amd64 PR build/smoke workflow. Local amd64 execution, full application
  acceptance, DockerHub publishing, attestation inspection and restore tests are
  not claimed. Existing application Cypress tests were not run for this audit.

No repository branch was merged and no production service or registry was changed.
