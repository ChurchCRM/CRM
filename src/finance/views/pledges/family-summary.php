<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="container-fluid">
    <!-- Page Title -->
    <div class="row mb-4">
        <div class="col-12">
            <h2><?= gettext('Pledge Dashboard') ?></h2>
            <p class="text-muted small">
                <?= gettext('Track and manage pledge commitments by family and fund') ?>
            </p>
        </div>
    </div>

    <!-- Fiscal Year Selector -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="form-inline">
                        <label for="fyid" class="mr-2"><?= gettext('Select Fiscal Year') ?>:</label>
                        <select name="fyid" id="fyid" class="form-control mr-3" onchange="this.form.submit();">
                            <option value="">-- <?= gettext('select an option') ?> --</option>
                            <?php foreach ($availableYears as $year): ?>
                                <option value="<?= $year['id'] ?>" <?= $year['id'] == $selectedFyid ? 'selected' : '' ?>>
                                    <?= InputUtils::escapeHTML($year['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <div class="row">
        <div class="col-12">
            <?php if (empty($familyPledges)): ?>
                <div class="alert alert-info">
                    <i class="fa-solid fa-info-circle"></i>
                    <?= gettext('No pledges found for the selected fiscal year') ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <?= gettext('Family Pledges') ?> 
                            <span class="badge badge-primary ml-2"><?= count($familyPledges) ?> <?= gettext('Families') ?></span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th><?= gettext('Family Name') ?></th>
                                        <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                                        <th><?= gettext('Envelope') ?></th>
                                        <?php endif; ?>
                                        <th><?= gettext('Fund Name') ?></th>
                                        <th class="text-right"><?= gettext('Pledge Amount') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($familyPledges as $family): ?>
                                        <?php $isFirstRow = true; ?>
                                        <?php foreach ($family['pledges'] as $pledge): ?>
                                            <tr>
                                                <?php if ($isFirstRow): ?>
                                                    <td rowspan="<?= count($family['pledges']) ?>" class="align-middle font-weight-bold">
                                                        <?= InputUtils::escapeHTML($family['family_name']) ?>
                                                    </td>
                                                    <?php if (SystemConfig::getBooleanValue('bUseDonationEnvelopes')): ?>
                                                    <td rowspan="<?= count($family['pledges']) ?>" class="align-middle">
                                                        <?= InputUtils::escapeHTML($family['envelope'] ?? '') ?>
                                                    </td>
                                                    <?php endif; ?>
                                                    <?php $isFirstRow = false; ?>
                                                <?php endif; ?>
                                                <td><?= InputUtils::escapeHTML($pledge['fund_name']) ?></td>
                                                <td class="text-right">
                                                    <?= number_format($pledge['pledge_amount'], 2) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
