<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use ChurchCRM\Portal\ThemeManager;
use ChurchCRM\Portal\ThemeValidator;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

/*
 * Admin → Member Portal (#9864, design §4).
 *
 * Variables from the route: $themes, $activeTheme, $activeThemeExists, $stats,
 * $portalCalendars, $signIn, $memberLists, $csrfToken.
 *
 * Every theme was validated server-side by the route, so each row carries a
 * badge on first paint without a round trip. The page's behaviour lives in
 * webpack/admin-member-portal.ts.
 */

/** The badge for one validation summary: class, icon and label. */
$statusBadge = static function (string $status): array {
    return match ($status) {
        ThemeValidator::LEVEL_ERROR => ['bg-danger text-white', 'fa-circle-xmark', gettext('Errors')],
        ThemeValidator::LEVEL_WARNING => ['bg-warning text-dark', 'fa-triangle-exclamation', gettext('Warnings')],
        default => ['bg-success text-white', 'fa-circle-check', gettext('Valid')],
    };
};

/** A theme's label in the Themes tab: the system theme is named as such. */
$themeLabel = static function (array $theme): string {
    return $theme['isDefault'] ? gettext('System default') : $theme['name'];
};

/**
 * What kind of calendar a Calendars-tab row is. A row of the `calendars` table
 * with an owning ministry is a ministry calendar; without one it is church-wide.
 */
$calendarKind = static function (array $portalCalendar): string {
    if ($portalCalendar['type'] !== 'calendar') {
        return gettext('System calendar');
    }

    return $portalCalendar['ministryId'] === null
        ? gettext('Church calendar')
        : gettext('Ministry calendar');
};

$themesDocUrl = SystemURLs::attributed('https://docs.churchcrm.io/administration/member-portal-theme-guide', 'member_portal_themes');
?>

