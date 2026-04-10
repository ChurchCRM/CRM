# Skill: Webpack & TypeScript Development

## Context
ChurchCRM uses Webpack to bundle frontend JavaScript/TypeScript and CSS. This skill covers entry points, API utilities, type safety, and best practices for building modern webpack modules.

**Verified versions in this repo (package.json):**
- `typescript` 5.9.3
- `webpack` 5.105.4
- `webpack-cli` 7.0.2
- `ts-loader` 9.5.4

> **Note:** React was removed in 7.2.0. All interactive UI uses vanilla JS + Bootstrap 5.

---

## Critical Issue: Window.CRM Initialization Timing

**Webpack bundles load BEFORE `window.CRM` is initialized.**

- ❌ **DON'T** assign `window.CRM.root` in constructors or module scope
- ✅ **DO** use `api-utils.ts` functions which evaluate at runtime

```typescript
// ❌ WRONG - window.CRM is undefined when module loads
const API_ROOT = window.CRM.root + '/api';  // undefined + string!

// ✅ CORRECT - Evaluates at runtime when CRM is ready
import { buildAPIUrl } from './api-utils';
const url = buildAPIUrl('person/123');  // Safe, evaluates later
```

## Webpack API Utilities (`webpack/api-utils.ts`)

### Safe API URL Construction

```typescript
import { buildAPIUrl, buildAdminAPIUrl, fetchAPIJSON } from './api-utils';

// Public API endpoints
const personUrl = buildAPIUrl('person/123');           // → '/api/person/123'
const photoUrl = buildAPIUrl('person/123/photo');     // → '/api/person/123/photo'

// Admin API endpoints
const configUrl = buildAdminAPIUrl('system/config/key');  // → '/admin/api/system/config/key'

// Dynamic root path (works with subdirectories)
// If installed at /churchcrm/, buildAPIUrl('person/123') → '/churchcrm/api/person/123'
```

### Fetch with JSON Parsing

```typescript
interface Person {
    id: number;
    firstName: string;
    lastName: string;
}

// ✅ Recommended - Type-safe JSON fetch
const person = await fetchAPIJSON<Person>('person/123');
console.log(person.firstName);  // IDE autocomplete works!

// ✅ With error handling
try {
    const data = await fetchAPIJSON<Person>('person/123');
    console.log('Success:', data);
} catch (error) {
    console.error('API error:', error);
}

// ✅ With fetch options
const response = await fetchAPI('person/123/photo', {
    method: 'DELETE',
    headers: { 'X-Custom': 'value' }
});
```

### Available Functions in api-utils.ts

| Function | Purpose | Returns |
|----------|---------|---------|
| `getRootPath()` | Get `window.CRM.root` dynamically | `string` (e.g., `/churchcrm`) |
| `buildAPIUrl(path)` | Build `/api/` endpoint URL | `string` |
| `buildAdminAPIUrl(path)` | Build `/admin/api/` endpoint URL | `string` |
| `fetchAPI(path, options)` | Generic fetch wrapper | `Promise<Response>` |
| `fetchAPIJSON<T>(path, options)` | Fetch and parse JSON | `Promise<T>` |
| `fetchAdminAPI(path, options)` | Admin API fetch variant | `Promise<Response>` |
| `fetchAdminAPIJSON<T>(path, options)` | Admin API JSON variant | `Promise<T>` |

## Skin Bundle Architecture (LTR + RTL) <!-- learned: 2026-03-28 -->

The main CSS/JS bundles are split into three files:

| File | Purpose |
|------|---------|
| `webpack/skin-core-css.js` | **CSS only** — all shared component CSS (icons, DataTables, TomSelect, etc.) except Tabler core |
| `webpack/skin-core.js` | **JS only** — jQuery, ApexCharts, Tabler JS, TomSelect bridge, Quill, etc. Imports `skin-core-css` |
| `webpack/skin-main.js` | LTR entry — imports `tabler.min.css` then `skin-core` |
| `webpack/skin-rtl.js` | RTL entry — imports `tabler.rtl.min.css` then `skin-core-css` (**no JS**) |

