<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$groupIcons = [
    'Persons'          => 'ti-user',
    'Families'         => 'ti-home',
    'Groups'           => 'ti-users-group',
    'Addresses'        => 'ti-map-pin',
    'Finance Deposits' => 'ti-building-bank',
    'Finance Payments' => 'ti-credit-card',
    'Calendar Events'  => 'ti-calendar',
];
?>

<form method="get" action="<?= SystemURLs::getRootPath() ?>/v2/search" class="mb-4">
  <div class="input-icon" style="max-width: 600px;">
    <span class="input-icon-addon">
      <i class="ti ti-search"></i>
    </span>
    <input type="search" name="q" class="form-control form-control-lg"
           value="<?= InputUtils::escapeHTML($query) ?>"
           placeholder="<?= gettext('Search people, families, groups…') ?>"
           autocomplete="off">
  </div>
</form>

<?php if ($query === '') : ?>

  <div class="empty">
    <div class="empty-icon">
      <i class="ti ti-search" style="font-size: 3rem; color: var(--tblr-secondary);"></i>
    </div>
    <p class="empty-title"><?= gettext('Enter a search term') ?></p>
    <p class="empty-subtitle text-secondary">
      <?= gettext('Search across people, families, groups, addresses, and more.') ?>
    </p>
  </div>

<?php elseif (empty($groups)) : ?>

  <div class="empty">
    <div class="empty-icon">
      <i class="ti ti-zoom-question" style="font-size: 3rem; color: var(--tblr-secondary);"></i>
    </div>
    <p class="empty-title"><?= gettext('No results found') ?></p>
    <p class="empty-subtitle text-secondary">
      <?= sprintf(gettext('No matches for "%s". Try a different search term.'), InputUtils::escapeHTML($query)) ?>
    </p>
  </div>

<?php else : ?>

  <p class="text-secondary mb-3">
    <?= sprintf(
        ngettext('%d result for "%s"', '%d results for "%s"', $totalResults),
        $totalResults,
        InputUtils::escapeHTML($query)
    ) ?>
  </p>

  <div class="row g-3">
    <?php foreach ($groups as $group) : ?>
      <?php
      /** @var \ChurchCRM\Search\SearchResultGroup $group */
      // groupName from BaseSearchResultProvider is "Persons (5)" — strip the count suffix
      $displayName = (string) preg_replace('/\s*\(\d+\)$/', '', $group->groupName);
      $icon        = $groupIcons[$displayName] ?? 'ti-search';
      $count       = count($group->results);
      ?>
      <div class="col-12">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title d-flex align-items-center">
              <span class="avatar avatar-sm rounded me-3 text-white"
                    style="background-color: var(--tblr-primary); flex-shrink: 0;">
                <i class="ti <?= $icon ?>"></i>
              </span>
              <?= InputUtils::escapeHTML($displayName) ?>
              <span class="badge bg-blue-lt text-blue ms-2"><?= $count ?></span>
            </h3>
          </div>
          <div class="list-group list-group-flush">
            <?php foreach ($group->results as $result) : ?>
              <a href="<?= InputUtils::escapeHTML($result->uri) ?>"
                 class="list-group-item list-group-item-action d-flex align-items-center py-3">
                <span><?= InputUtils::escapeHTML($result->text) ?></span>
                <i class="ti ti-chevron-right ms-auto text-secondary"></i>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
