<?php

use ChurchCRM\Portal\Permissions\EffectiveGrant;
use ChurchCRM\Utils\InputUtils;

/*
 * The "Portal sign-in" card's devices, passkeys and portal permissions, for
 * administrators only (member sign-in design §11.3, §8.6): sign out a device,
 * remove a passkey or a permission an administrator gave, and give one.
 *
 * Variables: $portalAccess (PersonPortalAccessService::forPerson()),
 * $portalGrantChoices.
 * Behaviour: webpack/people/person-portal-access.ts.
 */

// One entry per source: "Everyone", "Adult of the Smith family", each given grant.
$permissionGroups = [];
foreach ($portalAccess['permissions'] as $permission) {
    $key = $permission['source'] === EffectiveGrant::SOURCE_GIVEN ? 'given:' . $permission['grantId'] : $permission['source'] . ':' . $permission['sourceLabel'];
    $permissionGroups[$key] ??= ['source' => $permission['source'], 'label' => $permission['sourceLabel'], 'grantId' => $permission['grantId'], 'reason' => $permission['reason'], 'items' => []];
    $permissionGroups[$key]['items'][] = $permission;
}
$sourceOrder = [EffectiveGrant::SOURCE_EVERYONE => 0, EffectiveGrant::SOURCE_RULE => 1, EffectiveGrant::SOURCE_GIVEN => 2];
uasort($permissionGroups, static fn (array $a, array $b): int => $sourceOrder[$a['source']] <=> $sourceOrder[$b['source']]);

