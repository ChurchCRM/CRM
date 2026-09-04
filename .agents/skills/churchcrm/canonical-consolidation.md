# Skill: Canonical Consolidation Strategy

## Context

This skill documents how to implement canonical URL consolidation—using `<link rel="canonical">` tags to tell search engines which URL is the "true" version when multiple URLs serve identical content. Use this when dealing with URL duplicates, preventing duplicate content penalties, or consolidating traffic to canonical forms.

<!-- learned: 2026-09-04 -->

---

## Core Principle

**Canonical tags consolidate search equity** without requiring redirects. Search engines recognize the canonical tag and:
1. Index only the canonical URL (not the duplicate)
2. Credit the canonical URL with all ranking signals from duplicates
3. Prevent duplicate content penalties

**When to use canonical instead of redirects**:
- URL duplicates are **intentional** (index.html on static hosting, language variants)
- You **cannot** implement HTTP redirects (static hosting like GitHub Pages)
- You need to preserve **both URLs** for technical reasons
- Search engines **already index** the canonical URL and can consolidate via tag recognition

---

## Implementation: 4-Step Pattern

### Step 1: Identify the Canonical URL

**Definition**: The "true" or "primary" URL for a page.

**Characteristics**:
- Matches your site's **URL strategy** (trailing slash preference, language structure)
- Has **no unnecessary parameters** (no `?ref=old` or `?source=migration`)
- Is **user-facing** (what you put in navigation, sitemap, marketing)
- Is **indexable** (not blocked by robots.txt or noindex)

**Examples**:
```
Page: "Church Management Software"
├─ Canonical: /church-management-software/      ← trailing slash, clean
├─ Duplicate: /church-management-software.html   ← .html suffix (legacy)
├─ Duplicate: /church-management-software/index.html ← explicit index.html
└─ Variant: /es/church-management-software/     ← language variant (own canonical)
```

**For multilingual sites**:
- Each language has its **own canonical**
- `x-default` canonical points to English (or site default)
- Do NOT make all language variants point to English canonical (breaks hreflang!)

---

### Step 2: Output Canonical in Template

**All pages must emit a canonical tag** (no exceptions).

**Template pattern** (works for Hugo, Next.js, any framework):

```go
{{- $canonicalURL := .Permalink -}}
{{- if hasSuffix $canonicalURL "index.html" -}}
    {{- $canonicalURL = strings.TrimSuffix "index.html" $canonicalURL -}}
{{- end -}}
<link rel="canonical" href="{{ $canonicalURL }}">
```

**What this does**:
1. Take the page's **full URL** (`.Permalink`, `req.url`, etc.)
2. **Strip `index.html` suffix** if present (GitHub Pages native URLs)
3. **Output canonical tag** pointing to the stripped URL

**Why strip `index.html`**?
- `/page/` and `/page/index.html` are **identical content**
- Canonical should point to `/page/` (cleaner, no suffix)
- Search engines will consolidate both to `/page/`

**Placement in HTML**:
```html
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width">
  <!-- ... other meta tags ... -->
  <link rel="canonical" href="https://example.com/page/">  ← After meta tags
  <!-- ... rest of head ... -->
</head>
```

**Required attributes**:
- `rel="canonical"` — Tells engines this is the canonical link
- `href="https://example.com/path/"` — **Absolute URL** (domain included, no relative paths)
- Placed in `<head>` section (not `<body>`)

---

### Step 3: Duplicate URL Configuration (Aliases)

**For intentional duplicates**, declare them in front matter so the framework generates pages at old paths.

**Hugo pattern** (front matter):

```yaml
---
title: "Page Title"
url: "/canonical/path/"          # Canonical form
aliases:
  - "/old/legacy/path.html"       # Old URL (page will be generated here)
  - "/another-old-variant/"       # Can have multiple aliases
---
```

**Result**:
- Page built at `/canonical/path/index.html` with canonical tag pointing to `/canonical/path/`
- Alias page built at `/old/legacy/path.html` OR `/old/legacy/path/index.html`
  - Contains same canonical tag (pointing to `/canonical/path/`)
  - Contains `<meta http-equiv="refresh" content="0; url=/canonical/path/">` for browser redirect

**Next.js pattern** (in `next.config.js` or API routes):

```javascript
const redirects = async () => {
  return [
    {
      source: '/old/legacy/:path*',
      destination: '/canonical/:path*',
      permanent: false,  // false = 302 (temporary redirect)
    },
  ];
};
```

**With canonical in every page**:
```jsx
// pages/any-page.jsx
export default function Page() {
  return (
    <Head>
      <link rel="canonical" href="https://example.com/canonical/path/" />
    </Head>
  );
}
```

---

### Step 4: Validation & Monitoring

