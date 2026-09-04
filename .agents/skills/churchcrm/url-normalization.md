# Skill: URL Normalization & Canonicalization

## Context

This skill documents URL normalization patterns, canonicalization strategies, and redirect mechanisms used across ChurchCRM's web properties (churchcrm.io, docs.churchcrm.io). Use this when adding pages, managing multilingual content, or implementing SEO-critical URL structures.

## Core Principle

**One canonical URL per page.** Eliminate duplicates via:
- Canonical link tags pointing search engines to the true URL
- Hreflang tags for language variants (with x-default)
- Sitemap entries listing only canonical URLs
- Redirects for legacy paths (preserving search equity)

---

## Hugo Configuration

### URL Stripping (index.html)

All canonical URLs, hreflang tags, and sitemap entries must strip `index.html` suffixes. Hugo generates `/page/index.html` for trailing-slash URLs; templates remove the suffix for canonical consolidation.

**Canonical tag** (`layouts/partials/head.html`):
```go
{{- $canonicalURL := .Permalink -}}
{{- if hasSuffix $canonicalURL "index.html" -}}
    {{- $canonicalURL = strings.TrimSuffix "index.html" $canonicalURL -}}
{{- end -}}
<link rel="canonical" href="{{ $canonicalURL }}">
```

**Hreflang tags** (same file):
```go
{{ range .AllTranslations -}}
{{- $hrefLangURL := .Permalink -}}
{{- if hasSuffix $hrefLangURL "index.html" -}}
    {{- $hrefLangURL = strings.TrimSuffix "index.html" $hrefLangURL -}}
{{- end -}}
<link rel="alternate" hreflang="{{ .Language.LanguageCode }}" href="{{ $hrefLangURL }}" />
{{- end }}
<link rel="alternate" hreflang="x-default" href="{{ (index .Sites 0).BaseURL }}" />
```

**Sitemap** (`layouts/sitemap.xml`):
```go
{{- $loc := $page.Permalink | strings.TrimSuffix "index.html" }}
<loc>{{ $loc }}</loc>
```

---

## Multilingual Pages (Language Variants)

### Front Matter Configuration

For multilingual content, explicitly set the canonical URL in the page's front matter to ensure correct generation:

```yaml
---
title: "Page Title"
url: "/en/path/to/page/"  # For English (often at root: "/path/to/page/")
aliases:
  - "/path/to/page.html"   # Legacy .html path for redirect
---
```

**Pattern for non-English**:
```yaml
url: "/[lang]/path/to/page/"
aliases:
  - "/path/to/page.html"    # Old URL without language prefix
```

**All 8 languages currently using this pattern** (churchcrm.io):
- English (en): `/church-management-software/` (root)
- Spanish (es): `/es/church-management-software/`
- Portuguese (pt): `/pt/church-management-software/`
- Chinese (zh): `/zh/church-management-software/`
- French (fr): `/fr/church-management-software/`
- Russian (ru): `/ru/church-management-software/`
- German (de): `/de/church-management-software/`
- Arabic (ar): `/ar/church-management-software/`

---

## Redirect Mechanism (GitHub Pages + Hugo Aliases)

**Limitation**: GitHub Pages cannot serve HTTP 301/308 redirects from repository files (static hosting only).

**Solution**: Use Hugo aliases to generate static redirect pages:

1. Hugo creates a static HTML file at the old path (e.g., `/church-management-software.html`)
2. File contains `<meta http-equiv="refresh">` for browser redirect
3. File contains `<link rel="canonical">` for search engine consolidation
4. GitHub Pages serves as HTTP 200 + static HTML

**Search equity preservation**: The canonical tag in the alias page tells search engines to consolidate the old URL's ranking into the canonical form.

**Example alias page generated at `/es/church-management-software.html`**:
```html
<!DOCTYPE html>
<html>
<head>
  <link rel="canonical" href="https://churchcrm.io/es/church-management-software/">
  <meta http-equiv="refresh" content="0; url=https://churchcrm.io/es/church-management-software/">
</head>
<body></body>
</html>
```

---

## Validation Strategy

### Automated Script

The validation script `scripts/check-url-normalization.mjs` verifies:

1. ✅ No canonical href ends in `index.html`
2. ✅ No hreflang href ends in `index.html`
3. ✅ Sitemap contains no `index.html` entries
4. ✅ All redirect aliases point to canonical URLs
5. ✅ Redirect mapping CSV matches built site

**Run locally** (requires Hugo installed):
```bash
hugo --minify
node scripts/check-url-normalization.mjs
```

**CI execution**: Runs automatically after `hugo --minify` in CI pipeline.

**Expected output**:
```
OK:   XXX canonical hrefs checked, none end in index.html
OK:   XXX hreflang alternate hrefs checked, none end in index.html
OK:   XXX sitemap.xml <loc> entries checked, none end in index.html
OK:   7 implemented redirect-mapping.csv rows checked (old_url alias + new_url target)

3 check group(s) passed, 0 failure(s).
```

---

## Root URL Handling (/ vs /index.html)

### GitHub Pages Native Behavior