<div class="container-fluid" id="memberPortalPage">

    <?php if (!$activeThemeExists): ?>
        <div class="alert alert-danger" id="memberPortalMissingTheme">
            <h4 class="alert-title">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= gettext('The active theme folder is missing') ?>
            </h4>
            <p class="mb-0">
                <?= sprintf(
                    gettext('The Member Portal is set to the theme "%s", which is no longer on the server. Members are seeing an error page until you pick a theme that exists.'),
                    InputUtils::escapeHTML($activeTheme)
                ) ?>
                <a href="#portal-themes" class="ms-1"><?= gettext('Choose a theme') ?></a>
            </p>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs" role="tablist" id="memberPortalTabs">
        <li class="nav-item" role="presentation">
            <a class="nav-link active" id="portal-statistics-tab" data-bs-toggle="tab" href="#portal-statistics" role="tab">
                <i class="fa-solid fa-chart-simple me-2"></i><?= gettext('Statistics') ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="portal-signin-tab" data-bs-toggle="tab" href="#portal-signin" role="tab">
                <i class="fa-solid fa-right-to-bracket me-2"></i><?= gettext('Sign-in') ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="portal-members-tab" data-bs-toggle="tab" href="#portal-members" role="tab">
                <i class="fa-solid fa-users me-2"></i><?= gettext('Members') ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="portal-permissions-tab" data-bs-toggle="tab" href="#portal-permissions" role="tab">
                <i class="fa-solid fa-key me-2"></i><?= gettext('Permissions') ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="portal-audit-tab" data-bs-toggle="tab" href="#portal-audit" role="tab">
                <i class="fa-solid fa-list-check me-2"></i><?= gettext('Audit') ?>
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link" id="portal-themes-tab" data-bs-toggle="tab" href="#portal-themes" role="tab">
                <i class="fa-solid fa-palette me-2"></i><?= gettext('Themes') ?>
            </a>
        </li>
        <!-- Calendars (MP5, issue #9866): which calendars members see. -->
        <li class="nav-item" role="presentation" id="portal-calendars-tab-item">
            <a class="nav-link" id="portal-calendars-tab" data-bs-toggle="tab" href="#portal-calendars" role="tab">
                <i class="fa-solid fa-calendar-days me-2"></i><?= gettext('Calendars') ?>
            </a>
        </li>
    </ul>

    <div class="tab-content pt-3">

        <!-- ============================= Sign-in ============================ -->
        <div class="tab-pane fade" id="portal-signin" role="tabpanel">
            <?php
            $signInValues = $signIn['values'];
            $memberSignInOn = $signInValues['bMemberSignIn'] === '1';
            $tickedRoles = MemberSignInRule::idList($signInValues['sMemberSignInFamilyRoles']);
            $tickedClassifications = MemberSignInRule::idList($signInValues['sMemberSignInClassifications']);
            $switchRow = static function (string $key, string $label, bool $on, array $tooltips, bool $disabled = false): string {
                return '<label class="form-check form-switch mb-1">'
                    . '<input class="form-check-input" type="checkbox" id="' . $key . '" name="' . $key . '" value="1"'
                    . ($on ? ' checked' : '') . ($disabled ? ' disabled' : '') . '>'
                    . '<span class="form-check-label fw-medium">' . InputUtils::escapeHTML($label) . '</span>'
                    . '</label>'
                    . '<div class="form-text mb-2">' . InputUtils::escapeHTML($tooltips[$key]) . '</div>'
                    . '<div class="invalid-feedback d-block" data-error-for="' . $key . '"></div>';
            };
            $numberField = static function (string $key, string $label, string $value, int $min, int $max, array $tooltips): string {
                return '<div class="mb-3">'
                    . '<label class="form-label" for="' . $key . '">' . InputUtils::escapeHTML($label) . '</label>'
                    . '<input type="number" class="form-control w-auto" id="' . $key . '" name="' . $key . '"'
                    . ' min="' . $min . '" max="' . $max . '" step="1" required value="' . InputUtils::escapeAttribute($value) . '">'
                    . '<div class="form-text">' . InputUtils::escapeHTML($tooltips[$key]) . '</div>'
                    . '<div class="invalid-feedback d-block" data-error-for="' . $key . '"></div>'
                    . '</div>';
            };
            ?>
            <form id="memberSignInForm" novalidate autocomplete="off">
                <div class="row row-cards">
                    <div class="col-12">
                        <div class="card" id="memberSignInCard">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fa-solid fa-envelope-open-text me-2"></i><?= gettext('Member sign-in') ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?= $switchRow('bMemberSignIn', gettext('Let members sign in with their email'), $memberSignInOn, $signIn['tooltips'], $signIn['emailProblem'] !== null && !$memberSignInOn) ?>
                                <?php if ($signIn['emailProblem'] !== null): ?>
                                    <div class="alert alert-warning mb-0 mt-2" id="memberSignInEmailProblem">
                                        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= InputUtils::escapeHTML($signIn['emailProblem']) ?>
                                        <a href="<?= SystemURLs::getRootPath() ?>/admin/email" class="ms-1"><?= gettext('Set up email') ?></a>
                                    </div>
                                <?php endif; ?>
                                <div class="alert alert-warning mb-0 mt-2<?= $signIn['sharedAddresses'] > 0 ? '' : ' d-none' ?>" id="memberSignInSharedEmails">
                                    <i class="fa-solid fa-people-arrows me-2"></i><?= gettext('Shared emails that stop people from signing in') ?>:
                                    <strong data-shared-address-count><?= (int) $signIn['sharedAddresses'] ?></strong>
                                    <a href="#portal-members" class="ms-1" data-open-shared-email-fix><?= gettext('Fix shared emails') ?></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="card h-100" id="memberSignInWhoCard">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fa-solid fa-user-check me-2"></i><?= gettext('Who may sign in') ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <p class="text-secondary">
                                    <?= gettext('People who are deceased, inactive, in an inactive family, awaiting review or have Disable sign-in on never sign in. These choices decide who else may.') ?>
                                </p>
                                <fieldset class="mb-3">
                                    <legend class="form-label"><?= gettext('Family Roles') ?></legend>
                                    <div id="memberSignInFamilyRoles">
                                        <?php foreach ($signIn['familyRoles'] as $role): ?>
                                            <label class="form-check">
                                                <input class="form-check-input" type="checkbox" name="sMemberSignInFamilyRoles"
                                                       id="memberSignInRole-<?= (int) $role['id'] ?>"
                                                       value="<?= (int) $role['id'] ?>"
                                                       <?= in_array((int) $role['id'], $tickedRoles, true) ? 'checked' : '' ?>>
                                                <span class="form-check-label"><?= InputUtils::escapeHTML($role['name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="invalid-feedback d-block" data-error-for="sMemberSignInFamilyRoles"></div>
                                </fieldset>
                                <fieldset class="mb-3">
                                    <legend class="form-label"><?= gettext('Classifications') ?></legend>
                                    <div class="form-text mb-2"><?= gettext('This is where you decide about guests. The number is how many active people have each classification.') ?></div>
                                    <div id="memberSignInClassifications">
                                        <?php foreach ($signIn['classifications'] as $classification): ?>
                                            <label class="form-check">
                                                <input class="form-check-input" type="checkbox" name="sMemberSignInClassifications"
                                                       id="memberSignInClassification-<?= (int) $classification['id'] ?>"
                                                       value="<?= (int) $classification['id'] ?>"
                                                       <?= in_array((int) $classification['id'], $tickedClassifications, true) ? 'checked' : '' ?>>
                                                <span class="form-check-label">
                                                    <?= InputUtils::escapeHTML($classification['name']) ?>
                                                    <span class="badge bg-secondary text-white ms-1" data-cy="classification-count"><?= (int) $classification['activeCount'] ?></span>
                                                </span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="invalid-feedback d-block" data-error-for="sMemberSignInClassifications"></div>
                                </fieldset>
                                <?= $numberField('iMemberSignInMinAge', gettext('Minimum age'), $signInValues['iMemberSignInMinAge'], 0, 120, $signIn['tooltips']) ?>
                            </div>
                            <div class="card-footer text-secondary">
                                <?= sprintf(gettext('People the saved settings let in: %s'), '<strong id="memberSignInMayCount">' . (int) $signIn['maySignInCount'] . '</strong>') ?>
                                <div class="small"><?= gettext('Each of them also needs their own email that nobody else uses.') ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-xl-6">
                        <div class="card mb-3" id="memberSignInSessionsCard">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fa-solid fa-clock me-2"></i><?= gettext('Codes, sessions and devices') ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?= $numberField('iSignInLinkMinutes', gettext('Link and code lifetime (minutes)'), $signInValues['iSignInLinkMinutes'], 1, 1440, $signIn['tooltips']) ?>
                                <?= $numberField('iMemberSignInRequestsPerIP', gettext('Sign-in emails per IP address per hour'), $signInValues['iMemberSignInRequestsPerIP'], 0, 10000, $signIn['tooltips']) ?>
                                <?= $numberField('iPortalIdleMinutes', gettext('Idle time (minutes)'), $signInValues['iPortalIdleMinutes'], 1, 1440, $signIn['tooltips']) ?>
                                <?= $numberField('iRememberDeviceDays', gettext('Keep me signed in (days)'), $signInValues['iRememberDeviceDays'], 0, 365, $signIn['tooltips']) ?>
                                <?= $switchRow('bPortalPasskeys', gettext('Passkeys'), $signInValues['bPortalPasskeys'] === '1', $signIn['tooltips']) ?>
                                <?php if ($signIn['passkeyProblem'] !== null): ?>
                                    <div class="text-warning small" id="memberSignInPasskeyProblem">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i><?= InputUtils::escapeHTML($signIn['passkeyProblem']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="card mb-3" id="memberSignInOfficeCard">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fa-solid fa-bell me-2"></i><?= gettext('Office list') ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <label class="form-label" for="sMemberSignInNotificationRecipientIDs"><?= InputUtils::escapeHTML($signIn['tooltips']['sMemberSignInNotificationRecipientIDs']) ?></label>
                                <select multiple class="form-select" id="sMemberSignInNotificationRecipientIDs" name="sMemberSignInNotificationRecipientIDs"
                                        data-placeholder="<?= InputUtils::escapeAttribute(gettext('Type a name or email')) ?>">
                                    <?php foreach ($signIn['officeRecipients'] as $recipient): ?>
                                        <option value="<?= (int) $recipient['id'] ?>" selected><?= InputUtils::escapeHTML($recipient['text']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text"><?= gettext('Only people with their own email can be added.') ?></div>
                                <div class="invalid-feedback d-block" data-error-for="sMemberSignInNotificationRecipientIDs"></div>
                            </div>
                        </div>

                        <div class="card" id="memberSignInLoginAsCard">
                            <div class="card-header">
                                <h3 class="card-title mb-0">
                                    <i class="fa-solid fa-user-secret me-2"></i><?= gettext('Login as User') ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?= $switchRow('bAllowLoginAsUser', gettext('Allow Login as User'), $signInValues['bAllowLoginAsUser'] === '1', $signIn['tooltips']) ?>
                                <?= $switchRow('bLoginAsUserCanWrite', gettext('Allow changes during Login as User'), $signInValues['bLoginAsUserCanWrite'] === '1', $signIn['tooltips']) ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-2 mt-3">
                    <button type="submit" class="btn btn-primary" id="memberSignInSaveButton">
                        <i class="fa-solid fa-floppy-disk me-2"></i><?= gettext('Save sign-in settings') ?>
                    </button>
                    <span class="text-secondary" id="memberSignInStatus" role="status"></span>
                </div>
            </form>
        </div>

        <?php require __DIR__ . '/partials/member-portal-members.php'; ?>
        <?php require __DIR__ . '/partials/member-portal-permissions.php'; ?>
        <?php require __DIR__ . '/partials/member-portal-audit.php'; ?>

        <!-- ============================= Themes ============================= -->
        <div class="tab-pane fade" id="portal-themes" role="tabpanel">
            <p class="text-secondary" id="portalThemesLead">
                <?= gettext('A theme is a folder your church uploads to Include/themes/ on the server. The system default ships with ChurchCRM.') ?>
            </p>
            <div class="alert alert-info" id="portalThemeDisclaimer">
                <i class="fa-solid fa-circle-info me-2"></i>
                <?= gettext('Themes are provided by your church, not by ChurchCRM, and are not verified by the ChurchCRM project.') ?>
                <a href="<?= InputUtils::escapeAttribute($themesDocUrl) ?>" target="_blank" rel="noopener" class="ms-1">
                    <?= gettext('Read the theme authoring guide') ?>
                </a>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="portalThemesTable">
                        <thead>
                            <tr>
                                <th><?= gettext('Theme') ?></th>
                                <th class="d-none d-md-table-cell"><?= gettext('Folder') ?></th>
                                <th class="d-none d-md-table-cell"><?= gettext('Author') ?></th>
                                <th class="d-none d-md-table-cell text-center"><?= gettext('Overridden templates') ?></th>
                                <th><?= gettext('Validation') ?></th>
                                <th class="w-1"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($themes as $theme): ?>
                                <?php [$badgeClass, $badgeIcon, $badgeLabel] = $statusBadge($theme['status']); ?>
                                <tr data-theme="<?= InputUtils::escapeAttribute($theme['id']) ?>">
                                    <td>
                                        <span class="fw-bold"><?= InputUtils::escapeHTML($themeLabel($theme)) ?></span>
                                        <?php if ($theme['id'] === $activeTheme): ?>
                                            <span class="badge bg-primary text-white ms-2"><?= gettext('Active') ?></span>
                                        <?php endif; ?>
                                        <?php if ($theme['description'] !== ''): ?>
                                            <div class="text-secondary small"><?= InputUtils::escapeHTML($theme['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="d-none d-md-table-cell"><code><?= InputUtils::escapeHTML($theme['id']) ?></code></td>
                                    <td class="d-none d-md-table-cell"><?= $theme['author'] !== '' ? InputUtils::escapeHTML($theme['author']) : '<span class="text-secondary">&mdash;</span>' ?></td>
                                    <td class="d-none d-md-table-cell text-center"><?= (int) $theme['overriddenTemplates'] ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?> portal-theme-status">
                                            <i class="fa-solid <?= $badgeIcon ?> me-1"></i><?= InputUtils::escapeHTML($badgeLabel) ?>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-ghost-secondary portal-theme-findings-toggle<?= $theme['findings'] === [] ? ' d-none' : '' ?>"
                                                data-bs-toggle="collapse"
                                                data-bs-target="#portalFindings-<?= InputUtils::escapeAttribute($theme['id']) ?>">
                                            <?= gettext('Findings') ?>
                                            <span class="badge bg-secondary text-white ms-1 portal-theme-findings-count"><?= count($theme['findings']) ?></span>
                                        </button>
                                    </td>
                                    <td class="w-1">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-ghost-secondary" type="button"
                                                    data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button type="button" class="dropdown-item portal-theme-activate"
                                                        data-theme="<?= InputUtils::escapeAttribute($theme['id']) ?>"
                                                        <?= $theme['id'] === $activeTheme ? 'disabled' : '' ?>>
                                                    <i class="fa-solid fa-circle-check me-2"></i><?= gettext('Activate') ?>
                                                </button>
                                                <button type="button" class="dropdown-item portal-theme-check"
                                                        data-theme="<?= InputUtils::escapeAttribute($theme['id']) ?>">
                                                    <i class="fa-solid fa-stethoscope me-2"></i><?= gettext('Check') ?>
                                                </button>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="collapse portal-theme-findings-row" id="portalFindings-<?= InputUtils::escapeAttribute($theme['id']) ?>">
                                    <td colspan="6" class="bg-surface-secondary portal-theme-findings">
                                        <?php if ($theme['findings'] !== []): ?>
                                            <ul class="list-unstyled mb-0">
                                                <?php foreach ($theme['findings'] as $finding): ?>
                                                    <li class="py-1">
                                                        <span class="badge <?= $finding['level'] === ThemeValidator::LEVEL_ERROR ? 'bg-danger text-white' : 'bg-warning text-dark' ?> me-2">
                                                            <?= $finding['level'] === ThemeValidator::LEVEL_ERROR ? gettext('Error') : gettext('Warning') ?>
                                                        </span>
                                                        <?php if ($finding['file'] !== ''): ?>
                                                            <code><?= InputUtils::escapeHTML($finding['file']) ?></code><?php if ($finding['line'] > 0): ?>
                                                                <span class="text-secondary"><?= sprintf(gettext('line %d'), (int) $finding['line']) ?></span>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                        <span><?= InputUtils::escapeHTML($finding['message']) ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="portalDeveloperModePanel" class="mt-3"></div>
        </div>

        <!-- =========================== Statistics =========================== -->
        <div class="tab-pane fade show active" id="portal-statistics" role="tabpanel">
            <p class="text-secondary">
                <?= gettext('Member Portal sign-ins and activity, for members and for staff who open the portal from their staff sign-in.') ?>
            </p>

            <div class="row row-cards mb-3" id="portalStatCards">
                <?php
                $statCards = [
                    ['id' => 'activeNow', 'label' => gettext('Active now'), 'hint' => gettext('In the last 15 minutes'), 'icon' => 'fa-circle-dot', 'color' => 'bg-green'],
                    ['id' => 'signedIn24Hours', 'label' => gettext('Last 24 hours'), 'hint' => gettext('Signed in'), 'icon' => 'fa-clock', 'color' => 'bg-blue'],
                    ['id' => 'signedIn7Days', 'label' => gettext('Last 7 days'), 'hint' => gettext('Signed in'), 'icon' => 'fa-calendar-week', 'color' => 'bg-azure'],
                    ['id' => 'signedIn30Days', 'label' => gettext('Last 30 days'), 'hint' => gettext('Signed in'), 'icon' => 'fa-calendar-days', 'color' => 'bg-indigo'],
                    ['id' => 'maySignIn', 'label' => gettext('May sign in'), 'hint' => gettext('Total'), 'icon' => 'fa-users', 'color' => 'bg-secondary'],
                    ['id' => 'neverSignedIn', 'label' => gettext('Never signed in'), 'hint' => gettext('May sign in'), 'icon' => 'fa-user-slash', 'color' => 'bg-orange'],
                ];
                foreach ($statCards as $card): ?>
                    <div class="col-6 col-lg-4 col-xl-2">
                        <div class="card card-sm">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-auto">
                                        <span class="<?= $card['color'] ?> text-white avatar rounded-circle">
                                            <i class="fa-solid <?= $card['icon'] ?>"></i>
                                        </span>
                                    </div>
                                    <div class="col">
                                        <div class="h2 mb-0" id="portalStat-<?= $card['id'] ?>"><?= (int) $stats[$card['id']] ?></div>
                                        <div class="fw-medium"><?= InputUtils::escapeHTML($card['label']) ?></div>
                                        <div class="text-secondary small"><?= InputUtils::escapeHTML($card['hint']) ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="fa-solid fa-right-to-bracket me-2"></i><?= gettext('Ten most recent sign-ins') ?>
                    </h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="portalRecentSignInsTable">
                        <thead>
                            <tr>
                                <th><?= gettext('Member') ?></th>
                                <th><?= gettext('How') ?></th>
                                <th><?= gettext('Last sign-in') ?></th>
                                <th><?= gettext('Last seen in the portal') ?></th>
                                <th class="w-1 no-export"><?= gettext('Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($stats['recentSignIns'] === []): ?>
                                <tr id="portalNoRecentSignIns">
                                    <td colspan="5" class="text-secondary">
                                        <?= gettext('No member has signed in to the Member Portal yet.') ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($stats['recentSignIns'] as $recentSignIn): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= SystemURLs::getRootPath() ?>/people/view/<?= (int) $recentSignIn['personId'] ?>">
                                                <?= InputUtils::escapeHTML($recentSignIn['name']) ?>
                                            </a>
                                        </td>
                                        <td><?= InputUtils::escapeHTML((string) $recentSignIn['methodLabel']) ?></td>
                                        <td><?= InputUtils::escapeHTML(DateTimeUtils::formatDate($recentSignIn['lastSignIn'], true)) ?></td>
                                        <td>
                                            <?= $recentSignIn['lastActivity'] !== ''
                                                ? InputUtils::escapeHTML(DateTimeUtils::formatDate($recentSignIn['lastActivity'], true))
                                                : '<span class="text-secondary">&mdash;</span>' ?>
                                        </td>
                                        <td class="w-1">
                                            <?php $personId = (int) $recentSignIn['personId']; ?>
                                            <?php $inCart = isset($_SESSION['aPeopleCart']) && in_array($personId, $_SESSION['aPeopleCart'], false); ?>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-ghost-secondary" type="button"
                                                        data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false"
                                                        aria-label="<?= InputUtils::escapeAttribute(gettext('Actions')) ?>">
                                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                                </button>
                                                <div class="dropdown-menu dropdown-menu-end">
                                                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/people/view/<?= $personId ?>">
                                                        <i class="fa-solid fa-eye me-2"></i><?= gettext('View') ?>
                                                    </a>
                                                    <a class="dropdown-item" href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php?PersonID=<?= $personId ?>">
                                                        <i class="fa-solid fa-pencil me-2"></i><?= gettext('Edit') ?>
                                                    </a>
                                                    <div class="dropdown-divider"></div>
                                                    <button type="button"
                                                        class="dropdown-item <?= $inCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
                                                        data-cart-id="<?= $personId ?>"
                                                        data-cart-type="person"
                                                        data-label-add="<?= InputUtils::escapeAttribute(gettext('Add to Cart')) ?>"
                                                        data-label-remove="<?= InputUtils::escapeAttribute(gettext('Remove from Cart')) ?>">
                                                        <i class="<?= $inCart ? 'fa-solid fa-trash' : 'fa-solid fa-cart-shopping' ?> me-2"></i>
                                                        <span class="cart-label"><?= $inCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================ Calendars =========================== -->
        <div class="tab-pane fade" id="portal-calendars" role="tabpanel">
            <div id="portalCalendarSectionPanel" class="mb-3"></div>

            <p class="text-secondary" id="portalCalendarsLead">
                <?= gettext('Members see only the calendars switched on here. Birthdays and anniversaries show first names and last initials only.') ?>
            </p>

            <div class="card">
                <div class="table-responsive">
                    <table class="table table-vcenter card-table portal-calendars-table" id="portalCalendarsTable">
                        <thead>
                            <tr>
                                <th><?= gettext('Calendar') ?></th>
                                <th><?= gettext('Kind') ?></th>
                                <th class="w-1"><?= gettext('Colour') ?></th>
                                <th class="text-wrap"><?= gettext('Show in Member Portal') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($portalCalendars as $portalCalendar): ?>
                                <?php
                                $rowKey = $portalCalendar['type'] . '-' . $portalCalendar['id'];
                                $kind = $calendarKind($portalCalendar);
                                ?>
                                <tr data-calendar-type="<?= InputUtils::escapeAttribute($portalCalendar['type']) ?>"
                                    data-calendar-id="<?= (int) $portalCalendar['id'] ?>">
                                    <td><span class="fw-bold"><?= InputUtils::escapeHTML($portalCalendar['name']) ?></span></td>
                                    <td class="text-secondary"><?= InputUtils::escapeHTML($kind) ?></td>
                                    <td>
                                        <span class="portal-calendar-swatch d-inline-block rounded border"
                                              style="inline-size: 1.25rem; block-size: 1.25rem; background-color: <?= InputUtils::escapeAttribute($portalCalendar['colors']['background']) ?>;"
                                              title="<?= InputUtils::escapeAttribute($portalCalendar['colors']['background']) ?>"
                                              aria-hidden="true"></span>
                                    </td>
                                    <td>
                                        <label class="form-check form-switch mb-0">
                                            <input class="form-check-input portal-calendar-switch" type="checkbox"
                                                   id="portalCalendarSwitch-<?= InputUtils::escapeAttribute($rowKey) ?>"
                                                   <?= $portalCalendar['visible'] ? 'checked' : '' ?>>
                                            <span class="visually-hidden">
                                                <?= sprintf(gettext('Show "%s" in the Member Portal'), InputUtils::escapeHTML($portalCalendar['name'])) ?>
                                            </span>
                                        </label>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($portalCalendars === []): ?>
                                <tr id="portalNoCalendars">
                                    <td colspan="4" class="text-secondary">
                                        <?= gettext('This installation has no calendars yet.') ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-primary" id="portalCalendarsSaveButton">
                        <i class="fa-solid fa-floppy-disk me-2"></i><?= gettext('Save calendars') ?>
                    </button>
                    <span class="text-secondary" id="portalCalendarsStatus" role="status"></span>
                </div>
            </div>
        </div>

    </div>
</div>

<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = window.CRM || {};
    window.CRM.memberPortalAdmin = <?= InputUtils::jsonEncodeForScript([
        'activeTheme' => $activeTheme,
        'defaultThemeId' => ThemeManager::DEFAULT_THEME,
        'csrfToken' => $csrfToken,
    ]) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-member-portal.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