This structure ensures `churchcrm-rtl.min.js` is webpack-runtime-only (~2 KB) and not a full duplicate of `churchcrm.min.js`. RTL pages load `churchcrm.min.js` for JS and `churchcrm-rtl.min.css` for styles.

**When adding a shared CSS dependency** → add to `skin-core-css.js`.
**When adding a shared JS dependency** → add to `skin-core.js`.
**Never add JS imports to `skin-rtl.js`** — it is intentionally CSS-only.

---

## Entry Point Patterns

### Basic JavaScript Entry Point

```javascript
// webpack/photo-uploader.js
document.addEventListener('DOMContentLoaded', function() {
    const uploadButton = document.getElementById('upload-photo');
    
    if (!uploadButton) return;  // Element doesn't exist yet
    
    uploadButton.addEventListener('click', async function() {
        try {
            const result = await fetch('/api/photo', { method: 'POST' });
            console.log('Upload complete');
        } catch (error) {
            console.error('Upload failed:', error);
        }
    });
});
```

### TypeScript with Type Safety

```typescript
// webpack/avatar-loader.ts
import { buildAPIUrl, fetchAPIJSON } from './api-utils';

interface AvatarInfo {
    id: number;
    url: string;
    exists: boolean;
}

class AvatarLoader {
    async load(personId: number): Promise<void> {
        try {
            const path = `person/${personId}/avatar`;
            const avatar = await fetchAPIJSON<AvatarInfo>(path);
            
            if (avatar.exists) {
                this.displayAvatar(avatar);
            }
        } catch (error) {
            console.error('Failed to load avatar:', error);
        }
    }
    
    private displayAvatar(avatar: AvatarInfo): void {
        const img = document.querySelector('img.avatar') as HTMLImageElement;
        if (img) {
            img.src = avatar.url;
        }
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    const loader = new AvatarLoader();
    const personId = parseInt((document.getElementById('person-id') as HTMLElement)?.dataset.id || '0');
    if (personId > 0) {
        loader.load(personId);
    }
});
```

### avatar-loader.ts Click Class Injection <!-- learned: 2026-03-30 -->

`avatar-loader.ts` is the single source of truth for making avatars clickable.
In `loadUploadedPhoto.onload`, it adds `.view-person-photo` / `.view-family-photo`
plus `data-person-id` / `data-family-id` — but only after confirming the photo
loaded successfully. It skips images inside `#uploadImageButton` / `#uploadImageTrigger`
(profile upload buttons). **Do not add these classes in PHP templates** — avatar-loader
handles it. See `frontend-development.md` → "Avatar Click-to-View Lightbox" for full rules.

## CSS Organization

Each feature should have associated CSS:

```javascript
// webpack/my-feature.js
import './my-feature.css';  // Import at top
import './my-feature.scss'; // SCSS also supported

// TypeScript same pattern
import './my-feature.css';  // In webpack/my-feature.ts
```

**Output Configuration (webpack.config.js):**
```javascript
entry: {
    'skin/v2/my-feature': './webpack/my-feature.js',  // → src/skin/v2/my-feature.js
    'skin/v2/my-component-app': './webpack/my-component-app.ts',
}
```

## Type Definitions & Reuse

### Shared Types File

```typescript
// webpack/types/api-models.ts
export interface Person {
    id: number;
    firstName: string;
    lastName: string;
    familyId: number;
}

export interface Family {
    id: number;
    name: string;
    address: string;
}

export interface ApiResponse<T> {
    success: boolean;
    data?: T;
    message?: string;
    errors?: Record<string, string>;
}
```

### Using Shared Types

```typescript
// webpack/person-viewer.ts
import type { Person, ApiResponse } from './types/api-models';
import { fetchAPIJSON } from './api-utils';

async function viewPerson(id: number): Promise<void> {
    const response = await fetchAPIJSON<ApiResponse<Person>>(`person/${id}`);
    if (response.success && response.data) {
        console.log(`${response.data.firstName} ${response.data.lastName}`);
    }
}
```

## Best Practices

### 1. Async Loading
Always use async/await for API calls:

```typescript
// ✅ CORRECT
const data = await fetchAPIJSON('person/123');

// ❌ WRONG - No synchronous API calls
const data = fetch('/api/person/123');  // Returns Promise immediately
```

