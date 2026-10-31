<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\FiscalYearUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
$value = static fn (string $key): string => InputUtils::escapeAttribute(is_scalar($query[$key] ?? null) ? (string) $query[$key] : '');
$selected = is_array($query['classList']) ? array_filter($query['classList'], 'is_scalar') : [];
?>
<div class="card">
    <div class="card-body">
        <p><?= gettext('Use your church bylaws to choose the filters, then have authorized church leadership review the list. Matching these filters does not establish voting eligibility. This report does not record eligibility or conduct an election.') ?></p>
        <?php if ($error !== '') : ?><div class="alert alert-warning" role="alert" id="votingReportMessage"><?= InputUtils::escapeHTML($error) ?></div><?php endif; ?>
        <form method="post" action="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/reports/voting-members/download') ?>" id="votingMembersForm">
            <div class="mb-3">
                <label class="form-label" for="votingClasses"><?= gettext('Classifications to review') ?></label>
                <select multiple required class="form-select" name="classList[]" id="votingClasses" aria-describedby="votingClassHelp">
                    <?php foreach ($classifications as $option) : ?>
                        <option value="<?= (int) $option->getOptionId() ?>" <?= in_array((string) $option->getOptionId(), array_map('strval', $selected), true) ? 'selected' : '' ?>><?= InputUtils::escapeHTML($option->getOptionName()) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" id="votingClassHelp"><?= gettext('Choose the classifications your church uses. No classification is assumed to confer voting rights.') ?></div>
            </div>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label" for="votingAsOf"><?= gettext('Review date') ?></label>
                    <input type="date" required min="1900-01-01" max="2200-12-31" class="form-control" name="AsOf" id="votingAsOf" value="<?= $value('AsOf') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="votingAge"><?= gettext('Minimum age (0–120)') ?></label>
                    <input type="number" required min="0" max="120" class="form-control" name="MinimumAge" id="votingAge" value="<?= $value('MinimumAge') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="votingMonths"><?= gettext('Minimum membership duration in months (0–1200)') ?></label>
                    <input type="number" required min="0" max="1200" class="form-control" name="MembershipMonths" id="votingMonths" value="<?= $value('MembershipMonths') ?>">
                </div>
            </div>
            <p class="text-body-secondary"><?= gettext('Zero disables an optional requirement. Age requires a complete birth date; membership duration requires a membership date. People missing required dates are omitted and counted separately. Deceased people, inactive people and classifications, and inactive or pending-review families and people are always excluded; current classification and status are used, not historical status.') ?></p>
            <details class="mb-3" <?= (int) (is_scalar($query['RequireDonationYears']) ? $query['RequireDonationYears'] : 0) > 0 ? 'open' : '' ?>>
                <summary><?= gettext('Optional family giving requirement') ?></summary>
                <p class="form-text"><?= gettext('Giving is recorded by family, not individual. Enable this only when your bylaws allow a family payment to satisfy this criterion. This does not prove that each person donated. The window includes the selected fiscal year and ends no later than the review date.') ?></p>
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label" for="votingYears"><?= gettext('Fiscal years to check (0–100; 0 means no giving requirement)') ?></label>
                        <input type="number" required min="0" max="100" class="form-control" name="RequireDonationYears" id="votingYears" value="<?= $value('RequireDonationYears') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="FYID"><?= gettext('Last fiscal year to include') ?></label>
                        <select class="form-select" name="FYID" id="FYID">
                            <?php foreach (FiscalYearUtils::buildFiscalYearList(1) as $year) : ?>
                                <option value="<?= (int) $year['id'] ?>" <?= (string) $year['id'] === (is_scalar($query['FYID']) ? (string) $query['FYID'] : '') ? 'selected' : '' ?>><?= InputUtils::escapeHTML($year['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </details>
            <p class="text-body-secondary"><?= gettext('Baptism, electoral-roll applications, residence, communicant status, attendance, good standing, and local exceptions must be checked separately where your bylaws require them. Membership duration is not proof of attendance.') ?></p>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit" id="createReport"><?= gettext('Download Review List') ?></button>
                <a class="btn btn-secondary" href="<?= InputUtils::escapeAttribute(SystemURLs::getRootPath() . '/finance/reports') ?>"><?= gettext('Financial Reports') ?></a>
            </div>
        </form>
    </div>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
