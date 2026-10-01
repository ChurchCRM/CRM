# Playwright Workflow Development

## Overview

Guide for agents (and developers) creating new marketing screenshot and video workflows.

When you add a new Playwright workflow test, you create two things:
1. **The spec file** (`playwright/workflows/*.spec.ts`) — what gets captured
2. **The metadata entry** (`playwright/screenshot-metadata.json`) — how it displays

The pipeline automatically generates `manifest.json` from these two sources.

## Step 1: Write the Workflow Spec

**File:** `playwright/workflows/<feature>.spec.ts`

### Pattern

```typescript
import { expect, test } from '@playwright/test';
import { captureScreen } from '../support/capture';
import { humanPause, humanClick, humanType } from '../support/human';

test.describe('<Feature Name>', () => {
  test('<workflow-name>', async ({ page }, testInfo) => {
    // Navigate to the feature
    await page.goto('/feature/path');
    
    // Wait for content to load and be visible
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    
    // Pause for visual settle (animations, content load)
    await humanPause(page, 500);
    
    // Capture the screenshot
    // name MUST match test name
    await captureScreen(page, testInfo, {
      name: '<workflow-name>',
      purpose: 'Short marketing description of what this shows'
    });
  });
});
```

### Requirements

| Requirement | Details |
|---|---|
| **Test name** | Must match the `name` in `captureScreen()`. Format: `kebab-case`. Example: `dashboard-hero` |
| **Purpose string** | Marketing description (40-80 chars). Ends with period. Examples: `"The landing dashboard after login — hero shot."` |
| **Viewport** | Playwright auto-captures at 3 viewports: desktop (1440×900), tablet (1024×768), mobile (430×932) |
| **Waits** | Always wait for content visibility before capturing. Use `await expect(...).toBeVisible()` |
| **Pause before capture** | Use `humanPause(page, 500-1000)` to let animations settle |
| **Comments** | Include comments explaining any tricky setup (seed data specifics, why a particular flow, etc.) |

### Dark Mode Variants

If you're creating a dark-mode variant:

```typescript
test('dashboard-hero-dark', async ({ page }, testInfo) => {
  // Same setup as light version
  await page.goto('/v2/dashboard');
  
  // Switch to dark mode (assuming there's a theme toggle)
  await page.evaluate(() => {
    localStorage.setItem('theme', 'dark');
  });
  await page.reload();
  
  await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
  await humanPause(page, 800);
  
  await captureScreen(page, testInfo, {
    name: 'dashboard-hero-dark',
    purpose: 'Landing dashboard after login — hero shot in dark mode'
  });
});
```

Then in metadata, reference the dark variant:
```json
{
  "dashboard-hero": {
    "title": "Dashboard",
    "category": "Dashboards",
    "dark": "dashboard-hero-dark"
  }
}
```

## Step 2: Add Metadata Entry

**File:** `playwright/screenshot-metadata.json`

### Schema

```json
{
  "<workflow-name>": {
    "title": "Display Title",
    "category": "Category Name",
    "dark": null  // or "workflow-name-dark"
  }
}
```

### Title Guidelines

- **Length:** 20-40 characters
- **Style:** Title case, concise
- **Purpose:** What the user sees in the gallery
- **Examples:**
  - ✅ `"Dashboard"` (simple, clear)
  - ✅ `"People Directory"` (two-word is fine)
  - ✅ `"Check-In & Attendance"` (feature name)
  - ❌ `"Show the dashboard"` (too verbose, redundant)
  - ❌ `"dash"` (too short, unclear)

### Categories

**Must be one of:**
- `"Dashboards"` — module dashboards and landing views
- `"People & Families"` — people, families, directory, photos
- `"Groups & Events"` — groups, ministries, events, calendar
- `"Finance & Giving"` — pledges, giving, deposits, funds
- `"Communication"` — email, SMS, mailing lists
- `"Admin & Settings"` — admin panel, users, permissions, plugins
- `"Recordings"` — demo videos of workflows
- `"Setup"` — installation and initial setup videos

**Adding a new category:** Edit this file and `.agents/skills/churchcrm/playwright-workflow-development.md` to update the enum.

### Dark Variant

- **null** (default) if no dark-mode variant exists
- **string** (workflow name) if dark variant is captured
- Links light and dark shots for the website toggle

### Example

```json
{
  "dashboard-hero": {
    "title": "Dashboard",
    "category": "Dashboards",
    "dark": "dashboard-hero-dark"
  },
  "dashboard-hero-dark": {
    "title": "Dashboard (Dark Mode)",
    "category": "Dashboards",
    "dark": null
  },
  "people-directory-list": {
    "title": "People Directory",
    "category": "People & Families",
    "dark": null
  },
  "admin-plugin-management": {
    "title": "Plugin Management",
    "category": "Admin & Settings",
    "dark": null
  }
}
```

