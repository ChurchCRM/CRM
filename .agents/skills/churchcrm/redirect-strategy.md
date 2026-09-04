# Skill: Redirect Strategy & Implementation

## Context

This skill documents when and how to implement redirects for URL migrations, consolidations, and legacy path handling. Different hosting platforms have different redirect mechanisms; this covers decision-making and implementation patterns.

<!-- learned: 2026-09-04 -->

---

## Redirect Decision Tree

**START**: You have a URL migration (old URL → new URL)

```
Is the old URL still indexed or trafficked?
├─ NO → No redirect needed, use canonical consolidation only
└─ YES → Continue...

Can you implement HTTP-level redirects?
├─ YES (dynamic server, Node.js, etc.)
│  └─ Use HTTP 301 (permanent) or 302 (temporary) redirect
│     ├─ HTTP 301: "This URL moved permanently" (preserves ranking, can be cached by browsers)
│     └─ HTTP 302: "This URL moved temporarily" (re-indexes old URL, loses ranking)
│     └─ BEST: HTTP 301 for permanent migrations
│
└─ NO (static hosting: GitHub Pages, Netlify, S3)
   ├─ Can you use meta-refresh + canonical? → YES (GitHub Pages, Netlify)
   │  └─ Generate static HTML at old path with:
   │     ├─ <meta http-equiv="refresh" content="0; url=NEW_URL">
   │     └─ <link rel="canonical" href="NEW_URL">
   │     └─ HTTP 200 (not 301) because GitHub Pages serves only static files
   │
   └─ Can you use redirect rules/config file? → YES (Netlify, Vercel)
      └─ Use _redirects or netlify.toml or vercel.json
         ├─ These are server-side (platform handles redirect)
         └─ Closest to HTTP 301 without server code
```

---

## Redirect Types & Mechanisms

### HTTP-Level Redirects (Best)

**Best for**: Dynamic servers (Node.js, Django, PHP), platforms with middleware support

**HTTP Status Codes**:
- **301 (Moved Permanently)**: "This URL will never come back." Browsers/engines cache. Recommended for migrations.
- **302 (Found)**: "This URL moved temporarily." Engines re-index old URL. Only use if reverting.
- **307/308**: HTTP/1.1+ variants (similar semantics to 301/302, preserve request method)

**Search engine behavior**:
- **301**: Consolidates ranking signals. Old URL disappears from index within weeks.
- **302**: Keeps old URL indexed. Both URLs compete for rankings.

**Implementation patterns**:

**Next.js** (vercel.json or next.config.js):
```javascript
// next.config.js
const redirects = async () => [
  {
    source: '/old/path.html',
    destination: '/new/path/',
    permanent: true,  // 301 redirect
  },
  {
    source: '/es/old-page.html',
    destination: '/es/new-page/',
    permanent: true,
  },
];
```

**Express.js / Node.js**:
```javascript
app.get('/old/path.html', (req, res) => {
  res.redirect(301, '/new/path/');  // 301 Moved Permanently
});
```

**Django**:
```python
# urls.py
from django.views.generic.base import RedirectView

urlpatterns = [
    path('old/path.html', RedirectView.as_view(url='/new/path/', permanent=True)),
]
```

---

### Meta-Refresh + Canonical (Static Hosting)

**Best for**: GitHub Pages, static site hosting, Hugo/Jekyll with aliases

**Mechanism**:
1. Generate a static HTML file at the old path
2. File contains `<meta http-equiv="refresh">` for browser redirect (instant, user-facing)
3. File contains `<link rel="canonical">` for search engine consolidation
4. Server returns **HTTP 200** (file exists and is delivered)

**Limitations**:
- HTTP 200 (not 301), so search engines may take longer to consolidate
- Browser redirect happens client-side after page load
- Not all crawlers honor meta-refresh

**Implementation (Hugo)**:
```yaml
# content/page.md
---
title: "Page Title"
url: "/new/path/"
aliases:
  - "/old/path.html"
---
```

**Result**: Hugo generates:
- `/new/path/index.html` — actual content page with canonical pointing to `/new/path/`
- `/old/path.html` OR `/old/path/index.html` — alias page with:
  ```html
  <link rel="canonical" href="https://domain.com/new/path/">
  <meta http-equiv="refresh" content="0; url=https://domain.com/new/path/">
  ```

**Advantages**:
- Works on static hosting (no server-side code)
- User sees redirect (meta-refresh handles it)
- Search engines see canonical tag + redirect

**Disadvantages**:
- Not a true HTTP redirect (engines may not treat it as permanence signal)
- Slower consolidation (2-4 weeks typical, vs. immediate with 301)
- Some crawlers don't follow meta-refresh

---

### Platform-Specific Redirects (Netlify, Vercel, etc.)

**Best for**: Platforms with built-in redirect support (closest to HTTP redirects without server code)

**Netlify (_redirects file)**:
```
/old/path.html              /new/path/          301
/es/old-page.html           /es/new-page/       301
/blog/:year/:month/:slug    /blog/:slug         301
```

