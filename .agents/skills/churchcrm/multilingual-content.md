# Skill: Multilingual Content & Hreflang Patterns

## Context

This skill documents strategies for managing content across multiple languages and locales. Focus areas include canonical URL structure for language variants, hreflang implementation for SEO, and language-specific routing patterns.

<!-- learned: 2026-09-04 -->

---

## Multilingual URL Structure Patterns

### Pattern 1: Language Prefix (Recommended for ChurchCRM)

**Structure**: `/[lang]/path/`

**Examples**:
- `/en/church-management-software/` (English at root, no prefix)
- `/es/church-management-software/` (Spanish)
- `/pt/church-management-software/` (Portuguese)
- `/zh/church-management-software/` (Chinese)

**Advantages**:
- Clear language separation in URL
- Easy to redirect language-specific traffic
- SEO-friendly (canonical per language)
- Works well with hreflang

**Disadvantages**:
- Longer URLs
- English often special-cased (at root vs. `/en/`)

**Hugo Configuration**:
```toml
# hugo.toml
[languages]
  [languages.en]
    contentDir = "content/en"
    languageCode = "en"
    title = "ChurchCRM"
    weight = 1
  [languages.es]
    contentDir = "content/es"
    languageCode = "es"
    title = "ChurchCRM"
    weight = 2
  # ... more languages ...

defaultContentLanguage = "en"
defaultContentLanguageInSubdir = false  # English at /, not /en/
disableDefaultLanguageRedirect = true   # Don't auto-redirect /en/ to /
```

**Content structure**:
```
content/
  en/
    church-management-software.md
    _index.md (homepage)
  es/
    church-management-software.md
    _index.md (Spanish homepage)
  pt/
    church-management-software.md
    _index.md
  # ... more languages ...
```

**Front matter (multilingual pages)**:
```yaml
---
title: "Page Title"
url: "/church-management-software/"     # English at root
---
```

```yaml
---
title: "Título en Español"
url: "/es/church-management-software/"  # Spanish with prefix
---
```

---

### Pattern 2: Domain-Based (Alternative)

**Structure**: `en.example.com`, `es.example.com`, etc.

**Advantages**:
- Each language can have independent server/CDN
- SEO treats them as separate properties
- Can have different content strategy per domain

**Disadvantages**:
- Multiple domains to manage (SSL, DNS, backups)
- Hreflang still required to signal relationships
- More complex infrastructure

**When to use**: Large-scale, language-specific teams or regions

---

### Pattern 3: Subdirectory (Least Recommended)

**Structure**: `example.com/en/`, `example.com/es/`

**Note**: This is problematic when English is at root (`/`) but other languages have prefixes. Better to use Pattern 1 consistently.

---

## Hreflang Implementation (Critical for SEO)

**What is hreflang?** A tag that tells search engines: *"This page has versions in other languages; here are the links."*

**Why is it important?**
- Prevents duplicate content penalties (each language is a variant, not duplicate)
- Directs users to their language version
- Consolidates ranking signals within language (not across)
- Signals x-default for unknown locales

**Template Implementation** (Hugo):

```go
<!-- Place in <head> section -->
{{ range .AllTranslations -}}
{{- $hrefLangURL := .Permalink -}}
{{- if hasSuffix $hrefLangURL "index.html" -}}
    {{- $hrefLangURL = strings.TrimSuffix "index.html" $hrefLangURL -}}
{{- end -}}
<link rel="alternate" hreflang="{{ .Language.LanguageCode }}" href="{{ $hrefLangURL }}" />
{{- end }}
<link rel="alternate" hreflang="x-default" href="{{ (index .Sites 0).BaseURL }}" />
```

**What each line does**:
1. Loop through `.AllTranslations` (all language versions of this page)
2. Get the page's permalink
3. Strip `index.html` if present (canonical consolidation)
4. Output hreflang tag with language code (e.g., `hreflang="es"`)
5. After all languages, output `x-default` pointing to English/default

