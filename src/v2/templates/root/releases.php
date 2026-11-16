<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$releases = array_reverse($preview['upgradePath'] ?? []); // Same newest-first order as the wizard.
?>
<div class="card" id="releases-page">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('What\'s New') ?></h3>
    </div>
    <div class="card-body">
        <p class="text-secondary">
            <?= sprintf(gettext('Installed version: %s'), InputUtils::escapeHTML($preview['installedVersion'])) ?>
        </p>
        <?php if (!empty($preview['unavailable'])): ?>
            <div class="alert alert-warning" id="releases-unavailable">
                <?= gettext('Release notes are unavailable right now.') ?>
            </div>
        <?php elseif (empty($releases)): ?>
            <div class="alert alert-success" id="releases-up-to-date">
                <?= gettext('You\'re up to date.') ?>
            </div>
        <?php else: ?>
            <p><?= sprintf(gettext('%d releases available since your installed version.'), count($releases)) ?></p>
            <div id="releases-list">
            <?php foreach ($releases as $release): ?>
                <section class="version-notes-block mb-3" data-release-version="<?= InputUtils::escapeAttribute($release['version']) ?>">
                    <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-1">
                        <h5 class="mb-0"><?= InputUtils::escapeHTML($release['version']) ?></h5>
                        <?php if (filter_var($release['changelogUrl'] ?? '', FILTER_VALIDATE_URL)
                            && in_array(strtolower(parse_url($release['changelogUrl'], PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)): ?>
                            <a class="btn btn-ghost-secondary btn-sm flex-shrink-0"
                               href="<?= InputUtils::escapeAttribute($release['changelogUrl']) ?>"
                               target="_blank" rel="noopener noreferrer">
                                <?= gettext('Full release notes') ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <div class="release-notes release-notes-plain p-3 border rounded"><?= InputUtils::escapeHTML($release['notes'] ?? '') ?></div>
                </section>
            <?php endforeach; ?>
            </div>
            <p class="text-secondary"><?= gettext('Ask your system administrator to upgrade ChurchCRM to the latest version.') ?></p>
        <?php endif; ?>
    </div>
</div>
<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
