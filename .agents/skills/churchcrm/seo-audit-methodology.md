# Skill: SEO Audit Methodology

## Context

This skill documents systematic approaches to conducting SEO audits on web properties, using URL normalization and canonicalization audits as the reference pattern. Use this when investigating site structure, identifying duplicate content, validating configuration, or planning technical SEO improvements.

---

## Audit Framework: 4-Phase Approach

### Phase 1: Inventory & Discovery

**Objective**: Map all URL patterns, identify duplicates, understand routing strategy.

**Steps**:
1. List all URL patterns currently served:
   - Homepage variants (`/`, `/index.html`, `/index.php`)
   - Language/locale variants (`/en/`, `/es/`, etc.)
   - Content paths (pages, blog posts, archives)
   - Taxonomy URLs (tags, categories)
   - Legacy formats (`.html`, `.php`, trailing slash vs. non-trailing)

2. Document the generation mechanism:
   - Server framework (Hugo, Next.js, Django, etc.)
   - URL generation rules (`uglyURLs: true`, route definitions, trailing slashes)
   - Redirect/alias mechanisms (server-side, client-side, static files)
   - Multi-domain vs. subdomain strategy

3. Identify URL variations:
   - What URLs serve identical content?
   - What's intentional (language variants) vs. accidental (index.html duplication)?
   - Are there legacy paths that should redirect to new canonical forms?

**Deliverable**: URL inventory table with columns:
- URL pattern
- Content served
- Canonical form (if multiple variants)
- Generation mechanism
- Status (working, broken, indexed)

**Example**:
```
| URL Pattern | Content | Canonical | Mechanism | Status |
|---|---|---|---|---|
| `/page/` | Page content | `/page/` | Hugo trailing-slash | Indexed |
| `/page/index.html` | Page content | `/page/` | GitHub Pages native | Indexed |
| `/page.html` | Page content | `/page/` | Hugo alias redirect | Indexed, consolidated |
```

---

### Phase 2: Configuration Analysis

**Objective**: Verify that templates, sitemap, and metadata are configured to consolidate duplicates.

**Steps**:

1. **Canonical tag implementation**:
   - Audit all templates that output `<link rel="canonical">`
   - Verify canonical URL matches the "true" form (e.g., no `index.html`, consistent trailing slash)
   - Check that all pages emit a canonical tag (not just some sections)
   - Test against built site: `grep -r 'rel="canonical"' public/ | head -20`

2. **Hreflang for multilingual**:
   - If site has multiple languages, verify hreflang tags are present
   - Check that each language variant links to itself and siblings
   - Verify `x-default` points to the primary language/region
   - Validate hreflang URLs match canonical forms (no `index.html`)

3. **Sitemap correctness**:
   - Verify sitemap lists only canonical URLs (no duplicates, no `index.html`)
   - Check that all indexable pages are included
   - Verify lastmod timestamps are accurate
   - For multilingual, confirm hreflang `<xhtml:link>` elements in sitemap entries

4. **robots.txt & meta robots**:
   - Verify `robots.txt` allows crawling of canonical URLs
   - Check that duplicate/legacy URLs are either redirected or have `noindex` meta tag
   - Confirm taxonomy/archive pages use appropriate directives (`index` for main, `noindex` for paginated duplicates)

**Deliverable**: Configuration audit checklist with pass/fail for each check.

**Template check example** (canonical tags):
```bash
# Build site (Hugo, Next.js, etc.)
npm run build

# Extract all canonical hrefs
grep -oh 'href="[^"]*"' public/**/*.html | grep canonical | sort | uniq -c

# Check for index.html in canonical URLs (should be 0)
grep -r 'href=".*index.html"' public/ | grep canonical | wc -l
```

---

### Phase 3: Mechanism Validation

**Objective**: Verify that redirects/aliases actually work and preserve search equity.

**Steps**:

1. **Test redirect chains**:
   - For each old URL → new URL mapping, verify the redirect works
   - Check HTTP status code (301/308 for server-side redirects, 200 for meta-refresh with canonical)
   - Verify no redirect chains (A→B→C is bad; should be A→C, B→C)

2. **Validate alias pages** (for static hosting like GitHub Pages):
   - Old URL should resolve to a page containing:
     - `<meta http-equiv="refresh" content="0; url=CANONICAL_URL">`
     - `<link rel="canonical" href="CANONICAL_URL">`
   - Both should point to the same new URL

3. **Search engine consolidation**:
   - Verify canonical tags are recognized by testing in Search Console's URL Inspection tool
   - Check that old URLs have consolidated to new URLs (if already indexed)
   - Monitor for 404s on old paths (should not occur if aliases work)

**Deliverable**: Redirect validation report.

