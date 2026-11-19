<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\DashboardLandingService;
use ChurchCRM\Utils\InputUtils;

$dashboardUser = AuthenticationManager::getCurrentUser();
$dashboardTabs = DashboardLandingService::tabsFor($dashboardUser);
if (count($dashboardTabs) < 2) {
    return;
}
$dashboardRoot = SystemURLs::getRootPath();
$dashboardLanding = DashboardLandingService::keyFor($dashboardUser);
?>
<div class="d-flex align-items-start gap-2 mb-3">
    <ul class="nav nav-pills flex-nowrap overflow-auto flex-grow-1" id="dashboardSwitch" aria-label="<?= gettext('Dashboard') ?>">
        <?php foreach ($dashboardTabs as $key => [$path, $label, $icon]) : ?>
            <li class="nav-item flex-shrink-0">
                <a class="nav-link<?= $dashboardSwitchActive === $key ? ' active' : '' ?>" href="<?= $dashboardRoot . $path ?>"<?= $dashboardSwitchActive === $key ? ' aria-current="page"' : '' ?>>
                    <i class="fa-solid <?= $icon ?> me-2"></i><?= $label ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0" id="setLandingPage" data-landing="<?= $dashboardSwitchActive ?>" title="<?= InputUtils::escapeAttribute(gettext('Set as my main dashboard')) ?>" aria-label="<?= InputUtils::escapeAttribute(gettext('Set as my main dashboard')) ?>"<?= $dashboardLanding === $dashboardSwitchActive ? ' disabled' : '' ?>>
        <i class="fa-solid fa-thumbtack me-1"></i><span class="landing-label d-none d-md-inline"><?= $dashboardLanding === $dashboardSwitchActive ? gettext('Your main dashboard') : gettext('Set as my main dashboard') ?></span>
    </button>
</div>
