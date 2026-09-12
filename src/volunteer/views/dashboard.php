<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * The coordinator landing page.
 *
 * #9711 replaces the body of this view with the real S1 dashboard (gaps,
 * pending responses, proposed swaps, upcoming occurrences — design §5.2), all of
 * which need #9708/#9709. Until then it does the one useful thing it can: list
 * the ministries this person coordinates and open the guided setup.
 *
 * No queries here — the route prepared `$aMinistries`
 * (groups-mvc-guidelines.md).
 */

/** @var string $sRootPath */
/** @var array<int, array{id: int, name: string, description: ?string, active: bool, teamCount: int, positionCount: int}> $aMinistries */
/** @var bool $bIsManager */

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
$aMinistries = $aMinistries ?? [];
?>
<?php require SystemURLs::getDocumentRoot() . '/Include/Header.php'; ?>

<div class="row">
  <div class="col-12 col-xl-8">
    <div class="card">
      <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <h3 class="card-title mb-0">
          <i class="fa-solid fa-handshake-angle me-2"></i><?= gettext('My ministries') ?>
        </h3>
        <a href="<?= $sRootPath ?>/volunteer/setup" class="btn btn-primary btn-sm">
          <i class="fa-solid fa-wand-magic-sparkles me-1"></i><?= gettext('Guided setup') ?>
        </a>
      </div>
      <div class="card-body">
        <?php if (count($aMinistries) > 0): ?>
          <div class="list-group list-group-flush">
            <?php foreach ($aMinistries as $aMinistry): ?>
              <a class="list-group-item list-group-item-action"
                 href="<?= $sRootPath ?>/volunteer/ministries/<?= (int) $aMinistry['id'] ?>">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                  <div>
                    <span class="fw-bold"><?= InputUtils::escapeHTML($aMinistry['name']) ?></span>
                    <?php if (!$aMinistry['active']): ?>
                      <span class="badge bg-secondary-lt ms-2"><?= gettext('Inactive') ?></span>
                    <?php endif; ?>
                    <?php if (!empty($aMinistry['description'])): ?>
                      <div class="text-body-secondary small"><?= InputUtils::escapeHTML($aMinistry['description']) ?></div>
                    <?php endif; ?>
                  </div>
                  <div class="text-nowrap">
                    <span class="badge bg-blue-lt me-1">
                      <?= sprintf(ngettext('%d team', '%d teams', (int) $aMinistry['teamCount']), (int) $aMinistry['teamCount']) ?>
                    </span>
                    <span class="badge bg-azure-lt">
                      <?= sprintf(ngettext('%d position', '%d positions', (int) $aMinistry['positionCount']), (int) $aMinistry['positionCount']) ?>
                    </span>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty">
            <div class="empty-icon"><i class="fa-solid fa-handshake-angle fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('Nothing set up yet') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?php if ($bIsManager): ?>
                <?= gettext('Start by creating a ministry, then add the teams and positions that serve in it.') ?>
              <?php else: ?>
                <?= gettext('Ask a volunteer manager to create a ministry and make you its coordinator.') ?>
              <?php endif; ?>
            </p>
            <?php if ($bIsManager): ?>
              <div class="empty-action">
                <a href="<?= $sRootPath ?>/volunteer/setup" class="btn btn-primary">
                  <i class="fa-solid fa-plus me-1"></i><?= gettext('Start the guided setup') ?>
                </a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-xl-4">
    <div class="card">
      <div class="card-header">
        <h3 class="card-title"><?= gettext('Coming next') ?></h3>
      </div>
      <div class="card-body">
        <p class="mb-0 text-body-secondary">
          <?= gettext('Volunteer pools, qualifications, recurring schedules and assignments arrive in later releases. Setting up your ministries, teams and positions now means there is nothing to redo when they land.') ?>
        </p>
      </div>
    </div>
  </div>
</div>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