$removeButton = static function (string $class, string $dataName, int $id, string $label): string {
    return '<button type="button" class="btn btn-sm btn-ghost-danger px-1 ' . $class . '" data-' . $dataName . '="' . $id . '"'
        . ' title="' . InputUtils::escapeAttribute($label) . '" aria-label="' . InputUtils::escapeAttribute($label) . '">'
        . '<i class="fa-solid fa-xmark"></i></button>';
};
?>
<div id="portal-signin-access">
    <?php if ($portalAccess['devices'] !== [] || $portalAccess['passkeys'] !== []) : ?>
        <div class="subheader mt-3 mb-1"><?= gettext('Devices and passkeys') ?></div>
        <ul class="list-unstyled mb-0" id="portal-signin-device-list">
            <?php foreach ($portalAccess['devices'] as $device) : ?>
                <li class="d-flex align-items-start gap-2 py-1 border-bottom" data-cy="portal-device" data-device-id="<?= (int) $device['id'] ?>">
                    <i class="fa-solid fa-laptop text-body-secondary mt-1"></i>
                    <div class="flex-fill text-break">
                        <div><?= InputUtils::escapeHTML($device['label']) ?></div>
                        <div class="small text-secondary">
                            <?= $device['lastUsedAt'] !== null
                                ? InputUtils::escapeHTML(sprintf(gettext('Last used %s'), $device['lastUsedAt']))
                                : InputUtils::escapeHTML(sprintf(gettext('Added %s'), (string) $device['createdAt'])) ?>
                        </div>
                    </div>
                        <?= $removeButton('portal-signin-remove-device', 'device-id', (int) $device['id'], gettext('Sign out this device')) ?>
                </li>
            <?php endforeach; ?>
            <?php foreach ($portalAccess['passkeys'] as $passkey) : ?>
                <li class="d-flex align-items-start gap-2 py-1 border-bottom" data-cy="portal-passkey" data-passkey-id="<?= (int) $passkey['id'] ?>">
                    <i class="fa-solid fa-key text-body-secondary mt-1"></i>
                    <div class="flex-fill text-break">
                        <div><?= InputUtils::escapeHTML($passkey['label']) ?></div>
                        <div class="small text-secondary">
                            <?= gettext('Passkey') ?>
                            &middot; <?= $passkey['lastUsedAt'] !== null
                                ? InputUtils::escapeHTML(sprintf(gettext('Last used %s'), $passkey['lastUsedAt']))
                                : InputUtils::escapeHTML(sprintf(gettext('Added %s'), (string) $passkey['createdAt'])) ?>
                        </div>
                    </div>
                        <?= $removeButton('portal-signin-remove-passkey', 'passkey-id', (int) $passkey['id'], gettext('Remove this passkey')) ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <button type="button" class="btn btn-link p-0 mt-3 text-decoration-none collapsed" id="portal-signin-permissions-toggle"
            data-bs-toggle="collapse" data-bs-target="#portal-signin-permissions-section"
            aria-expanded="false" aria-controls="portal-signin-permissions-section">
        <i class="fa-solid fa-chevron-down me-1" id="portal-signin-permissions-chevron"></i>
        <span id="portal-signin-permissions-label"><?= gettext('View Permissions') ?></span>
        <span class="text-secondary ms-1" id="portal-signin-permissions-count">(<?= count($portalAccess['permissions']) ?>)</span>
    </button>

    <div class="collapse" id="portal-signin-permissions-section">
        <div class="subheader mt-2 mb-1"><?= gettext('Portal permissions') ?></div>
        <ul class="list-unstyled mb-0" id="portal-signin-permissions">
            <?php foreach ($permissionGroups as $group) : ?>
                <li class="d-flex align-items-start gap-2 py-1 border-bottom" data-cy="portal-permission-source"
                    data-source="<?= InputUtils::escapeAttribute($group['source']) ?>"
                    <?= $group['grantId'] !== null ? 'data-grant-id="' . (int) $group['grantId'] . '"' : '' ?>>
                    <div class="flex-fill text-break">
                        <div class="small fw-medium"><?= InputUtils::escapeHTML($group['label']) ?></div>
                        <?php foreach ($group['items'] as $item) : ?>
                            <div class="small" data-capability="<?= InputUtils::escapeAttribute($item['capability']) ?>">
                                <?= InputUtils::escapeHTML($item['capabilityLabel']) ?>
                                <?php if ($item['scopeName'] !== null && $group['source'] !== EffectiveGrant::SOURCE_RULE) : ?>
                                    <span class="text-secondary">&middot; <?= InputUtils::escapeHTML($item['scopeName']) ?></span>
                                <?php endif; ?>
                                <?php if ($item['statusLabel'] !== null) : ?>
                                    <span class="badge bg-secondary-lt text-secondary ms-1" title="<?= InputUtils::escapeAttribute($item['statusLabel']) ?>"><?= gettext('Off') ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($group['reason'] !== null) : ?>
                            <div class="small text-secondary fst-italic"><?= InputUtils::escapeHTML($group['reason']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($group['grantId'] !== null) : ?>
                        <?= $removeButton('portal-signin-remove-grant', 'grant-id', (int) $group['grantId'], gettext('Remove this permission')) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            <?php if ($permissionGroups === []) : ?>
                <li class="small text-secondary"><?= gettext('None') ?></li>
            <?php endif; ?>
        </ul>

        <?php if ($portalAccess['notInEffect'] !== []) : ?>
            <div class="small text-secondary mt-2"><?= gettext('Given, but not in effect') ?></div>
            <ul class="list-unstyled mb-0" id="portal-signin-ended-grants">
                <?php foreach ($portalAccess['notInEffect'] as $grant) : ?>
                    <li class="d-flex align-items-start gap-2 py-1 border-bottom text-secondary" data-cy="portal-ended-grant" data-grant-id="<?= (int) $grant['grantId'] ?>">
                        <div class="flex-fill small text-break">
                            <?= InputUtils::escapeHTML($grant['capabilityLabel']) ?>
                            <?php if ($grant['scope']['name'] !== null) : ?>
                                &middot; <?= InputUtils::escapeHTML($grant['scope']['name']) ?>
                            <?php endif; ?>
                            <div>
                                <?= $grant['known']
                                    ? InputUtils::escapeHTML(sprintf(gettext('Ended %s'), (string) $grant['endsOnDisplay']))
                                    : gettext('Its plugin is not installed') ?>
                            </div>
                        </div>
                            <?= $removeButton('portal-signin-remove-grant', 'grant-id', (int) $grant['grantId'], gettext('Remove this permission')) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="portal-grant-add"
                data-bs-toggle="modal" data-bs-target="#portal-grant-modal">
            <i class="fa-solid fa-plus me-1"></i><?= gettext('Give a permission') ?>
        </button>
    </div>
</div>

<div class="modal fade" id="portal-grant-modal" tabindex="-1" aria-labelledby="portal-grant-title" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="portal-grant-form" novalidate autocomplete="off">
            <div class="modal-header">
                <h5 class="modal-title" id="portal-grant-title"><?= gettext('Give a portal permission') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= gettext('Close') ?>"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label required" for="portal-grant-capability"><?= gettext('Permission') ?></label>
                    <select class="form-select" id="portal-grant-capability" name="capability" required>
                        <option value=""><?= gettext('Choose a permission') ?></option>
                        <?php
                        $byOwner = [];
                        foreach ($portalGrantChoices as $choice) {
                            $byOwner[$choice['ownerName']][] = $choice;
                        }
                        foreach ($byOwner as $ownerName => $choices) : ?>
                            <optgroup label="<?= InputUtils::escapeAttribute($ownerName) ?>">
                                <?php foreach ($choices as $choice) : ?>
                                    <option value="<?= InputUtils::escapeAttribute($choice['key']) ?>"
                                            data-scope-type="<?= InputUtils::escapeAttribute($choice['scopeType']) ?>"
                                            data-scope-label="<?= InputUtils::escapeAttribute($choice['scopeFieldLabel']) ?>"
                                            data-description="<?= InputUtils::escapeAttribute($choice['description']) ?>"
                                            data-status="<?= InputUtils::escapeAttribute((string) $choice['statusLabel']) ?>">
                                        <?= InputUtils::escapeHTML($choice['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text" id="portal-grant-capability-hint"></div>
                </div>
                <div class="mb-3 d-none" id="portal-grant-scope-group">
                    <label class="form-label required" for="portal-grant-scope" id="portal-grant-scope-label"></label>
                    <select class="form-select" id="portal-grant-scope" name="scopeId"
                            data-placeholder="<?= InputUtils::escapeAttribute(gettext('Type a name')) ?>"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="portal-grant-ends"><?= gettext('Last day') ?></label>
                    <input type="date" class="form-control" id="portal-grant-ends" name="endsOn">
                    <div class="form-text"><?= gettext('Leave empty to keep it until someone removes it.') ?></div>
                </div>
                <div>
                    <label class="form-label required" for="portal-grant-reason"><?= gettext('Reason') ?></label>
                    <textarea class="form-control" id="portal-grant-reason" name="reason" rows="2" maxlength="255" required></textarea>
                    <div class="form-text"><?= gettext('Only administrators see the reason.') ?></div>
                </div>
                <div class="invalid-feedback d-block" id="portal-grant-error"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-link link-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                <button type="submit" class="btn btn-primary" id="portal-grant-save">
                    <i class="fa-solid fa-plus me-1"></i><?= gettext('Give permission') ?>
                </button>
            </div>
        </form>
    </div>
</div>
