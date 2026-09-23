<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$reportUrl = $sRootPath . '/v2/reports/people/' . InputUtils::escapeAttribute($slug);
$canEdit = AuthenticationManager::getCurrentUser()->isEditRecordsEnabled();
$cart = $_SESSION['aPeopleCart'] ?? [];
$missingLabels = array_map(static fn (string $key): string => $report['params'][$key]['label'], $missing);
?>
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Filters') ?></h3>
    </div>
    <div class="card-body">
        <form id="reportFilters" method="get" action="<?= $reportUrl ?>">
            <div class="row g-3 align-items-end">
                <?php foreach ($report['params'] as $key => $param) :
                    $id = InputUtils::escapeAttribute($key);
                    $value = $values[$key] ?? null;
                    $isMissing = in_array($key, $missing, true);
                    $invalid = $isMissing ? ' is-invalid' : '';
                    ?>
                <div class="<?= in_array($param['type'], ['classification', 'events'], true) ? 'col-md-6 col-lg-4' : 'col-md-4 col-lg-3' ?>">
                    <label class="form-label" for="<?= $id ?>"><?= InputUtils::escapeHTML($param['label']) ?></label>
                    <?php if ($param['type'] === 'number') : ?>
                    <input type="number" class="form-control" id="<?= $id ?>" name="<?= $id ?>"
                           min="<?= (int) $param['min'] ?>" max="<?= (int) $param['max'] ?>" value="<?= (int) $value ?>">
                    <?php elseif ($param['type'] === 'classification' || $param['type'] === 'events') : ?>
                    <select class="form-select<?= $invalid ?>" id="<?= $id ?>" name="<?= $id ?>[]" multiple
                            data-placeholder="<?= $param['type'] === 'classification' ? gettext('All classifications') : gettext('Choose events') ?>">
                        <?php foreach ($options[$key] as $optionId => $label) : ?>
                        <option value="<?= (int) $optionId ?>"<?= in_array((int) $optionId, (array) $value, true) ? ' selected' : '' ?>><?= InputUtils::escapeHTML($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else : ?>
                    <select class="form-select<?= $invalid ?>" id="<?= $id ?>" name="<?= $id ?>">
                        <?php if ($param['type'] !== 'month') : ?>
                        <option value=""><?= gettext('Choose...') ?></option>
                        <?php endif; ?>
                        <?php foreach ($options[$key] as $optionId => $label) : ?>
                        <option value="<?= (int) $optionId ?>"<?= (int) $optionId === (int) $value && $value !== null ? ' selected' : '' ?>><?= InputUtils::escapeHTML($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                    <?php if ($param['type'] === 'classification') : ?>
                    <div class="form-hint"><?= gettext('Leave empty for all classifications.') ?></div>
                    <?php elseif (!empty($param['help'])) : ?>
                    <div class="form-hint"><?= InputUtils::escapeHTML($param['help']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <div class="col-auto">
                    <button type="submit" id="runReport" class="btn btn-primary">
                        <i class="fa-solid fa-play me-1"></i><?= gettext('Run Report') ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($rows === null) : ?>
<div class="alert alert-info" id="reportMissing">
    <?= InputUtils::escapeHTML(sprintf(gettext('Choose %s to run this report.'), implode(', ', $missingLabels))) ?>
</div>
<?php else : ?>
<div class="card">
    <div class="card-header d-flex align-items-center flex-wrap gap-2">
        <h3 class="card-title mb-0">
            <?= gettext('Results') ?>
            <span id="resultCount" class="badge bg-primary text-white ms-2"><?= count($rows) ?></span>
        </h3>
        <div class="ms-auto btn-list">
            <button type="button" id="addAllToCart" class="btn btn-sm btn-outline-primary"<?= $rows === [] ? ' disabled' : '' ?>>
                <i class="fa-solid fa-cart-plus me-1"></i><?= gettext('Add All to Cart') ?>
            </button>
            <a id="downloadCsv" class="btn btn-sm btn-outline-secondary" href="<?= $reportUrl ?>/csv<?= $csvQuery !== '' ? '?' . InputUtils::escapeAttribute($csvQuery) : '' ?>">
                <i class="fa-solid fa-file-csv me-1"></i><?= gettext('Download CSV') ?>
            </a>
        </div>
    </div>
    <div style="overflow-x: clip; overflow-y: visible;">
        <table id="reportResults" class="table table-vcenter table-hover card-table">
            <thead>
                <tr>
                    <?php foreach ($report['columns'] as $label) : ?>
                    <th><?= InputUtils::escapeHTML($label) ?></th>
                    <?php endforeach; ?>
                    <th class="text-center no-export w-1"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows === []) : ?>
                <tr>
                    <td colspan="<?= count($report['columns']) + 1 ?>" class="text-secondary"><?= gettext('No people match these filters.') ?></td>
                </tr>
                <?php endif; ?>
                <?php foreach ($rows as $row) :
                    $personId = (int) $row['Id'];
                    $inCart = in_array($personId, $cart, false);
                    ?>
                <tr data-person-id="<?= $personId ?>">
                    <?php foreach (array_keys($report['columns']) as $column) : ?>
                    <?php if ($column === 'Name') : ?>
                    <td><a href="<?= $sRootPath ?>/people/view/<?= $personId ?>"><?= InputUtils::escapeHTML($row['Name']) ?></a></td>
                    <?php else : ?>
                    <td><?= InputUtils::escapeHTML((string) ($row[$column] ?? '')) ?></td>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <td class="w-1">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                <i class="fa-solid fa-ellipsis-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="<?= $sRootPath ?>/people/view/<?= $personId ?>">
                                    <i class="fa-solid fa-eye me-2"></i><?= gettext('View') ?>
                                </a>
                                <?php if ($canEdit) : ?>
                                <a class="dropdown-item" href="<?= $sRootPath ?>/PersonEditor.php?PersonID=<?= $personId ?>">
                                    <i class="fa-solid fa-pencil me-2"></i><?= gettext('Edit') ?>
                                </a>
                                <?php endif; ?>
                                <?php if (!empty($row['FamilyId'])) : ?>
                                <a class="dropdown-item" href="<?= $sRootPath ?>/people/family/<?= (int) $row['FamilyId'] ?>">
                                    <i class="fa-solid fa-users me-2"></i><?= gettext('View Family') ?>
                                </a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <button type="button"
                                        class="dropdown-item <?= $inCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
                                        data-cart-id="<?= $personId ?>"
                                        data-cart-type="person"
                                        data-label-add="<?= gettext('Add to Cart') ?>"
                                        data-label-remove="<?= gettext('Remove from Cart') ?>">
                                    <i class="<?= $inCart ? 'fa-solid fa-box-open' : 'fa-solid fa-cart-shopping' ?> me-2"></i>
                                    <span class="cart-label"><?= $inCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
                                </button>
                                <div class="dropdown-divider"></div>
                                <button type="button" class="dropdown-item text-danger delete-person"
                                        data-person_id="<?= $personId ?>"
                                        data-person_name="<?= InputUtils::escapeAttribute($row['Name']) ?>">
                                    <i class="fa-solid fa-trash me-2"></i><?= gettext('Delete') ?>
                                </button>
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

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function () {
        document.querySelectorAll('#reportFilters select[multiple]').forEach(function (select) {
            new window.TomSelect(select, {
                plugins: ['remove_button'],
                hideSelected: true,
                placeholder: select.dataset.placeholder
            });
        });

        $('#addAllToCart').on('click', function () {
            var ids = $('#reportResults tbody tr[data-person-id]').map(function () {
                return parseInt(this.dataset.personId, 10);
            }).get();
            if (ids.length > 0) {
                window.CRM.cartManager.addPerson(ids);
            }
        });
    });
</script>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
