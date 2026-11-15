<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * @var int[]                                         $windows
 * @var int                                           $days
 * @var \ChurchCRM\model\ChurchCRM\ImportantDateType[] $types
 * @var int                                           $typeId
 * @var bool                                          $hasAnyType
 * @var bool                                          $isAdmin
 * @var list<array<string, mixed>>                    $rows
 */

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$windowLabels = [
    7  => gettext('Next 7 days'),
    30 => gettext('Next 30 days'),
    90 => gettext('Next 90 days'),
];
?>

<div class="container-fluid" id="important-dates-upcoming">
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="<?= $sRootPath ?>/people/important-dates" class="row g-2 align-items-end" id="important-dates-filter">
                <div class="col-sm-5 col-md-4 col-lg-3">
                    <label class="form-label" for="important-dates-days"><?= gettext('Coming up') ?></label>
                    <select class="form-select" id="important-dates-days" name="days">
                        <?php foreach ($windows as $window) : ?>
                            <option value="<?= $window ?>"<?= $window === $days ? ' selected' : '' ?>><?= InputUtils::escapeHTML($windowLabels[$window]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-5 col-md-4 col-lg-3">
                    <label class="form-label" for="important-dates-type-filter"><?= gettext('Date type') ?></label>
                    <select class="form-select" id="important-dates-type-filter" name="type">
                        <option value="0"><?= gettext('All date types') ?></option>
                        <?php foreach ($types as $type) : ?>
                            <option value="<?= (int) $type->getId() ?>"<?= (int) $type->getId() === $typeId ? ' selected' : '' ?>><?= InputUtils::escapeHTML($type->getName()) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-2 col-md-auto">
                    <button type="submit" class="btn btn-primary w-100"><i class="fa-solid fa-filter me-1"></i><?= gettext('Show') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <?php if (!$hasAnyType) : ?>
            <div class="card-body text-center text-body-secondary py-4" id="important-dates-no-types">
                <i class="fa-solid fa-calendar-day fa-2x mb-2 d-block opacity-50"></i>
                <?php if ($isAdmin) : ?>
                    <p class="mb-2"><?= gettext('No important date types are set up yet.') ?></p>
                    <a class="btn btn-primary" href="<?= $sRootPath ?>/admin/people/important-dates"><i class="fa-solid fa-sliders me-1"></i><?= gettext('Set up date types') ?></a>
                <?php else : ?>
                    <p class="mb-0"><?= gettext('No important date types are set up yet. Ask an administrator to add them.') ?></p>
                <?php endif; ?>
            </div>
        <?php elseif (count($rows) === 0) : ?>
            <div class="card-body text-center text-body-secondary py-4" id="important-dates-empty">
                <i class="fa-solid fa-calendar-check fa-2x mb-2 d-block opacity-50"></i>
                <p class="mb-0"><?= InputUtils::escapeHTML(sprintf(gettext('No important dates in the %s.'), mb_strtolower($windowLabels[$days]))) ?></p>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-vcenter card-table" id="important-dates-table">
                    <thead>
                        <tr>
                            <th><?= gettext('Date') ?></th>
                            <th><?= gettext('Type') ?></th>
                            <th><?= gettext('Name') ?></th>
                            <th><?= gettext('Years') ?></th>
                            <th><?= gettext('Note') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) :
                            $badge = 'bg-secondary-lt text-secondary';
                            if ($row['daysUntil'] === 0) {
                                $badge = 'bg-success-lt text-success';
                            } elseif ($row['daysUntil'] <= 7) {
                                $badge = 'bg-primary-lt text-primary';
                            } ?>
                            <tr data-type-id="<?= (int) $row['typeId'] ?>">
                                <td class="text-nowrap">
                                    <div><?= InputUtils::escapeHTML($row['observedLabel']) ?></div>
                                    <span class="badge <?= $badge ?>"><?= InputUtils::escapeHTML($row['relative']) ?></span>
                                </td>
                                <td>
                                    <?= InputUtils::escapeHTML($row['type']) ?>
                                    <div class="small text-body-secondary"><?= InputUtils::escapeHTML(sprintf(gettext('Recorded %s'), $row['recorded'])) ?></div>
                                </td>
                                <td>
                                    <i class="fa-solid <?= $row['ownerKind'] === 'family' ? 'fa-people-roof' : 'fa-user' ?> text-body-secondary me-1"></i>
                                    <a href="<?= InputUtils::escapeAttribute($row['ownerUrl']) ?>"><?= InputUtils::escapeHTML($row['ownerName']) ?></a>
                                </td>
                                <td><?= InputUtils::escapeHTML($row['yearsLabel'] ?? '') ?></td>
                                <td class="text-body-secondary"><?= InputUtils::escapeHTML($row['note'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
