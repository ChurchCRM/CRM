---
title: "Webpack & TypeScript Development"
intent: "Entry points, API utilities, type safety, and best practices for building modern webpack modules"
tags: ["frontend", "webpack", "javascript"]
prereqs: []
complexity: "intermediate"
---
# Skill: Webpack & TypeScript Development

## Context
Webpack bundles the frontend JavaScript/TypeScript and CSS. Interactive UI is vanilla JS/TS on Tabler 1.6 + Bootstrap 5 (see `tabler-components.md`). Custom `rem` values assume a 16px root. Versions: `package.json`.

---

## Window.CRM Initialization Timing

Webpack bundles load before `window.CRM` is initialized. Read `window.CRM` at run time (inside `window.CRM.onLocalesReady(...)` or an event handler), and build URLs with `api-utils.ts`.

```typescript
import { buildAPIUrl } from './api-utils';
const url = buildAPIUrl('person/123');  // evaluated when called
```

## API Utilities (`webpack/api-utils.ts`)

```typescript
import { buildAPIUrl, buildAdminAPIUrl, fetchAPI, fetchAPIJSON } from './api-utils';

buildAPIUrl('person/123');                 // '<root>/api/person/123'
buildAdminAPIUrl('system/config/key');     // '<root>/admin/api/system/config/key'

interface Person { id: number; firstName: string; lastName: string; }

try {
    const person = await fetchAPIJSON<Person>('person/123');
} catch (error) {
    // show the error to the user
}

await fetchAPI('person/123/photo', { method: 'DELETE' });
```

The root path comes from `window.CRM.root`, so URLs work under a subdirectory install. `webpack/api-utils.ts` is short and JSDoc'd; read it for the full export list. `fetchAPIJSON` throws on a non-OK response and returns the parsed body (no `{ success, data }` envelope), so wrap it in `try`/`catch`.

## Skin Bundles (LTR + RTL)

| File | Purpose |
|------|---------|
| `webpack/skin-core-css.js` | Shared component CSS (icons, DataTables, TomSelect, ...) except Tabler core |
| `webpack/skin-core.js` | Shared JS (jQuery, ApexCharts, Tabler JS, TomSelect bridge, Quill, ...); imports `skin-core-css` |
| `webpack/skin-main.js` | LTR entry: `tabler.min.css` then `skin-core` |
| `webpack/skin-rtl.js` | RTL entry: `tabler.rtl.min.css` then `skin-core-css`, CSS only |

RTL pages load `churchcrm.min.js` for JS and `churchcrm-rtl.min.css` for styles. Add shared CSS dependencies to `skin-core-css.js`, shared JS dependencies to `skin-core.js`, and keep `skin-rtl.js` CSS-only.

Vendor libraries (DataTables, moment, date pickers, bootbox, i18next, JustValidate, bs-stepper) are imported by `webpack/vendor-globals.js` and ship in `churchcrm.min.js`; pages load no `<script>` tags for them. Leaflet comes from `webpack/leaflet-global.js` in the map entries. Locale files for moment, the datepicker and DataTables are dynamic `import()`s in `webpack/locale-loader.js`.

---

## Entry Points

### Plain JavaScript

```javascript
// webpack/photo-uploader.js
import { buildAPIUrl } from './api-utils';

document.addEventListener('DOMContentLoaded', () => {
    const uploadButton = document.getElementById('upload-photo');
    if (!uploadButton) return;

    uploadButton.addEventListener('click', async () => {
        try {
            await fetch(buildAPIUrl('photo'), { method: 'POST' });
        } catch (error) {
            console.error('Upload failed:', error);
        }
    });
});
```

### TypeScript

```typescript
// webpack/avatar-loader.ts
import { fetchAPIJSON } from './api-utils';

interface AvatarInfo { id: number; url: string; exists: boolean; }

async function load(personId: number): Promise<void> {
    try {
        const avatar = await fetchAPIJSON<AvatarInfo>(`person/${personId}/avatar`);
        const img = document.querySelector<HTMLImageElement>('img.avatar');
        if (avatar.exists && img) {
            img.src = avatar.url;
        }
    } catch (error) {
        console.error('Failed to load avatar:', error);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const personId = Number.parseInt(document.getElementById('person-id')?.dataset.id ?? '0', 10);
    if (personId > 0) {
        load(personId);
    }
});
```