## Step 3: Run the Pipeline

**Command:** `npm run marketing`

**What happens:**
1. Playwright runs all tests in `playwright/workflows/`
2. Each test captures screenshots at 3 viewports (desktop, tablet, mobile)
3. Scripts finalize any recorded videos
4. **`npm run marketing:manifest` auto-generates `manifest.json`**
   - Reads metadata JSON sidecars from capture
   - Merges with `screenshot-metadata.json`
   - Outputs single `artifacts/manifest.json` with all metadata

**Output:**
```
playwright/artifacts/
├── manifest.json (auto-generated, committed)
├── screenshots/
│   ├── desktop/workflow-name.png
│   ├── tablet/workflow-name.png
│   └── mobile/workflow-name.png
├── videos/
│   ├── desktop/workflow-name.webm
│   ├── tablet/workflow-name.webm
│   └── mobile/workflow-name.webm
└── metadata/ (debug only, not committed)
    └── device/*.json
```

## Step 4: Commit

**Files to commit:**
```bash
git add playwright/artifacts/manifest.json
git add playwright/screenshot-metadata.json
git add playwright/artifacts/screenshots/
git add playwright/artifacts/videos/
git commit -m "chore: add workflow-name screenshot and video"
```

**Manifest.json is committed** so:
- It's preserved in git history
- It's part of the PR review
- The website can sync a known-good version
- Diffs show what changed (file sizes, timestamps, new entries)

## Step 5: Publish to Website

**From CRM root:**
```bash
npm run publish:visuals
```

This automatically copies:
- `manifest.json` → website `data/manifest.json`
- `screenshot-metadata.json` → website `data/screenshot-metadata.json`
- Screenshots and videos to website `static/images/`

## Validation

Before pushing, verify:

### Spec Validation
```bash
npm run marketing:screenshots  # Run tests only
npm run marketing:check         # Validate all exist
```

### Metadata Validation
```bash
# Check schema conformance
npm run playwright:validate-metadata  # (if available)

# Or manually verify:
# - All test names match metadata keys
# - All titles are 20-40 chars
# - All categories are in enum
# - Dark references exist if specified
```

### Manifest Validation
```bash
# Verify manifest.json structure
npm run marketing:manifest && cat playwright/artifacts/manifest.json | jq 'length'
# Should output: number of artifacts (devices × workflows)
```

## Troubleshooting

**Problem:** Test fails, no screenshot captured
- ✅ Check console output for timeouts or navigation errors
- ✅ Verify URLs and selectors are correct
- ✅ Check seed data exists for what you're querying
- ✅ Increase timeout if waiting for network

**Problem:** Screenshot exists but not in manifest.json
- ✅ Did you run `npm run marketing` (not just screenshots)?
- ✅ Is there a metadata entry in `screenshot-metadata.json`?
- ✅ Check test name matches capture name exactly
- ✅ Check `.gitignore` isn't excluding the file

**Problem:** Manifest.json has wrong metadata
- ✅ Check `screenshot-metadata.json` has correct spelling
- ✅ Check category is in the enum
- ✅ Re-run `npm run marketing:manifest`

**Problem:** Website doesn't show new screenshot
- ✅ Did you run `npm run publish:visuals`?
- ✅ Did website PR sync `data/manifest.json`?
- ✅ Check Hugo build includes `data/` files

## When an Agent Develops a Workflow

1. **Agent reads this skill** before writing any spec
2. **Agent writes the spec** following the Pattern section
3. **Agent adds metadata** following the Schema section
4. **Agent validates** before pushing
5. **Agent commits** both spec and metadata

The pipeline does the rest automatically.

## Key Points

- ✅ **Test name and capture name must match exactly**
- ✅ **Purpose string is a marketing description, not technical**
- ✅ **Metadata is simple: just title + category**
- ✅ **Manifest is auto-generated, never hand-edited**
- ✅ **All 3 viewports capture automatically**
- ✅ **Dark variants are optional**

## Resources

- **Schema:** `playwright/screenshot-metadata.schema.json`
- **Example specs:** `playwright/workflows/dashboard.spec.ts` (simple), `playwright/workflows/people-family.spec.ts` (complex)
- **Capture function:** `playwright/support/capture.ts`
- **Human helpers:** `playwright/support/human.ts` (pause, click, type, etc.)
- **Photo upload:** `playwright/support/photo.ts` (for seeding photos)

## See Also

- `END_TO_END_SCREENSHOT_WORKFLOW.md` — Complete pipeline from spec to live site
- `MANIFEST_GENERATION_QUICK_REFERENCE.md` — Who generates what and when
