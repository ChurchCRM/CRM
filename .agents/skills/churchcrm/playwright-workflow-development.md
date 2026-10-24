---
title: "Playwright Workflow Development"
intent: "Create marketing screenshot and video workflows: one spec file, metadata in captureScreen() options"
---

# Playwright Workflow Development

A workflow is one spec: `playwright/workflows/<feature>.spec.ts`. The `captureScreen()` options carry what is captured (`name`, `purpose`) and how it is shown (`title`, `category`, optional `dark`). Each capture writes them to a sidecar, and `manifest.json` is generated from the sidecars. Pipeline, artifact layout and publishing: `marketing-visuals-pipeline.md`.

## Spec pattern

```typescript
import { expect, test } from '@playwright/test';
import { captureScreen } from '../support/capture';
import { humanPause } from '../support/human';

test.describe('<Feature Name>', () => {
  test('<workflow-name>', async ({ page }, testInfo) => {
    await page.goto('/feature/path');
    await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
    await humanPause(page, 500);

    await captureScreen(page, testInfo, {
      name: '<workflow-name>',
      title: 'Display Title',
      category: 'People & Families',
      purpose: 'Short marketing description of what this shows.'
    });
  });
});
```

| Requirement | Details |
|---|---|
| Test name | Equals `name` in `captureScreen()` (it throws otherwise). `kebab-case`, for example `dashboard-hero` |
| `title` and `category` | Required. `marketing:check` fails a capture whose sidecar lacks either |
| `purpose` | Marketing description, 40-80 chars, ends with a period |
| Viewports | One page load captures desktop (1440×900), tablet (1024×768), mobile (430×932) |
| Waits | `await expect(...).toBeVisible()` before capturing, then `humanPause(page, 500-1000)` so animations settle |
| Comments | Explain tricky setup (seed data specifics, why a particular flow) |

Helpers: `playwright/support/human.ts` (pause, click, type), `playwright/support/photo.ts` (seed photos), `playwright/support/capture.ts`. Examples: `playwright/workflows/dashboard.spec.ts` (simple), `playwright/workflows/people-family.spec.ts` (complex).

### Dark variants

Write a second test with the dark name that switches the theme, reloads, and captures:

```typescript
test('dashboard-hero-dark', async ({ page }, testInfo) => {
  await page.goto('/v2/dashboard');
  await page.evaluate(() => localStorage.setItem('theme', 'dark'));
  await page.reload();
  await expect(page.locator('h2')).toBeVisible({ timeout: 15000 });
  await humanPause(page, 800);
  await captureScreen(page, testInfo, {
    name: 'dashboard-hero-dark',
    title: 'Dashboard (Dark)',
    category: 'Dashboards',
    purpose: 'Landing dashboard after login in dark mode.'
  });
});
```

Add `dark: 'dashboard-hero-dark'` to the light capture's options. The dark capture omits `dark`.

## Title and category

- `title`: 20-40 characters, title case, the gallery heading (`"People Directory"`, `"Check-In & Attendance"`)
- `category`, one of:
  - `Dashboards`: module dashboards and landing views
  - `People & Families`: people, families, directory, photos
  - `Groups & Events`: groups, ministries, events, calendar
  - `Finance & Giving`: pledges, giving, deposits, funds
  - `Communication`: email, SMS, mailing lists
  - `Admin & Settings`: admin panel, users, permissions, plugins
  - `Recordings`: demo videos of workflows
  - `Setup`: installation and initial setup videos

A new category is used in `captureScreen()` and added to this list.

## Run and validate

```bash
npm run marketing              # all locales: screenshots, videos, manifest
npm run marketing:screenshots  # Playwright tests only
npm run marketing:check        # validates sidecars and artifacts
npm run marketing:manifest     # regenerates playwright/artifacts/manifest.json
```

## Commit

Commit the spec (`playwright/workflows/*.spec.ts`). `playwright/artifacts/` is gitignored; images, videos and the manifest are published after the release.

## Troubleshooting

- **No screenshot captured:** read the console output for timeouts or navigation errors; check URLs, selectors and seed data; raise the timeout for slow network waits
- **Screenshot missing from the manifest:** run `npm run marketing` (not only screenshots); confirm `title` and `category` are set and the test title equals the capture name
- **Wrong manifest metadata:** fix `title` / `category` spelling in the spec, then rerun `npm run marketing:manifest`
- **Website lacks the new screenshot:** confirm the release was published and `Publish visuals` in `ChurchCRM/visuals` succeeded; check https://cdn.churchcrm.io/manifest.json
