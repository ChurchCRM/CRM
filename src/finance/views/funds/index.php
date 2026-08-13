<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * Donation Funds admin page view.
 *
 * Variables injected by funds.php route:
 *
 * @var string $sRootPath
 * @var string $sPageTitle
 * @var string $sPageSubtitle
 * @var array  $aBreadcrumbs
 * @var array  $fundsData    Array of fund rows: id, name, description, active,
 *                            order, hasPledges, isFirst, isLast
 */

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="container-xl">

    <!-- Edit Fund Modal -->
    <div class="modal fade" id="editFundModal" tabindex="-1" aria-labelledby="editFundModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editFundModalLabel"><?= gettext('Edit Donation Fund') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="<?= gettext('Close') ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editFundId">
                    <div class="mb-3">
                        <label for="editFundName" class="form-label"><?= gettext('Name') ?> <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="editFundName" maxlength="30">
                    </div>
                    <div class="mb-3">
                        <label for="editFundDesc" class="form-label"><?= gettext('Description') ?></label>
                        <input type="text" class="form-control" id="editFundDesc" maxlength="100">
                    </div>
                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editFundActive">
                            <label class="form-check-label" for="editFundActive"><?= gettext('Active') ?></label>
                        </div>
                    </div>
                    <div id="editFundError" class="alert alert-danger d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary"
                            data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                    <button type="button" class="btn btn-primary"
                            id="saveFundEdit"><?= gettext('Save Changes') ?></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add New Fund Card -->
    <div class="card mb-4">
        <div class="card-status-top bg-success"></div>
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-plus me-2"></i><?= gettext('Add New Fund') ?>
            </h3>
        </div>
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label for="newFundName" class="form-label"><?= gettext('Name') ?> <span class="text-danger">*</span></label>
                    <input type="text" id="newFundName" class="form-control" maxlength="30"
                           placeholder="<?= InputUtils::escapeAttribute(gettext('Fund name')) ?>">
                </div>
                <div class="col-md-5">
                    <label for="newFundDesc" class="form-label"><?= gettext('Description') ?></label>
                    <input type="text" id="newFundDesc" class="form-control" maxlength="100"
                           placeholder="<?= InputUtils::escapeAttribute(gettext('Optional description')) ?>">
                </div>
                <div class="col-md-3">
                    <button type="button" class="btn btn-success w-100" id="addNewFund">
                        <i class="fa-solid fa-plus me-1"></i><?= gettext('Add New Fund') ?>
                    </button>
                </div>
            </div>
            <div id="addFundError" class="alert alert-danger mt-3 d-none"></div>
        </div>
    </div>

    <!-- Existing Funds Card -->
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title">
                <i class="fa-solid fa-list me-2"></i><?= gettext('Existing Donation Funds') ?>
            </h3>
            <span class="badge bg-info text-white ms-auto">
                <?= count($fundsData) ?> <?= gettext('funds') ?>
            </span>
        </div>
        <?php if (empty($fundsData)): ?>
            <div class="card-body">
                <div class="alert alert-info mb-0">
                    <i class="fa-solid fa-circle-info me-1"></i>
                    <?= gettext('No funds have been added yet. Use the form above to add the first fund.') ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card-body p-0">
                <!-- overflow-x:clip keeps horizontal containment while letting dropdowns escape vertically -->
                <div style="overflow-x: clip; overflow-y: visible;">
                    <table id="fundsTable" class="table table-hover table-vcenter card-table data-table">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('Description') ?></th>
                                <th><?= gettext('Active') ?></th>
                                <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fundsData as $fund): ?>
                                <?php
                                    $fundId       = (int) $fund['id'];
                                    $fundName     = InputUtils::escapeHTML($fund['name']);
                                    $fundDesc     = InputUtils::escapeHTML($fund['description']);
                                    $isActive     = (bool) $fund['active'];
                                    $hasPledges   = (bool) $fund['hasPledges'];
                                    $isFirst      = (bool) $fund['isFirst'];
                                    $isLast       = (bool) $fund['isLast'];

                                    // Escape for data-* attribute JSON embedding
                                    $nameAttr     = InputUtils::escapeAttribute($fund['name']);
                                    $descAttr     = InputUtils::escapeAttribute($fund['description']);
                                ?>
                                <tr>
                                    <td><?= $fundName ?></td>
                                    <td><?= $fundDesc !== '' ? $fundDesc : '<span class="text-muted">—</span>' ?></td>
                                    <td>
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success-lt text-success">
                                                <i class="ti ti-circle-check me-1"></i><?= gettext('Active') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-lt text-secondary">
                                                <i class="ti ti-circle-x me-1"></i><?= gettext('Inactive') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="w-1">
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-ghost-secondary" type="button"
                                                    data-bs-toggle="dropdown"
                                                    data-bs-display="static"
                                                    aria-expanded="false">
                                                <i class="ti ti-dots-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu dropdown-menu-end">
                                                <button type="button" class="dropdown-item fund-edit-btn"
                                                        data-fund-id="<?= $fundId ?>"
                                                        data-fund-name="<?= $nameAttr ?>"
                                                        data-fund-desc="<?= $descAttr ?>"
                                                        data-fund-active="<?= $isActive ? 'true' : 'false' ?>">
                                                    <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                                </button>
                                                <?php if (!$isFirst): ?>
                                                    <button type="button" class="dropdown-item fund-order-btn"
                                                            data-fund-id="<?= $fundId ?>"
                                                            data-direction="up">
                                                        <i class="ti ti-arrow-up me-2"></i><?= gettext('Move up') ?>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!$isLast): ?>
                                                    <button type="button" class="dropdown-item fund-order-btn"
                                                            data-fund-id="<?= $fundId ?>"
                                                            data-direction="down">
                                                        <i class="ti ti-arrow-down me-2"></i><?= gettext('Move down') ?>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!$isFirst || !$isLast): ?>
                                                <div class="dropdown-divider"></div>
                                                <?php endif; ?>
                                                <?php if ($hasPledges): ?>
                                                    <button type="button" class="dropdown-item text-danger disabled"
                                                            disabled
                                                            title="<?= InputUtils::escapeAttribute(gettext('Cannot delete: fund has associated pledges')) ?>">
                                                        <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="dropdown-item text-danger fund-delete-btn"
                                                            data-fund-id="<?= $fundId ?>"
                                                            data-fund-name="<?= $nameAttr ?>">
                                                        <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

</div><!-- /.container-xl -->

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    /* Pass i18n strings and root path to the external script */
    window.CRM = window.CRM || {};
    window.CRM.funds = {
        apiBase: <?= json_encode(SystemURLs::getRootPath() . '/finance/api/funds') ?>,
        i18n: {
            confirmDelete:    <?= json_encode(gettext('Are you sure you want to delete this fund?')) ?>,
            deleteWarning:    <?= json_encode(gettext('This action cannot be undone.')) ?>,
            deleteTitle:      <?= json_encode(gettext('Delete Fund')) ?>,
            cancel:           <?= json_encode(gettext('Cancel')) ?>,
            delete:           <?= json_encode(gettext('Delete')) ?>,
            deletedOk:        <?= json_encode(gettext('Fund deleted successfully.')) ?>,
            savedOk:          <?= json_encode(gettext('Fund saved successfully.')) ?>,
            addedOk:          <?= json_encode(gettext('Fund added successfully.')) ?>,
            reorderedOk:      <?= json_encode(gettext('Fund order updated.')) ?>,
            errRequired:      <?= json_encode(gettext('Fund name is required.')) ?>,
            errServer:        <?= json_encode(gettext('An error occurred. Please try again.')) ?>
        }
    };
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/js/finance-funds.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
