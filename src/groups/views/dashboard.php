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

    <!-- Overview Card -->
    <div class="card card-primary card-outline mb-3">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title"><i class="fa-solid fa-sitemap"></i> <?= gettext('Overview') ?></h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-truncate">
                                <h3 class="card-title text-primary">
                                    <div class="stat-icon bg-primary text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                        <i class="fa-solid fa-users"></i>
                                    </div>
                                </h3>
                                <div class="h6 text-muted"><?= gettext('Total Groups') ?></div>
                                <div class="h2 m-0"><?= $totalGroups ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-truncate">
                                <h3 class="card-title text-success">
                                    <div class="stat-icon bg-success text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                        <i class="fa-solid fa-check-circle"></i>
                                    </div>
                                </h3>
                                <div class="h6 text-muted"><?= gettext('Active Groups') ?></div>
                                <div class="h2 m-0"><?= $activeGroups ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-truncate">
                                <h3 class="card-title text-danger">
                                    <div class="stat-icon bg-danger text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                        <i class="fa-solid fa-ban"></i>
                                    </div>
                                </h3>
                                <div class="h6 text-muted"><?= gettext('Inactive Groups') ?></div>
                                <div class="h2 m-0"><?= $inactiveGroups ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="card card-sm">
                        <div class="card-body">
                            <div class="text-truncate">
                                <h3 class="card-title text-info">
                                    <div class="stat-icon bg-info text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                        <i class="fa-solid fa-user-tie"></i>
                                    </div>
                                </h3>
                                <div class="h6 text-muted"><?= gettext('Memberships') ?></div>
                                <div class="h2 m-0"><?= $totalMemberships ?></div>
                            </div>
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
