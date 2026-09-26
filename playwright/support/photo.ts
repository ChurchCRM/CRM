import fs from 'node:fs';
import type { Page } from '@playwright/test';

/**
 * Uploads a demo photo to a family or person via the same API the app's own
 * photo-uploader widget calls (POST /api/{family|person}/{id}/photo with a
 * base64 imgBase64 body) — used for records that only exist once the
 * pipeline is already running (a family/person created live by a workflow
 * test, or the admin account from the setup wizard) and so can't get a
 * photo through the src/admin/demo/people.json seed data like everyone
 * else. Reuses page.request, which shares the browser context's session
 * cookies, so no separate auth is needed.
 */
export async function uploadDemoPhoto(
  page: Page,
  entityType: 'family' | 'person',
  entityId: number,
  imagePath: string
): Promise<void> {
  const imgBase64 = fs.readFileSync(imagePath).toString('base64');
  const response = await page.request.post(`/api/${entityType}/${entityId}/photo`, {
    data: { imgBase64: `data:image/jpeg;base64,${imgBase64}` },
  });
  if (!response.ok()) {
    throw new Error(`Failed to upload ${entityType} photo for id ${entityId}: ${response.status()} ${await response.text()}`);
  }
}
