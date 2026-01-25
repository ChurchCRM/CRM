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
    <div class="card-header bg-primary">
        <h3 class="card-title">
            <i class="fa-solid fa-images mr-2"></i>
            <?= gettext('Photo Directory') ?>
        </h3>
        <div class="card-tools">
            <span class="badge badge-light">
                <?= sprintf(ngettext('%d person', '%d people', $totalPeople), $totalPeople) ?>
            </span>
        </div>
    </div>
    
    <div class="card-body">
        <!-- Filter Controls -->
        <form method="GET" action="<?= $sRootPath ?>/v2/people/photos" class="mb-4">
            <div class="row">
                <!-- Classification Filter -->
                <div class="col-md-4 col-sm-6 mb-2">
                    <label for="classification">
                        <i class="fa-solid fa-filter mr-1"></i><?= gettext('Classification') ?>
                    </label>
                    <select name="classification" id="classification" class="form-control" onchange="this.form.submit()">
                        <option value=""><?= gettext('All Classifications') ?></option>
                        <?php foreach ($classifications as $cls): ?>
                            <option value="<?= $cls->getOptionId() ?>" 
                                <?= ($classificationFilter === $cls->getOptionId()) ? 'selected' : '' ?>>
                                <?= InputUtils::escapeHTML($cls->getOptionName()) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Photos Only Toggle -->
                <div class="col-md-4 col-sm-6 mb-2">
                    <label class="d-block">&nbsp;</label>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="photosOnly" 
                               name="photosOnly" value="1" 
                               <?= $showOnlyWithPhotos ? 'checked' : '' ?>
                               onchange="this.form.submit()">
                        <label class="custom-control-label" for="photosOnly">
                            <i class="fa-solid fa-camera mr-1"></i><?= gettext('Show only people with photos') ?>
                        </label>
                    </div>
                </div>
                
                <!-- Reset Button -->
                <div class="col-md-4 col-sm-12 mb-2">
                    <label class="d-block">&nbsp;</label>
                    <a href="<?= $sRootPath ?>/v2/people/photos" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-rotate-left mr-1"></i><?= gettext('Reset Filters') ?>
                    </a>
                </div>
            </div>
        </form>

        <!-- Photo Grid -->
        <?php if (empty($peopleData)): ?>
            <div class="alert alert-info">
                <i class="fa-solid fa-info-circle mr-2"></i>
                <?= gettext('No people found matching your criteria.') ?>
            </div>
        <?php else: ?>
            <div class="row" id="photo-grid">
                <?php foreach ($peopleData as $data): 
                    $person = $data['person'];
                    $hasPhoto = $data['hasPhoto'];
                ?>
                    <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-4">
                        <div class="card h-100 photo-card">
                            <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $person->getId() ?>" 
                               class="text-decoration-none">
                                <div class="photo-container text-center p-2">
                                    <?php if ($hasPhoto): ?>
                                        <img src="<?= $sRootPath ?>/api/person/<?= $person->getId() ?>/photo" 
                                             alt="<?= InputUtils::escapeAttribute($person->getFullName()) ?>"
                                             class="img-fluid rounded photo-thumbnail"
                                             loading="lazy">
                                    <?php else: ?>
                                        <!-- Avatar placeholder using initials -->
                                        <div class="avatar-placeholder rounded d-flex align-items-center justify-content-center"
                                             data-person-id="<?= $person->getId() ?>">
                                            <span class="initials-text">
                                                <?= InputUtils::escapeHTML(
                                                    mb_strtoupper(
                                                        mb_substr($person->getFirstName() ?? '', 0, 1) . 
                                                        mb_substr($person->getLastName() ?? '', 0, 1)
                                                    )
                                                ) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body p-2 text-center">
                                    <h6 class="card-title mb-0 text-dark">
                                        <?= InputUtils::escapeHTML($person->getFirstName()) ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?= InputUtils::escapeHTML($person->getLastName()) ?>
                                    </small>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Photo gallery pagination" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php 
                        $queryString = http_build_query(array_filter([
                            'classification' => $classificationFilter,
                            'photosOnly' => $showOnlyWithPhotos ? '1' : null,
                        ]));
                        $baseUrl = $sRootPath . '/v2/people/photos' . ($queryString ? '?' . $queryString . '&' : '?');
                        ?>
                        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>page=<?= $currentPage - 1 ?>">&laquo;</a>
                        </li>
                        <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                            <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $baseUrl ?>page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $baseUrl ?>page=<?= $currentPage + 1 ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