### Avatar click classes

`avatar-loader.ts` makes avatars clickable: in `loadUploadedPhoto.onload` it adds `.view-person-photo` / `.view-family-photo` plus `data-person-id` / `data-family-id` after the photo loads, and skips images inside `#uploadImageButton` / `#uploadImageTrigger`. PHP templates leave those classes to it.

## CSS

Import the feature's CSS or SCSS at the top of its entry:

```javascript
import './my-feature.css';
import './my-feature.scss';
```

## Entry Registration (`webpack.config.js`)

Entry keys are flat. `output.path` is `src/skin/v2` and `output.filename` is `[name].min.js`, so the key is the bundle's basename (`'my-feature'` emits `src/skin/v2/my-feature.min.js`). The source may sit in a subdirectory and resolves with `resolve.extensions: ['.ts', '.tsx', '.js']`. Read the `entry` object in `webpack.config.js` for the current list.

```javascript
entry: {
    kiosk: './webpack/kiosk',                          // webpack/kiosk/index.ts
    'people-list': './webpack/people/person-list',
    'my-feature': './webpack/my-feature.js',
},
```

Load the bundle from a view with its emitted name:

```php
<script src="<?= SystemURLs::assetVersioned('/skin/v2/my-feature.min.js') ?>"></script>
```

Use one entry per page or feature. Entries are plain `.ts` / `.js` modules.

## Types

`webpack/types/` holds ambient declaration files (`window.d.ts` for the global `window.CRM` namespace, plus `datatables.d.ts`, `styles.d.ts`, `avatar-initials.d.ts`). Put exported interfaces in a `types.ts` next to the feature that owns them:

```typescript
// webpack/kiosk/types.ts
export interface PersonApiData { Id: number; FirstName: string; }

// webpack/kiosk/kiosk-jsom.ts
import type { PersonApiData } from './types';
import { fetchAPIJSON } from '../api-utils';

const person = await fetchAPIJSON<PersonApiData>(`person/${id}`);
```

## Best Practices

- Use `async`/`await` for API calls and wrap them in `try`/`catch` that shows the error to the user
- Build API URLs with `buildAPIUrl()` / `buildAdminAPIUrl()`
- Check that an element exists before attaching handlers: `document.getElementById('x')?.addEventListener('click', handler)`
- Use named ES imports (`import { buildAPIUrl } from './api-utils'`) so tree shaking works
- Load heavy libraries on demand with `await import('...')`
- Wrap user-facing text in `i18next.t()`: `window.CRM.notify(i18next.t('Settings saved'), { type: 'success' })`

## Biome Lint

Biome lints TypeScript/JS (`npm run lint`). Suppress with Biome syntax and a reason:

```js
// biome-ignore lint/suspicious/noExplicitAny: <reason>
const root = (window as any).CRM?.root;
```

Common suppressions: `lint/suspicious/noExplicitAny` (interop code), `lint/style/noNonNullAssertion` (null is structurally impossible), `lint/complexity/useOptionalChain` (optional chaining changes semantics).

## JS or TypeScript

Use TypeScript (`.ts`) for API calls (typed `api-utils.ts` helpers) and typed response shapes.

Use plain JavaScript (`.js`) for jQuery plugin initialization (DataTables, Select2), thin DOM-ready handler wrappers with no API calls, and modules that render HTML through template literals.

```javascript
// webpack/groups-sundayschool-dashboard.js
document.addEventListener("DOMContentLoaded", () => {
  $(".data-table").DataTable(window.CRM.plugin.dataTable);
});
```

## Related

- `webpack/api-utils.ts` source
- `api-development.md` for the API routes these helpers call
- `frontend-development.md`, `tabler-components.md`
