# docs-staging/ — prep area for external documentation

This directory is **not** built or deployed by ChurchCRM. It exists so
that documentation destined for repositories outside this one — chiefly
[`docs.churchcrm.io`](https://docs.churchcrm.io) — can be drafted,
reviewed, and merged in the same pull request as the code changes it
describes.

## Why stage here?

ChurchCRM ships as a web application in this repo and as user-facing
docs in a separate wiki/docs repo. When a code change introduces
behaviour that needs end-user documentation (a new admin feature, a new
install flow, a new security contract), the documentation should go
live at the same time as the code, ideally in the same review. Doing
that cleanly means:

1. Writing the doc as part of the code PR.
2. Landing it in a neutral location inside this repo so reviewers can
   read the full context.
3. Copying it into the external docs repo in a second PR that lands
   immediately after the code PR is merged.

Step 2 is what `docs-staging/` is for.

## Rules

- **Never import or include files from this directory at runtime.** It
  is drafts only, not application assets.
- **Mirror the target repo's directory layout.** Files destined for a
  `wiki/` tree in the external repo live under `docs-staging/wiki/…`.
- **Use the same front-matter and link style the target repo expects.**
  For docs.churchcrm.io that means standard GFM with relative links.
- **Sign off each doc with the date and the matching code commit.**
  Add a `<!-- staged: YYYY-MM-DD — commit <sha> -->` HTML comment at
  the top of every staged file so the docs maintainer knows what
  shipped together.
- **When the external repo catches up, delete the staged file** from
  this directory in a follow-up commit. Stale drafts are a
  maintenance hazard.

## Current contents

| File | Target | Notes |
|------|--------|-------|
| `wiki/plugins/README.md` | `wiki/plugins/README.md` | Plugin overview landing page |
| `wiki/plugins/installing-community-plugins.md` | `wiki/plugins/installing-community-plugins.md` | End-user install flow |
| `wiki/plugins/plugin-security-and-compliance.md` | `wiki/plugins/plugin-security-and-compliance.md` | Risk levels, permissions, admin audit |
| `wiki/plugins/plugin-localization.md` | `wiki/plugins/plugin-localization.md` | Plugin author localization guide |

## How the docs maintainer merges these

```bash
# In the docs.churchcrm.io checkout:
rsync -av /path/to/CRM/docs-staging/wiki/ wiki/
# Inspect, edit front-matter to match site conventions, open PR.
```

After the docs PR lands, open a cleanup PR against this repo that
removes the staged files and updates `src/plugins/README.md` with
direct links to the freshly-published pages.
