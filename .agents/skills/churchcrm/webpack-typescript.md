# Skill: Webpack & TypeScript Development

## Context
ChurchCRM uses Webpack to bundle frontend JavaScript/TypeScript and CSS. This skill covers entry points, API utilities, type safety, and best practices for building modern webpack modules.

**Verified versions in this repo (package.json):**
- `react` 19.2.4
- `react-dom` 19.2.4
- `typescript` 5.9.3
- `webpack` 5.105.2
- `webpack-cli` 6.0.1
- `ts-loader` 9.5.4

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

### React Component with TypeScript

```typescript
// webpack/admin-dashboard-app.tsx
import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { fetchAPIJSON } from './api-utils';

interface User {
    id: number;
    name: string;
    email: string;
}

const AdminDashboard: React.FC = () => {
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const loadUsers = async () => {
            try {
                const data = await fetchAPIJSON<User[]>('users');
                setUsers(data);
            } catch (error) {
                console.error('Failed to load users:', error);
            } finally {
                setLoading(false);
            }
        };
        
        loadUsers();
    }, []);

    if (loading) return <div>Loading...</div>;
    
    return (
        <div>
            <h1>Dashboard</h1>
            <ul>
                {users.map(user => (
                    <li key={user.id}>{user.name} ({user.email})</li>
                ))}
            </ul>
        </div>
    );
};

// Mount app
const container = document.getElementById('admin-dashboard-app');
if (container) {
    const root = createRoot(container);
    root.render(<AdminDashboard />);
}
```

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
    'skin/v2/my-component-app': './webpack/my-component-app.tsx',
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

## Related Knowledge
- **API Utilities**: See webpack/api-utils.ts source
- **Bootstrap Build**: See `npm run build:frontend` documentation
- **Admin API Calls**: See admin-api-development.md skill
