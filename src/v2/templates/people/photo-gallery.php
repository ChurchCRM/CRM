<?php
/**
 * Photo Gallery Template - Displays medium-sized photos of all congregation members.
 *
 * This template renders a responsive grid of person photos with names,
 * allowing users to quickly identify church members visually.
 *
 * Feature request: https://github.com/ChurchCRM/CRM/issues/7899
 *
 * Variables passed from route:
 * @var string $sRootPath - Root path for URLs
 * @var string $sPageTitle - Page title
 * @var array $peopleData - Array of ['person' => Person, 'hasPhoto' => bool]
 * @var \Propel\Runtime\Collection\ObjectCollection $classifications - Classification options
 * @var bool $showOnlyWithPhotos - Filter toggle
 * @var int|null $classificationFilter - Active classification filter
 * @var int $totalPeople - Total people displayed
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="ti ti-users me-2"></i>
            <?= gettext('Photo Directory') ?>
        </h3>
        <div class="card-options ms-auto">
            <span class="badge bg-primary-lt text-primary fs-6 px-3 py-2">
                <?= sprintf(ngettext('%d person', '%d people', $totalPeople), $totalPeople) ?>
            </span>
        </div>
    </div>

    <div class="card-body border-bottom py-3">
        <!-- Filter Controls -->
        <div class="row g-2 align-items-center">
            <!-- Classification Filter -->
            <div class="col-12 col-sm">
                <select id="classification-select" class="form-select">
                    <option value=""><?= gettext('All Classifications') ?></option>
                    <?php foreach ($classifications as $cls): ?>
                        <option value="<?= $cls->getOptionId() ?>"
                            <?= ($classificationFilter === $cls->getOptionId()) ? 'selected' : '' ?>>
                            <?= InputUtils::escapeHTML($cls->getOptionName()) ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="-1" <?= $filterUnassigned ? 'selected' : '' ?>>
                        <?= gettext('Unassigned') ?>
                    </option>
                </select>
            </div>

            <!-- Photos Only Toggle -->
            <div class="col-auto">
                <div class="form-check form-switch mb-0">
                    <input type="checkbox" class="form-check-input" id="photosOnly-toggle"
                           <?= $showOnlyWithPhotos ? 'checked' : '' ?>>
                    <label class="form-check-label text-nowrap" for="photosOnly-toggle">
                        <i class="ti ti-camera me-1"></i><?= gettext('Photos only') ?>
                    </label>
                </div>
            </div>

            <!-- Per-page selector -->
            <div class="col-auto">
                <select id="perpage-select" class="form-select" style="width:auto">
                    <?php foreach ($allowedLimits as $lim): ?>
                        <option value="<?= $lim ?>" <?= ($perPage === $lim) ? 'selected' : '' ?>>
                            <?= $lim ?> / <?= gettext('page') ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="0" <?= ($perPage === 0) ? 'selected' : '' ?>>
                        <?= gettext('All') ?>
                    </option>
                </select>
            </div>

            <!-- Reset -->
            <div class="col-auto">
                <a href="<?= $sRootPath ?>/v2/people/photos" class="btn btn-outline-secondary">
                    <i class="ti ti-refresh"></i>
                    <span class="d-none d-sm-inline ms-1"><?= gettext('Reset') ?></span>
                </a>
            </div>
        </div>

        <script>
        (function () {
            var base = '<?= $sRootPath ?>/v2/people/photos';

            function applyFilters() {
                var cls       = document.getElementById('classification-select').value;
                var photosOnly = document.getElementById('photosOnly-toggle').checked ? '1' : '0';
                var perPage   = document.getElementById('perpage-select').value;
                var params    = new URLSearchParams();
                if (cls) params.set('classification', cls);
                params.set('photosOnly', photosOnly);
                params.set('perPage', perPage);
                window.location.href = base + '?' + params.toString();
            }

            document.getElementById('classification-select').addEventListener('change', applyFilters);
            document.getElementById('photosOnly-toggle').addEventListener('change', applyFilters);
            document.getElementById('perpage-select').addEventListener('change', applyFilters);
        })();
        </script>
    </div>

    <div class="card-body">
        <?php if (empty($peopleData)): ?>
            <div class="empty">
                <div class="empty-icon">
                    <i class="ti ti-camera-off fs-1 text-muted"></i>
                </div>
                <p class="empty-title"><?= gettext('No people found') ?></p>
                <p class="empty-subtitle text-muted">
                    <?= gettext('No people match your current filters.') ?>
                </p>
                <div class="empty-action">
                    <a href="<?= $sRootPath ?>/v2/people/photos" class="btn btn-primary">
                        <i class="ti ti-refresh me-1"></i><?= gettext('Reset Filters') ?>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row row-cards" id="photo-grid">
                <?php foreach ($peopleData as $data):
                    $person    = $data['person'];
                    $hasPhoto  = $data['hasPhoto'];
                    $cellPhone = $person->getCellPhone();
                    $phone     = $cellPhone ?: $person->getHomePhone();
                    $email    = $person->getEmail();
                    $initials = mb_strtoupper(
                        mb_substr($person->getFirstName() ?? '', 0, 1) .
                        mb_substr($person->getLastName() ?? '', 0, 1)
                    );
                ?>
                    <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                        <div class="card card-sm">
                            <div class="card-body p-3 text-center">

                                <!-- Avatar -->
                                <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $person->getId() ?>"
                                   class="d-block mb-3">
                                    <?php if ($hasPhoto): ?>
                                        <span class="avatar avatar-xl rounded"
                                              style="background-image: url('<?= $sRootPath ?>/api/person/<?= $person->getId() ?>/photo');"
                                              aria-label="<?= InputUtils::escapeAttribute($person->getFullName()) ?>"></span>
                                    <?php else: ?>
                                        <span class="avatar avatar-xl rounded avatar-placeholder">
                                            <?= InputUtils::escapeHTML($initials) ?>
                                        </span>
                                    <?php endif; ?>
                                </a>

                                <!-- Name -->
                                <div class="fw-bold lh-sm">
                                    <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $person->getId() ?>"
                                       class="text-reset">
                                        <?= InputUtils::escapeHTML($person->getFirstName()) ?>
                                        <?= InputUtils::escapeHTML($person->getLastName()) ?>
                                    </a>
                                </div>
                                <div class="text-muted small mt-1">
                                    <?php if ($person->getClsId() && isset($classificationMap[$person->getClsId()])): ?>
                                        <?= InputUtils::escapeHTML($classificationMap[$person->getClsId()]) ?>
                                    <?php else: ?>
                                        <em><?= gettext('Not Classified') ?></em>
                                    <?php endif; ?>
                                </div>

                                <!-- Action Icons — always show all 3, disabled when no data -->
                                <div class="mt-3 d-flex gap-2 justify-content-center">
                                    <?php if ($phone): ?>
                                        <a href="tel:<?= InputUtils::escapeAttribute(preg_replace('/\D/', '', $phone)) ?>"
                                           class="btn btn-sm btn-icon btn-outline-success"
                                           title="<?= gettext('Call') ?>: <?= InputUtils::escapeAttribute($phone) ?>">
                                            <i class="ti ti-phone"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-sm btn-icon btn-outline-secondary disabled"
                                              title="<?= gettext('No phone number on file') ?>">
                                            <i class="ti ti-phone"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($cellPhone): ?>
                                        <a href="sms:<?= InputUtils::escapeAttribute(preg_replace('/[^\d+]/', '', $cellPhone)) ?>"
                                           class="btn btn-sm btn-icon btn-outline-secondary"
                                           title="<?= gettext('Send text message') ?>">
                                            <i class="ti ti-message"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-sm btn-icon btn-outline-secondary disabled"
                                              title="<?= gettext('No cell phone on file') ?>">
                                            <i class="ti ti-message"></i>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($email): ?>
                                        <a href="mailto:<?= InputUtils::escapeAttribute($email) ?>"
                                           class="btn btn-sm btn-icon btn-outline-primary"
                                           title="<?= gettext('Email') ?>: <?= InputUtils::escapeAttribute($email) ?>"
                                           target="_blank" rel="noopener noreferrer">
                                            <i class="ti ti-mail"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-sm btn-icon btn-outline-secondary disabled"
                                              title="<?= gettext('No email address on file') ?>">
                                            <i class="ti ti-mail"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="mt-4">
                    <?php
                    $paginationParams = ['photosOnly' => $showOnlyWithPhotos ? '1' : '0', 'perPage' => $perPage];
                    if ($filterUnassigned) {
                        $paginationParams['classification'] = '-1';
                    } elseif ($classificationFilter !== null) {
                        $paginationParams['classification'] = $classificationFilter;
                    }
                    $queryString = http_build_query($paginationParams);
                    $baseUrl = $sRootPath . '/v2/people/photos' . ($queryString ? '?' . $queryString . '&' : '?');
                    ?>
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>page=<?= $currentPage - 1 ?>">
                                <i class="ti ti-chevron-left"></i>
                                <span class="d-none d-sm-inline ms-1"><?= gettext('Prev') ?></span>
                            </a>
                        </li>
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>page=<?= $currentPage + 1 ?>">
                                <span class="d-none d-sm-inline me-1"><?= gettext('Next') ?></span>
                                <i class="ti ti-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
