<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">

            <!-- Welcome message -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-status-top bg-success"></div>
                <div class="card-header py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-circle-info"></i> <?= gettext('Recommended Order') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-3"><?= gettext('Follow these two steps to add your church members quickly and correctly.') ?></p>

                    <!-- Step 1 -->
                    <div class="d-flex align-items-start mb-4">
                        <div class="me-3 mt-1">
                            <span class="badge bg-primary text-white rounded-pill" style="font-size:1rem;padding:.5em .75em;">1</span>
                        </div>
                        <div>
                            <h6 class="mb-1"><?= gettext('Add Your First Family') ?></h6>
                            <p class="text-muted mb-2">
                                <?= gettext('A family represents a household. It holds the shared address and home phone number for all family members living together.') ?>
                            </p>
                            <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php" class="btn btn-success btn-sm">
                                <i class="fa-solid fa-house-circle-plus me-1"></i><?= gettext('Add First Family') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-status-top bg-info"></div>
                <div class="card-header py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-lightbulb"></i> <?= gettext('Quick Tips') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fa-solid fa-circle-check text-success me-2"></i>
                            <?= gettext('Families share an address and phone number.') ?>
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-circle-check text-success me-2"></i>
                            <?= gettext('Each person can have their own email and mobile number.') ?>
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-circle-check text-success me-2"></i>
                            <?= gettext('Family roles (Head of Household, Spouse, Child, etc.) help organise your records.') ?>
                        </li>
                        <li class="mb-2">
                            <i class="fa-solid fa-circle-check text-success me-2"></i>
                            <?= gettext('You can always import more data later via CSV.') ?>
                        </li>
                        <li class="mb-0">
                            <i class="fa-solid fa-circle-info text-info me-2"></i>
                            <?= gettext('Donations and giving records are tracked at the family level. If you plan to record giving for an individual who lives alone, create a single-person family for them.') ?>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- CTA Buttons -->
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-4 mb-2 mb-sm-0">
                            <a href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php" class="btn btn-success w-100">
                                <i class="fa-solid fa-house-circle-plus me-1"></i><?= gettext('Add First Family') ?>
                            </a>
                        </div>
                        <div class="col-sm-4 mb-2 mb-sm-0">
                            <a href="<?= SystemURLs::getRootPath() ?>/PersonEditor.php" class="btn btn-primary w-100">
                                <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add a Person') ?>
                            </a>
                        </div>
                        <div class="col-sm-4">
                            <a href="<?= SystemURLs::getRootPath() ?>/admin/get-started" class="btn btn-outline-secondary w-100">
                                <i class="fa-solid fa-arrow-left me-1"></i><?= gettext('Back to Get Started') ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Sidebar hint -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-status-top bg-secondary"></div>
                <div class="card-header py-2">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-circle-question"></i> <?= gettext('Family vs Person') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">
                        <strong><?= gettext('Family') ?>:</strong>
                        <?= gettext('A household. Stores the shared mailing address and home phone.') ?>
                    </p>
                    <p class="small text-muted mb-2">
                        <strong><?= gettext('Person') ?>:</strong>
                        <?= gettext('An individual church member. Stores name, birthday, personal email, and mobile.') ?>
                    </p>
                    <p class="small text-muted mb-0">
                        <i class="fa-solid fa-circle-info text-info me-1"></i>
                        <?= gettext('A person does not need to belong to a family, but assigning one makes address management much easier.') ?>
                    </p>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-light py-2">
                    <h5 class="mb-0 text-dark">
                        <i class="fa-solid fa-file-import text-secondary"></i> <?= gettext('Have Existing Data?') ?>
                    </h5>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-3">
                        <?= gettext('If you have many records in a spreadsheet, the CSV import may be faster than entering data manually.') ?>
                    </p>
                    <a href="<?= SystemURLs::getRootPath() ?>/admin/import/csv" class="btn btn-outline-info btn-sm w-100">
                        <i class="fa-solid fa-upload me-1"></i><?= gettext('Import from CSV') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
