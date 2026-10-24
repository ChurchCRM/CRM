<?php
/*
 * Admin → Member Portal → Permissions (member sign-in design §8.6).
 * The catalog is read from GET /admin/api/member-portal/permissions when the tab
 * opens; webpack/admin-member-portal/permissions-tab.ts draws it.
 */
?>
<div class="tab-pane fade" id="portal-permissions" role="tabpanel">
    <p class="text-secondary" id="portalPermissionsLead">
        <?= gettext('What members can see and do in the portal, and where each permission comes from. People are counted among everyone the sign-in rules let in, and staff.') ?>
        <?= gettext('An administrator can give any permission to one person on that person\'s page. "Admin overrides" then shows here, with the number of people who were given it.') ?>
    </p>
    <div id="portalMemberFeaturesPanel" class="mb-3"></div>
    <div class="card">
        <div class="table-clip-x">
            <table class="table table-vcenter card-table" id="portalPermissionsTable">
                <thead>
                    <tr>
                        <th><?= gettext('Permission') ?></th>
                        <th class="d-none d-md-table-cell"><?= gettext('Applies to') ?></th>
                        <th class="d-none d-lg-table-cell"><?= gettext('Given to') ?></th>
                        <th><?= gettext('Status') ?></th>
                        <th class="text-end"><?= gettext('People') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5" class="text-secondary"><?= gettext('Loading…') ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal fade" id="portalHoldersModal" tabindex="-1" aria-labelledby="portalHoldersTitle" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-fullscreen-lg-down modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="portalHoldersTitle"></h5>
                        <div class="text-secondary small" id="portalHoldersDescription"></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-vcenter w-100" id="portalHoldersTable"></table>
                </div>
            </div>
        </div>
    </div>
</div>
