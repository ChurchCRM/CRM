<?php

use ChurchCRM\dto\SystemURLs;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-fluid" id="important-date-types">
    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-calendar-day me-2"></i><?= gettext('Date Types') ?></h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table" id="important-date-types-table">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('Recorded on') ?></th>
                                <th class="text-center"><?= gettext('Several per record') ?></th>
                                <th class="text-center"><?= gettext('On staff calendar') ?></th>
                                <th class="text-center"><?= gettext('On') ?></th>
                                <th class="w-1"><span class="visually-hidden"><?= gettext('Actions') ?></span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="6" class="text-body-secondary"><?= gettext('Loading...') ?></td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer text-body-secondary small">
                    <?= gettext('Turning a type off keeps every date already recorded and stops new ones. A type can be deleted only while no dates use it.') ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3" id="important-date-presets-card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-wand-magic-sparkles me-2"></i><?= gettext('Suggested Types') ?></h3>
                </div>
                <div class="card-body">
                    <p class="text-body-secondary small"><?= gettext('Add a common type in one click. Rename it afterwards to match your tradition.') ?></p>
                    <div class="d-flex flex-wrap gap-2" id="important-date-presets"></div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fa-solid fa-plus me-2"></i><?= gettext('Add Your Own') ?></h3>
                </div>
                <div class="card-body">
                    <form id="important-date-type-form" novalidate>
                        <div class="mb-3">
                            <label class="form-label" for="idt-name"><?= gettext('Name') ?></label>
                            <input type="text" class="form-control" id="idt-name" name="name" maxlength="100" required placeholder="<?= gettext('e.g. Commissioning') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="idt-applies-to"><?= gettext('Recorded on') ?></label>
                            <select class="form-select" id="idt-applies-to" name="appliesTo">
                                <option value="person"><?= gettext('People') ?></option>
                                <option value="family"><?= gettext('Families') ?></option>
                            </select>
                        </div>
                        <div class="mb-2 form-check">
                            <input type="checkbox" class="form-check-input" id="idt-allow-multiple" name="allowMultiple">
                            <label class="form-check-label" for="idt-allow-multiple"><?= gettext('Allow several dates per record') ?></label>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="idt-show-on-calendar" name="showOnCalendar" checked>
                            <label class="form-check-label" for="idt-show-on-calendar"><?= gettext('Show on the staff Important Dates calendar') ?></label>
                            <div class="form-text"><?= gettext('Never shown on the Member Portal.') ?></div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-plus me-1"></i><?= gettext('Add Type') ?></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/v2/admin-important-dates.min.js') ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
