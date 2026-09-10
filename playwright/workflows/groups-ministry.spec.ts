import { test } from '@playwright/test';

import { captureScreen } from '../support/capture';
import { gotoFirstActiveGroup } from '../support/groups';
import { humanPause } from '../support/human';

test.describe('Groups & Ministry', () => {
  test('groups-ministry-overview', async ({ page }, testInfo) => {
    // A church user browsing ministry organization: open the groups
    // dashboard, then drill into an active group to see its membership.
    await gotoFirstActiveGroup(page);
    await humanPause(page, 500);

    await captureScreen(page, testInfo, {
      name: 'groups-ministry-overview',
      purpose: 'Show ministry group organization and membership',
    });
  });
});
