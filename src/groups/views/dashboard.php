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

    <!-- Stat Cards Row -->
    <div class="row mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <span class="bg-primary text-white avatar rounded-circle">
                                <i class="fa-solid fa-users"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium"><?= $totalGroups ?></div>
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
                                <i class="fa-solid fa-check-circle"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium"><?= $activeGroups ?></div>
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
                                <i class="fa-solid fa-ban"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium"><?= $inactiveGroups ?></div>
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
                                <i class="fa-solid fa-user-tie"></i>
                            </span>
                        </div>
                        <div class="col">
                            <div class="font-weight-medium"><?= $totalMemberships ?></div>
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
                <div class="card-header bg-primary text-white py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-plus-circle"></i> <?= gettext('Add New Group') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 mb-2">
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
