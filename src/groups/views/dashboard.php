<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Dashboard metrics
$totalGroups      = GroupQuery::create()->count();
$activeGroups     = GroupQuery::create()->filterByActive(true)->count();
$inactiveGroups   = GroupQuery::create()->filterByActive(false)->count();
$totalMemberships = Person2group2roleP2g2rQuery::create()->count();

?>

<div class="container-fluid">

    <!-- Key Metrics Row -->
    <div class="row">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4" style="background: linear-gradient(135deg, #007bff, #0056b3); color: #fff;">
                    <div style="font-size: 2.2rem; font-weight: 700;"><?= $totalGroups ?></div>
                    <div class="text-uppercase small font-weight-bold mt-2" style="opacity: .8;">
                        <?= gettext('Total Groups') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4" style="background: linear-gradient(135deg, #28a745, #1e7e34); color: #fff;">
                    <div style="font-size: 2.2rem; font-weight: 700;"><?= $activeGroups ?></div>
                    <div class="text-uppercase small font-weight-bold mt-2" style="opacity: .8;">
                        <?= gettext('Active Groups') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4" style="background: linear-gradient(135deg, #6c757d, #495057); color: #fff;">
                    <div style="font-size: 2.2rem; font-weight: 700;"><?= $inactiveGroups ?></div>
                    <div class="text-uppercase small font-weight-bold mt-2" style="opacity: .8;">
                        <?= gettext('Inactive Groups') ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-body text-center py-4" style="background: linear-gradient(135deg, #fd7e14, #dc6502); color: #fff;">
                    <div style="font-size: 2.2rem; font-weight: 700;"><?= $totalMemberships ?></div>
                    <div class="text-uppercase small font-weight-bold mt-2" style="opacity: .8;">
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
                               placeholder="<?= gettext('Enter group name') ?>">
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