**Before deployment**:
1. **Build and audit**: Generate the site, check that canonical tags exist on all pages
   ```bash
   npm run build
   grep -r 'rel="canonical"' public/ | wc -l  # Should be > 0
   grep -r 'canonical.*index.html' public/    # Should be 0 (no index.html suffixes)
   ```

2. **Verify aliases point to canonical**:
   ```bash
   # Old URL should contain canonical tag pointing to new URL
   cat public/old/legacy/path/index.html | grep -o 'href="[^"]*"' | head -1
   # Output: href="https://example.com/canonical/path/"
   ```

**After deployment**:
1. **Search Console URL Inspection**: Test both old and new URLs
   - Old URL should show canonical pointing to new URL
   - New URL should show as canonical

2. **Monitor consolidation**:
   - Week 1-2: Old URL indexed separately (still indexing both)
   - Week 2-4: Impressions/clicks consolidate to new URL
   - Week 4+: Old URL disappears from search results (consolidated)

3. **Check for indexing issues**:
   - If old URL continues to appear as separate in search results after 4 weeks, escalate to HTTP redirects or noindex

---

## Common Pitfalls & Fixes

| Pitfall | Symptom | Fix |
|---------|---------|-----|
| **Canonical tag missing** | Pages have no canonical | Add to all templates, verify in built site |
| **Canonical with `index.html`** | `/page/index.html` in canonical tag | Use `TrimSuffix` to strip `index.html` |
| **Relative canonical URL** | `href="/page/"` instead of `href="https://..."` | Always use absolute URLs with domain |
| **Multiple canonicals** | Page outputs 2+ canonical tags | Ensure only one canonical tag per page |
| **Canonical points to wrong page** | Canonical points to duplicate, not canonical | Debug: is alias front matter correct? Is template logic correct? |
| **Canonical for duplicate language** | Both `/en/page/` and `/es/page/` point to `/en/page/` | Each language variant must have its own canonical; use hreflang for variants |
| **Canonical ignored by search engines** | Old URL still appears in search results | Canonicals require 2-4 weeks to consolidate; check Search Console for errors |

---

## Escalation: When Canonical Alone Is Not Enough

**When to add HTTP redirects** (in addition to or instead of canonical):

1. **Marketing links still point to old URLs**
   - Old links in social media, email, external sites traffic to old URL
   - Users see URL change in browser (confusing without redirect)
   - Redirect reduces bounce rate

2. **Old URL indexed with different content**
   - Old URL has been ranking for different keywords
   - Canonical tag won't merge the ranking signals (separate entities)
   - HTTP redirect consolidates ranking signals + equity

3. **Canonical not recognized after 4+ weeks**
   - Search Console shows old URL still indexed as separate
   - Canonical tag present but not working
   - Implement HTTP 301/302 redirect to force consolidation

4. **Static hosting (GitHub Pages) limitation**
   - GitHub Pages serves `index.html` natively at both `/page/` and `/page/index.html`
   - Canonical consolidates one direction, but both URLs remain live
   - If strict URL consolidation needed, server-side redirects required (outside repo scope)

---

## Canonical + Hreflang for Multilingual

**Critical**: When using canonical with language variants, **coordinate with hreflang**.

**Correct pattern**:
```html
<!-- English page -->
<link rel="canonical" href="https://example.com/en/page/">
<link rel="alternate" hreflang="en" href="https://example.com/en/page/">
<link rel="alternate" hreflang="es" href="https://example.com/es/page/">
<link rel="alternate" hreflang="x-default" href="https://example.com/en/page/">

<!-- Spanish page -->
<link rel="canonical" href="https://example.com/es/page/">
<link rel="alternate" hreflang="en" href="https://example.com/en/page/">
<link rel="alternate" hreflang="es" href="https://example.com/es/page/">
<link rel="alternate" hreflang="x-default" href="https://example.com/en/page/">
```

**Each language variant**:
- Has its **own canonical** (pointing to itself)
- Lists **itself in hreflang** (`hreflang="es"` on Spanish page)
- Lists **siblings in hreflang** (Spanish page links to English)
- **x-default** points to default language (usually English)

---

## Reference Implementation (ChurchCRM)

See `.agents/skills/churchcrm/url-normalization.md` for the live implementation pattern used across churchcrm.io:
- 8 language variants of `church-management-software.md` each with own canonical
- Hugo template stripping `index.html` from canonicals
- Aliases for legacy `.html` paths redirecting via canonical consolidation
- Validation script confirming no `index.html` in any canonical tag

---

## Related Skills

- `url-normalization.md` — Hugo-specific patterns
- `seo-audit-methodology.md` — How to audit canonicals
- `redirect-strategy.md` — When to use redirects vs. canonicals
- `multilingual-content.md` — Hreflang coordination

---

**Last updated**: 2026-09-04 <!-- learned: 2026-09-04 -->
