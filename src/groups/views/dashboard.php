<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Dashboard metrics — 2 queries: total+active at DB level; inactive = total - active
$totalGroups      = GroupQuery::create()->count();
$activeGroups     = GroupQuery::create()->filterByActive(true)->count();
$inactiveGroups   = $totalGroups - $activeGroups;
$totalMemberships = Person2group2roleP2g2rQuery::create()->count();

?>

<div class="container-fluid">

    <!-- Key Metrics Row -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 groups-metric-card metric-total">
                <div class="card-body text-center py-4">
                    <div class="metric-value"><?= $totalGroups ?></div>
                    <div class="metric-label text-uppercase small font-weight-bold mt-2">
                        <?= gettext('Total Groups') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 groups-metric-card metric-active">
                <div class="card-body text-center py-4">
                    <div class="metric-value"><?= $activeGroups ?></div>
                    <div class="metric-label text-uppercase small font-weight-bold mt-2">
                        <?= gettext('Active Groups') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 groups-metric-card metric-inactive">
                <div class="card-body text-center py-4">
                    <div class="metric-value"><?= $inactiveGroups ?></div>
                    <div class="metric-label text-uppercase small font-weight-bold mt-2">
                        <?= gettext('Inactive Groups') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100 groups-metric-card metric-memberships">
                <div class="card-body text-center py-4">
                    <div class="metric-value"><?= $totalMemberships ?></div>
                    <div class="metric-label text-uppercase small font-weight-bold mt-2">
                        <?= gettext('Total Memberships') ?>
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
                <div class="card-header bg-primary text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-plus-circle"></i> <?= gettext('Add New Group') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="form-group mb-2">
                        <label for="groupName">
                            <?= gettext('Group Name') ?> <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="groupName" id="groupName"
                               placeholder="<?= gettext('Enter group name') ?>"
                               aria-describedby="groupNameFeedback">
                        <div id="groupNameFeedback" class="invalid-feedback" role="alert">
                            <?= gettext('Please enter a group name.') ?>
                        </div>
                        <small class="form-text text-muted"><?= gettext('Required') ?></small>
                    </div>
                    <button type="button" class="btn btn-primary" id="addNewGroup">
                        <i class="fa fa-plus"></i> <?= gettext('Add Group') ?>
                    </button>
                </div>
            </div>

            <!-- Groups Table -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-secondary text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-sitemap"></i> <?= gettext('Groups') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <table class="table" id="groupsTable"></table>
                </div>
            </div>

        </div>

    </div>

</div>

<script src="<?= SystemURLs::getRootPath() ?>/skin/js/GroupList.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
