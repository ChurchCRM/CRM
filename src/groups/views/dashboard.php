<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Dashboard metrics — 2 queries: total+active at DB level; inactive = total - active
$totalGroups      = GroupQuery::create()->count();
$activeGroups     = GroupQuery::create()->filterByActive(true)->count();
$inactiveGroups   = $totalGroups - $activeGroups;
$totalMemberships = Person2group2roleP2g2rQuery::create()->count();

?>

<div class="container-fluid">

    <!-- Stat Cards Row -->
    <div class="row mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar rounded-circle">
                                <i class="fa-solid fa-users icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $totalGroups ?></div>
                            <div class="text-muted"><?= gettext('Total Groups') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-success text-white avatar rounded-circle">
                                <i class="fa-solid fa-circle-check icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $activeGroups ?></div>
                            <div class="text-muted"><?= gettext('Active Groups') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-danger text-white avatar rounded-circle">
                                <i class="fa-solid fa-ban icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $inactiveGroups ?></div>
                            <div class="text-muted"><?= gettext('Inactive Groups') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-info text-white avatar rounded-circle">
                                <i class="fa-solid fa-user-tie icon"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="fw-medium"><?= $totalMemberships ?></div>
                            <div class="text-muted"><?= gettext('Memberships') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Main Content -->
        <div class="col-12">

            <!-- Add Group -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-status-top bg-primary"></div>
                <div class="card-header py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-circle-plus"></i> <?= gettext('Add New Group') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-5">
                            <label for="groupName" class="form-label">
                                <?= gettext('Group Name') ?> <span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" name="groupName" id="groupName"
                                   placeholder="<?= gettext('Enter group name') ?>"
                                   aria-describedby="groupNameFeedback">
                            <div id="groupNameFeedback" class="invalid-feedback" role="alert">
                                <?= gettext('Please enter a group name.') ?>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="groupType" class="form-label">
                                <?= gettext('Group Type') ?>
                            </label>
                            <select class="form-select" id="groupType" name="groupType">
                                <option value=""><?= gettext('— Select type (optional) —') ?></option>
                                <?php foreach ($groupTypes as $type): ?>
                                    <option value="<?= (int) $type['id'] ?>"><?= InputUtils::escapeHTML($type['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="button" class="btn btn-primary w-100" id="addNewGroup">
                                <i class="fa fa-plus"></i> <?= gettext('Add Group') ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Groups Table -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-status-top bg-secondary"></div>
                <div class="card-header py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-sitemap"></i> <?= gettext('Groups') ?>
                    </h5>
                </div>
                <div class="card-body" style="overflow: visible;">
                    <table class="table" id="groupsTable"></table>
                </div>
            </div>

        </div>

    </div>

</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/GroupList.js"></script>

<?php if ($isAdmin): ?>
<link rel="stylesheet" href="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.css') ?>">
<script src="<?= SystemURLs::assetVersioned('/skin/v2/system-settings-panel.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
$(document).ready(function () {
    window.CRM.settingsPanel.init({
        container: '#groupSettings',
        title: <?= json_encode(gettext('Group Settings'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        icon: 'fa-solid fa-sliders',
        settings: [
            {
                name: 'bEnabledSundaySchool',
                type: 'boolean',
                label: <?= json_encode(gettext('Sunday School Module'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                tooltip: <?= json_encode(gettext('Enable or disable the Sunday School module and sidebar menu.'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
            }
        ],
        onSave: function () {
            setTimeout(function () { window.location.reload(); }, 1500);
        }
    });
});
</script>
<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