**Vercel (vercel.json)**:
```json
{
  "redirects": [
    {
      "source": "/old/path.html",
      "destination": "/new/path/",
      "permanent": true
    }
  ]
}
```

**Advantages**:
- Closest to true HTTP redirects (platform handles it server-side)
- Works like 301 redirects for search engines
- No static file overhead

---

## Redirect Mapping & Documentation

**Always document redirects** in a CSV file or configuration file.

**Format**:
```
old_url,new_url,status_code,reason,date_implemented
https://domain.com/old/path.html,https://domain.com/new/path/,301,URL normalization,2026-01-15
https://domain.com/page.php,https://domain.com/page/,301,Legacy PHP migration,2026-02-01
```

**Columns**:
- `old_url`: Full URL including domain and scheme
- `new_url`: Canonical destination
- `status_code`: 301 (permanent), 302 (temporary), or 200 (meta-refresh)
- `reason`: Why the redirect exists
- `date_implemented`: When it went live

**Example from ChurchCRM** (content/redirect-mapping.csv):
```
https://churchcrm.io/es/church-management-software.html,https://churchcrm.io/es/church-management-software/,200,Hugo alias redirect (meta-refresh + canonical),2026-09-04
```

---

## Validation & Monitoring

**Before deployment**:
1. **Test redirect chain**:
   ```bash
   curl -I https://domain.com/old/path.html
   # Should show:
   # HTTP/1.1 301 Moved Permanently
   # Location: https://domain.com/new/path/
   
   # NOT a chain: old → temporary → new
   ```

2. **Verify final destination**:
   ```bash
   curl -L https://domain.com/old/path.html | head -5
   # Should show new page content (after following redirect)
   ```

3. **Check canonical in new page**:
   ```bash
   curl https://domain.com/new/path/ | grep canonical
   # Should show: <link rel="canonical" href="https://domain.com/new/path/">
   ```

**After deployment**:
1. **Monitor Search Console**:
   - Old URL should show as redirected (if 301)
   - Ranking signals should consolidate to new URL
   - Typical consolidation time: 1-4 weeks

2. **Check for redirect loops**:
   - Search Console will report errors if old URL redirects to itself
   - URL Inspection tool will show redirect chain

3. **Verify no 404s on old paths**:
   - If redirect is broken, old path returns 404
   - Search Console will report these as crawl errors

---

## GitHub Pages Constraint (ChurchCRM Context)

**GitHub Pages cannot serve HTTP 301/308 redirects from repository files.**

Why? GitHub Pages is **static hosting**:
- Only delivers files as-is (HTTP 200)
- No dynamic code to generate HTTP headers
- Cannot distinguish between "redirect" and "regular file"

**Workaround**: Use meta-refresh + canonical (HTTP 200)
- Hugo generates static HTML at old path
- Page contains `<meta http-equiv="refresh">` + `<link rel="canonical">`
- Browser redirects user, search engines see canonical

**Limitation**: Consolidation is slower than HTTP 301 (typically 2-4 weeks)

**Escalation**: If strict/immediate URL consolidation is needed, GitHub Pages is a constraint. Escalate to:
- Server-side redirects (requires moving off GitHub Pages)
- Proxy layer (Cloudflare Workers, etc.) to inject HTTP redirects
- Configuration outside the repository scope

---

## Decision Examples

### Example 1: Church Website URL Normalization

**Scenario**: Migrating from `.html` URLs to trailing-slash URLs

**Old URLs**: `/church-management-software.html` (8 language variants)
**New URLs**: `/church-management-software/` (8 language variants)
**Platform**: GitHub Pages (static hosting)
**Status**: Some old URLs already indexed

**Decision**:
1. ❌ Cannot use HTTP 301 (static hosting limitation)
2. ✅ Use Hugo aliases + canonical + meta-refresh
3. ✅ Document in redirect-mapping.csv
4. ✅ Monitor Search Console for consolidation (2-4 weeks)
5. ⏳ If separation persists, escalate for server-level config

**Result**: HTTP 200 + canonical consolidation strategy selected

---

### Example 2: Blog URL Restructure

**Scenario**: Changing blog URLs from `/blog/YYYY-MM-DD-slug.html` to `/blog/slug/`

**Old URLs**: 100+ blog posts with date-based slugs
**New URLs**: Shorter, date-less paths
**Platform**: Netlify (platform redirects support)
**Status**: High traffic to old URLs (SEO, email links)

**Decision**:
1. ✅ Can use Netlify _redirects (HTTP 301 support)
2. ✅ Generate bulk redirects with regex: `/blog/:year/:month/:day/:slug.html /blog/:slug/ 301`
3. ✅ Document in Netlify config + external CSV
4. ✅ Monitor for redirect loops, broken chains
5. ✅ Expect consolidation within 2 weeks (HTTP 301)

**Result**: HTTP 301 permanent redirects selected

---

## Related Skills

- `url-normalization.md` — Hugo-specific implementation
- `canonical-consolidation.md` — Canonical tag strategy
- `seo-audit-methodology.md` — Audit redirects
- `multilingual-content.md` — Multilingual redirect patterns

---

**Last updated**: 2026-09-04 <!-- learned: 2026-09-04 -->
