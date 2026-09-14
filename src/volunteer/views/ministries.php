<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * "My ministries and teams" (#9711) — the module index and, for a team leader, the
 * only entry point into it (see the route docblock).
 *
 * No queries here — the route prepared `$aMinistries` and `$aTeams`
 * (groups-mvc-guidelines.md). Server-rendered, so there is no loading state to show;
 * the §5.8 state that does apply is the empty one, and it is first-class.
 *
 * A team's parent ministry is a **link only when the caller coordinates it**. For a
 * team leader it is plain text: §4.6 gives them their team, not the ministry, and a
 * link the server would refuse is worse than no link.
 */

/** @var string $sRootPath */
/** @var array<int, array{id:int,name:string,description:?string,active:bool,teamCount:int,positionCount:int}> $aMinistries */
/** @var array<int, array{id:int,name:string,description:?string,active:bool,ministryId:int,ministryName:?string,ministryManageable:bool}> $aTeams */
/** @var bool $bIsManager */

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();
$aMinistries = $aMinistries ?? [];
$aTeams = $aTeams ?? [];
?>
<?php require SystemURLs::getDocumentRoot() . '/Include/Header.php'; ?>

<div class="row g-3">
  <div class="col-12 col-xl-8">
    <div class="card">
      <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
        <h3 class="card-title mb-0">
          <i class="fa-solid fa-handshake-angle me-2"></i><?= gettext('Ministries I coordinate') ?>
        </h3>
        <?php if ($bIsManager): ?>
          <button type="button" class="btn btn-primary btn-sm" id="ministry-new-btn">
            <i class="fa-solid fa-plus me-1"></i><?= gettext('New ministry') ?>
          </button>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <?php if (count($aMinistries) > 0): ?>
          <div class="list-group list-group-flush" id="volunteer-ministries-list">
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
          <div class="empty" id="volunteer-ministries-empty">
            <div class="empty-icon"><i class="fa-solid fa-handshake-angle fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('You do not coordinate a ministry') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?php if ($bIsManager): ?>
                <?= gettext('Start by creating a ministry, then add the teams and positions that serve in it.') ?>
              <?php elseif (count($aTeams) > 0): ?>
                <?= gettext('You lead a team instead. Your team is listed beside this.') ?>
              <?php else: ?>
                <?= gettext('Ask a volunteer manager to create a ministry and make you its coordinator.') ?>
              <?php endif; ?>
            </p>
            <?php if ($bIsManager): ?>
              <div class="empty-action">
                <button type="button" class="btn btn-primary" id="ministry-new-empty-btn">
                  <i class="fa-solid fa-plus me-1"></i><?= gettext('New ministry') ?>
                </button>
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
        <h3 class="card-title mb-0">
          <i class="fa-solid fa-people-group me-2"></i><?= gettext('Teams I lead') ?>
        </h3>
      </div>
      <div class="card-body">
        <?php if (count($aTeams) > 0): ?>
          <div class="list-group list-group-flush" id="volunteer-teams-list">
            <?php foreach ($aTeams as $aTeam): ?>
              <div class="list-group-item">
                <span class="fw-bold"><?= InputUtils::escapeHTML($aTeam['name']) ?></span>
                <?php if (!$aTeam['active']): ?>
                  <span class="badge bg-secondary-lt ms-2"><?= gettext('Inactive') ?></span>
                <?php endif; ?>
                <div class="text-body-secondary small">
                  <?php if ($aTeam['ministryManageable']): ?>
                    <a href="<?= $sRootPath ?>/volunteer/ministries/<?= (int) $aTeam['ministryId'] ?>">
                      <?= InputUtils::escapeHTML($aTeam['ministryName'] ?? '') ?>
                    </a>
                  <?php else: ?>
                    <?= InputUtils::escapeHTML($aTeam['ministryName'] ?? '') ?>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty" id="volunteer-teams-empty">
            <div class="empty-icon"><i class="fa-solid fa-people-group fa-2x text-muted"></i></div>
            <p class="empty-title"><?= gettext('No teams yet') ?></p>
            <p class="empty-subtitle text-body-secondary">
              <?= gettext('Teams appear here once a ministry you coordinate has one, or once you are made a team leader.') ?>
            </p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-body">
        <a href="<?= $sRootPath ?>/volunteer/dashboard" class="btn btn-outline-primary w-100">
          <i class="fa-solid fa-gauge me-1"></i><?= gettext('Back to the dashboard') ?>
        </a>
      </div>
    </div>
  </div>
</div>

<?php if ($bIsManager): ?>
  <?php require __DIR__ . '/partials/ministry-create-modal.php'; ?>
<?php endif; ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= SystemURLs::assetVersioned('/skin/v2/volunteer-ministries.min.js') ?>"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