**Test script pattern** (Node.js):
```javascript
// For each old_url in redirect mapping:
// 1. Fetch old_url, check HTTP status
// 2. Parse HTML, look for canonical or meta-refresh
// 3. Verify canonical points to new_url
// 4. Confirm new_url exists in built site
const redirects = [
  { old: '/old/path.html', new: '/new/path/' },
  // ...
];
for (const r of redirects) {
  const html = fs.readFileSync(`public${r.old.replace(/\.html$/, '')}/index.html`, 'utf8');
  if (!html.includes(r.new)) throw new Error(`${r.old} doesn't point to ${r.new}`);
}
```

---

### Phase 4: Evidence-Based Decisions

**Objective**: Determine what actually needs to be fixed, with justification.

**Decision Framework**:

1. **Evidence of ranking harm**:
   - Search Console: Are there duplicate URLs indexed separately with split impressions/clicks?
   - PageSpeed Insights: Are there warnings about duplicate content?
   - Crawl reports: Are crawlers hitting the duplicates?
   - If NO evidence of harm, consolidation via canonical may be sufficient (no redirect needed)

2. **User impact**:
   - Do users encounter the duplicate URLs? (broken links, outdated bookmarks)
   - Would a redirect improve UX? (e.g., old marketing links pointing to new paths)
   - Can the duplicate be eliminated entirely? (remove from sitemap, robots.txt noindex)

3. **Technical constraints**:
   - Can you implement HTTP redirects? (requires server-side config, not available on static hosting)
   - Can you use aliases/meta-refresh? (works on static hosting, loses some redirect juice)
   - Can you rely on canonical consolidation? (sufficient if search engines recognize canonical tags)

**Decision tree**:
```
Do we have evidence of ranking harm from this duplicate?
├─ YES → Fix it (redirect or noindex)
│  ├─ Can we do HTTP 301/308? → Best option
│  ├─ Can we use meta-refresh + canonical? → Good option
│  └─ Can we use noindex tag? → Last resort (if redirect is impossible)
└─ NO → Leave as-is, monitor Search Console
   └─ If harm emerges later (2-4 weeks), escalate to redirect
```

**Example decision**: *"/ vs /index.html duplication detected, but canonical consolidation is configured and no Search Console evidence of harm. Decision: monitor for 2-4 weeks, escalate if continued separate indexing occurs."*

---

## Audit Outputs & Recommendations

### Audit Report Structure

1. **Executive summary**: Key findings + conservative decisions
2. **URL inventory table**: All patterns, canonical forms, status
3. **Configuration audit**: Templates, sitemap, hreflang verified
4. **Redirect validation**: Test results for old→new mappings
5. **Evidence & rationale**: Why each decision was made
6. **Limitations**: What can't be fixed at repo level (e.g., GitHub Pages native behavior)
7. **Next steps**: Post-deployment monitoring, escalation criteria

### Conservative Approach Principles

1. **Do NOT assume duplicates are bad**: Evidence of ranking harm or user confusion required
2. **Do NOT make blanket changes**: Each URL pattern deserves individual assessment
3. **Do NOT rely on search engine optimism**: Monitor Search Console for actual consolidation
4. **Do NOT over-engineer**: Canonical + sitemap often sufficient; escalate only if evidence warrants

---

## Related Skills

- `url-normalization.md` — Specific patterns for Hugo + GitHub Pages
- `canonical-consolidation.md` — Implementing canonicalization strategies
- `multilingual-content.md` — Hreflang and language variant patterns
- `redirect-strategy.md` — Choosing redirect mechanisms

---

## Example Audit Workflow

**Scenario**: Migrating a site from PHP (`.php` URLs) to Hugo (trailing-slash URLs)

**Phase 1 - Inventory**:
- Identify all `.php` paths still being served (legacy aliases)
- Document new Hugo paths (`/page/` instead of `/page.php`)
- List language variants (`/es/page/` vs. `/page.php?lang=es`)

**Phase 2 - Configuration**:
- Verify Hugo templates strip `index.html` from canonicals
- Check that old `.php` paths have `aliases:` in front matter
- Validate sitemap lists only `/page/`, not `/page.php` or `/page/index.html`

**Phase 3 - Validation**:
- Run validation script to confirm alias pages point to new URLs
- Test that old `.php` paths resolve to meta-refresh pages
- Verify new paths have canonical tags

**Phase 4 - Decision**:
- If Search Console shows old `.php` URLs indexed: canonical tags will consolidate
- If no evidence of harm: monitor for 2-4 weeks
- If harm emerges: consider server-level HTTP redirects (outside repo scope)

---

**Last updated**: 2026-09-04 <!-- learned: 2026-09-04 -->