**Example output (Spanish page)**:
```html
<link rel="alternate" hreflang="en" href="https://churchcrm.io/church-management-software/">
<link rel="alternate" hreflang="es" href="https://churchcrm.io/es/church-management-software/">
<link rel="alternate" hreflang="pt" href="https://churchcrm.io/pt/church-management-software/">
<link rel="alternate" hreflang="x-default" href="https://churchcrm.io/">
```

**Critical rules**:
1. ✅ Each language page lists **itself** in hreflang (self-reference)
2. ✅ English page lists `hreflang="en"` pointing to itself
3. ✅ Spanish page lists `hreflang="es"` pointing to itself
4. ✅ ALL language variants linked (don't leave any out)
5. ✅ x-default points to English/primary language
6. ❌ Do NOT make all languages point to English canonical (breaks hreflang!)
7. ❌ Do NOT skip x-default (helps search engines pick default for unknown locales)

---

## Canonical + Hreflang Coordination

**Critical**: Canonical and hreflang must work together, not conflict.

**Correct pattern**:

**English page (`/church-management-software/`)**:
```html
<link rel="canonical" href="https://churchcrm.io/church-management-software/">
<link rel="alternate" hreflang="en" href="https://churchcrm.io/church-management-software/">
<link rel="alternate" hreflang="es" href="https://churchcrm.io/es/church-management-software/">
<link rel="alternate" hreflang="pt" href="https://churchcrm.io/pt/church-management-software/">
<link rel="alternate" hreflang="x-default" href="https://churchcrm.io/">
```

**Spanish page (`/es/church-management-software/`)**:
```html
<link rel="canonical" href="https://churchcrm.io/es/church-management-software/">
<link rel="alternate" hreflang="en" href="https://churchcrm.io/church-management-software/">
<link rel="alternate" hreflang="es" href="https://churchcrm.io/es/church-management-software/">
<link rel="alternate" hreflang="pt" href="https://churchcrm.io/pt/church-management-software/">
<link rel="alternate" hreflang="x-default" href="https://churchcrm.io/">
```

**Key observations**:
- Each language's **canonical points to itself** (not to English)
- **Hreflang is identical** on all language pages (each lists all variants)
- **x-default always points to the default language** (English)

**❌ Common mistake** (all languages pointing to English canonical):
```html
<!-- WRONG on Spanish page -->
<link rel="canonical" href="https://churchcrm.io/church-management-software/">
<!-- This tells search engines Spanish page is duplicate of English, breaks language variants -->
```

---

## Language Detection & Routing

### Server-Side Language Detection (Recommended)

**Middleware detects browser language, redirects to appropriate version**:

```javascript
// Express.js middleware
app.use((req, res, next) => {
  const browserLang = req.acceptsLanguages(['en', 'es', 'pt', 'zh', 'fr', 'ru', 'de', 'ar']);
  if (browserLang && req.url === '/' && !req.cookies.language) {
    return res.redirect(`/${browserLang}/`);
  }
  next();
});
```

**Advantages**:
- User sees correct language automatically
- Respects browser language preferences
- No extra clicks needed

**Disadvantages**:
- Requires dynamic server (not available on GitHub Pages)
- Must store language preference (cookie) to avoid re-redirecting

---

### Client-Side Language Detection (Static Hosting)

**JavaScript detects browser language, redirects**:

```html
<script>
  // Check if language preference is set
  if (!localStorage.getItem('language')) {
    const browserLang = navigator.language.split('-')[0]; // 'es' from 'es-MX'
    const supported = ['en', 'es', 'pt', 'zh', 'fr', 'ru', 'de', 'ar'];
    if (supported.includes(browserLang) && browserLang !== 'en') {
      localStorage.setItem('language', browserLang);
      window.location.href = `/${browserLang}/`;
    }
  }
</script>
```

**Advantages**:
- Works on GitHub Pages (client-side only)
- Respects browser preferences

**Disadvantages**:
- Delay (JavaScript must run)
- Requires JavaScript enabled
- Doesn't work for crawlers/SEO

---

## Sitemap for Multilingual

**Sitemap must list all language variants with hreflang**.

**Format** (sitemap.xml):
```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:xhtml="http://www.w3.org/1999/xhtml">
  <url>
    <loc>https://churchcrm.io/church-management-software/</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://churchcrm.io/church-management-software/" />
    <xhtml:link rel="alternate" hreflang="es" href="https://churchcrm.io/es/church-management-software/" />
    <xhtml:link rel="alternate" hreflang="pt" href="https://churchcrm.io/pt/church-management-software/" />
    <xhtml:link rel="alternate" hreflang="x-default" href="https://churchcrm.io/" />
  </url>
  <!-- ... more URLs ... -->
</urlset>
```

**Hugo template pattern**:
```go
{{- $loc := $page.Permalink | strings.TrimSuffix "index.html" }}
<url>
  <loc>{{ $loc }}</loc>
  {{- if $page.IsTranslated }}
    {{- range $page.Translations }}
    <xhtml:link rel="alternate" hreflang="{{ .Language.LanguageCode }}"
                href="{{ .Permalink | strings.TrimSuffix "index.html" }}" />
    {{- end }}
    <xhtml:link rel="alternate" hreflang="{{ $page.Language.LanguageCode }}"
                href="{{ $loc }}" />
  {{- end }}
</url>
```

---

## Language Fallback Strategy

**What happens if a page doesn't exist in a language?**

**Option 1: Fallback to English**
```yaml
# content/es/missing-page.md doesn't exist
# Request to /es/missing-page/ falls back to English /missing-page/
```

**Option 2: 404 (Recommended for SEO)**
```yaml
# content/es/missing-page.md doesn't exist
# Request to /es/missing-page/ returns 404
# User can navigate to English version from 404 page
```

**Implementation** (Hugo):
```toml
[languages.es]
  disabled = false
  weight = 2
  # If set to true, untranslated pages fall back to default language
  # Better to NOT fall back (return 404) so users know content isn't translated
```

---

## Language Switcher (UX)

**Always provide a language switcher for user convenience**.

**Pattern**:
```html
<div class="language-switcher">
  <a href="/church-management-software/">English</a>
  <a href="/es/church-management-software/">Español</a>
  <a href="/pt/church-management-software/">Português</a>
  <a href="/zh/church-management-software/">中文</a>
  <!-- ... more languages ... -->
</div>
```

**Best practices**:
- Show current language as highlighted/bold
- Place in consistent location (header, footer)
- Use language names in the native language (Español not Spanish)
- Include flag emojis cautiously (politics/representation sensitive)

---

## Validation Checklist

- [ ] Each language has its own content directory (`content/en/`, `content/es/`, etc.)
- [ ] Canonical URL is set per language (each language points to itself)
- [ ] Hreflang tags list all language variants
- [ ] x-default hreflang points to primary language (usually English)
- [ ] Sitemap includes all language variants with xhtml:link hreflang
- [ ] No `index.html` in canonical or hreflang URLs (use TrimSuffix)
- [ ] Language switcher works on every page
- [ ] Browser language detection works (if implemented)
- [ ] Search Console shows correct hreflang relationships
- [ ] No missing translations cause broken links (404 or fallback?)

---

## Reference Implementation (ChurchCRM)

See `.agents/skills/churchcrm/url-normalization.md` for the live implementation:
- 8 languages: en, es, pt, zh, fr, ru, de, ar
- English at root (`/`), others with prefix (`/es/`, `/pt/`, etc.)
- Hreflang template in `layouts/partials/head.html`
- Sitemap with xhtml:link variants in `layouts/sitemap.xml`
- Language switcher in navigation

---

## Related Skills

- `url-normalization.md` — Hugo-specific patterns
- `canonical-consolidation.md` — Canonical strategy for languages
- `seo-audit-methodology.md` — Auditing language variants
- `redirect-strategy.md` — Redirecting between language versions

---

**Last updated**: 2026-09-04 <!-- learned: 2026-09-04 -->