### 2. Error Handling
Always wrap async operations:

```typescript
// ✅ CORRECT
try {
    const data = await fetchAPIJSON('person/123');
} catch (error) {
    console.error('Failed:', error);
    // Show error to user
}

// ❌ WRONG - Unhandled promise rejection
const data = await fetchAPIJSON('person/123');  // Crash if fails
```

### 3. No Global State Assignment
Avoid modifying `window.CRM`:

```typescript
// ✅ CORRECT - Use api-utils
import { buildAPIUrl } from './api-utils';
const url = buildAPIUrl('person/123');

// ❌ WRONG - Assumes window.CRM exists
const url = window.CRM.root + '/api/person/123';
```

### 4. DOM Ready Check
Always verify elements exist:

```typescript
// ✅ CORRECT
const button = document.getElementById('my-button');
if (button) {
    button.addEventListener('click', handler);
}

// ❌ WRONG - Crashes if element doesn't exist
document.getElementById('my-button').addEventListener('click', handler);
```

### 5. Tree Shaking for Performance
Use ES6 imports for better bundling:

```typescript
// ✅ CORRECT - Enables tree shaking
import { buildAPIUrl } from './api-utils';

// ❌ LESS EFFICIENT - Full module imported
import * as utils from './api-utils';
```

### 6. Lazy Loading Heavy Libraries
```typescript
// ✅ Only load when needed
async function openModal() {
    const { Modal } = await import('bootstrap');
    new Modal(element).show();
}
```

### 7. i18n for User-Facing Text
```typescript
// ✅ Always use i18next.t()
window.CRM.notify(i18next.t('Settings saved'), { type: 'success' });

// ❌ WRONG - Untranslatable
window.CRM.notify('Settings saved', { type: 'success' });
```

### 8. Code Splitting
Separate concerns into different entry points:

```javascript
// webpack.config.js
entry: {
    'skin/v2/admin': './webpack/admin-dashboard.js',     // Admin pages
    'skin/v2/photo-uploader': './webpack/photo-uploader.js',  // Photo upload
    'skin/v2/kiosk': './webpack/kiosk/registration.tsx', // Kiosk app
}
```

---

## Biome Lint — Suppression Comments <!-- learned: 2026-03-03 -->

This project uses **Biome** (not ESLint) for TypeScript/JS linting. ESLint suppression comments are silently ignored by Biome.

### ✅ CORRECT — Biome suppression syntax
```js
// biome-ignore lint/suspicious/noExplicitAny: <reason>
const root = (window as any).CRM?.root;
```

### Common rules to suppress

| Rule | When |
|------|------|
| `lint/suspicious/noExplicitAny` | Legitimate `any` in interop/legacy code |
| `lint/style/noNonNullAssertion` | When null is structurally impossible |
| `lint/complexity/useOptionalChain` | When optional chaining changes semantics |

---

## JS vs TypeScript: When to Use Each <!-- learned: 2026-03-15 -->

Use **TypeScript** (`.ts`) when:
- Making API calls (use `api-utils.ts` typed helpers)
- Working with custom typed interfaces or response shapes

Use **plain JavaScript** (`.js`) when:
- The entry is primarily jQuery plugin initialization (DataTables, Select2, etc.)
- The file is a thin DOM-ready event-handler wrapper with no API calls
- The module renders HTML via template literals (no type benefit)

**Why:** `datatables.net` augments the jQuery `JQuery<T>` interface via side-effect import. This requires either:
- `import 'datatables.net'` (would bundle DataTables, wasting ~200 KB since it's loaded globally)
- Adding `"datatables.net"` to tsconfig `types` array (untested — may conflict)

Existing JS-only entries: `admin-dashboard.js`, `backup.js`, `restore.js`, `church-info.js`.

```javascript
// webpack/groups-sundayschool-dashboard.js — jQuery-heavy, plain JS
document.addEventListener("DOMContentLoaded", () => {
  $(".data-table").DataTable(window.CRM.plugin.dataTable);
  // ...
});
```

## Related Knowledge
- **API Utilities**: See webpack/api-utils.ts source
- **Bootstrap Build**: See `npm run build:frontend` documentation
- **Admin API Calls**: See admin-api-development.md skill