GitHub Pages serves `index.html` natively at BOTH `/` and `/index.html`. This is expected static-hosting behavior, not a site bug.

**Evidence**:
```
$ curl -sI https://churchcrm.io/ | head -5
HTTP/2 200
ETag: "abc123-def456"

$ curl -sI https://churchcrm.io/index.html | head -5
HTTP/2 200
ETag: "abc123-def456"  # Identical ETags = identical content
```

### Conservative Decision: No Redirect

**Why not redirect `/index.html` → `/`?**
1. Hugo aliases cannot override GitHub Pages' native `index.html` serving
2. Canonical consolidation already configured to point to `/`
3. Search engines recognize canonical tags and consolidate duplicates
4. No ranking impact detected; canonical is sufficient

**Follow-up**: Monitor Search Console post-deployment for consolidation (2-4 weeks). If separate indexing persists, server-level configuration (outside repository scope) may be needed.

---

## Redirect Mapping Documentation

Maintain a CSV file (`content/redirect-mapping.csv`) documenting all URL migrations:

| old_url | new_url | mechanism | status | reason |
|---------|---------|-----------|--------|--------|
| `https://churchcrm.io/es/church-management-software.html` | `https://churchcrm.io/es/church-management-software/` | Hugo alias (meta-refresh + canonical) | implemented | Normalized trailing-slash form |

**Columns**:
- `old_url`: Legacy URL (with `http(s)://domain`)
- `new_url`: Canonical URL
- `mechanism`: How the redirect works (Hugo alias, HTTP 301, meta-refresh, etc.)
- `status`: "implemented", "planned", or "audited but not changed"
- `reason`: Why the redirect exists (normalization, consolidation, legacy cleanup, etc.)

---

## When to Add URL Normalization

### Before Creating a Page

✅ **Check**:
1. Is this a new language variant? → Use `url: "/[lang]/path/"` + language-specific alias
2. Is this replacing an old page? → Document the old URL in `aliases:` for redirect
3. Does this need a trailing slash? → Use trailing-slash form in `url:` (canonical)

✅ **Configure**:
```yaml
url: "/canonical/path/"
aliases:
  - "/old/legacy/path.html"
  - "/another-old-variant/"
```

✅ **Verify**:
- Hugo build generates alias pages at old paths
- Canonical tag in new page points to `/canonical/path/`
- Sitemap lists only `/canonical/path/` (no `index.html`, no aliases)

### When Renaming Pages

1. Add old URL to `aliases:` in the page's front matter
2. Run `hugo --minify`
3. Verify alias page generated at old path
4. Update navigation links to point to new URL
5. Document in `content/redirect-mapping.csv`

### When Adding Language Variants

For a page already published in one language, adding a variant:

1. **Create content file**: `content/[lang]/page.md`
2. **Set canonical URL**: `url: "/[lang]/path/"`
3. **Add redirect alias** (if old URL exists): `aliases: ["/old/path/"]`
4. **Verify hreflang**: Hugo automatically generates hreflang tags linking language variants
5. **Test validation script**: Run `node scripts/check-url-normalization.mjs`

---

## Testing & Debugging

### Common Issues

**Issue**: Canonical tag includes `index.html`
- **Cause**: Template not stripping suffix
- **Fix**: Check `layouts/partials/head.html` has `strings.TrimSuffix "index.html"`

**Issue**: Alias page not generated
- **Cause**: Hugo build failed or aliases misconfigured
- **Fix**: Check front matter `aliases:` field exists and is valid YAML
- **Test**: Run `hugo --minify` and check `public/[old-path]/index.html` exists

**Issue**: Sitemap includes `index.html` entries
- **Cause**: Sitemap template not stripping suffix
- **Fix**: Verify `layouts/sitemap.xml` line 7 has `strings.TrimSuffix "index.html"`

**Issue**: Hreflang tags missing language variants
- **Cause**: Content not translated or language not configured
- **Fix**: Verify all language versions exist and `defaultContentLanguage` is set correctly in `hugo.toml`

### Manual Testing

```bash
# Build and validate
hugo --minify
node scripts/check-url-normalization.mjs

# Check specific file in built site
cat public/path/to/page/index.html | grep '<link rel="canonical"'

# Verify redirect alias
cat public/old/legacy/path/index.html | grep 'meta.*refresh'
```

---

## Reference Files

- **Validation script**: `scripts/check-url-normalization.mjs`
- **Redirect mapping**: `content/redirect-mapping.csv`
- **Hugo config**: `hugo.toml`
- **Canonical implementation**: `layouts/partials/head.html` (lines 27-31)
- **Hreflang implementation**: `layouts/partials/head.html` (lines 220-227)
- **Sitemap implementation**: `layouts/sitemap.xml` (line 7)
- **Audit findings**: `/marketing/research/seo-audits/URL_NORMALIZATION_AUDIT.md` (marketing repo)

---

## Related Skills

- `i18n-localization.md` — Multilingual content strategy
- `security-best-practices.md` — Canonical tag XSS considerations
- `seo-audits.md` — Full audit methodology

---

Last updated: 2026-09-04
