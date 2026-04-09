<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Stat Cards Row -->
<div class="row mb-3">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="fa-solid fa-chalkboard icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $classes ?></div>
                        <div class="text-muted"><?= gettext('Classes') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-person-chalkboard icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $teachers ?></div>
                        <div class="text-muted"><?= gettext('Teachers') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-children icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $kids ?></div>
                        <div class="text-muted"><?= gettext('Students') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-solid fa-people-roof icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $families ?></div>
                        <div class="text-muted"><?= gettext('Families') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-azure text-white avatar rounded-circle">
                            <i class="fa-solid fa-child icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $maleKids ?></div>
                        <div class="text-muted"><?= gettext('Boys') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-danger text-white avatar rounded-circle">
                            <i class="fa-solid fa-child-dress icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $femaleKids ?></div>
                        <div class="text-muted"><?= gettext('Girls') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- How Sunday School works (collapsible explainer) -->
<div class="card mb-3" id="ssExplainerCard">
    <div class="card-status-top bg-blue"></div>
    <div class="card-header">
        <h3 class="card-title">
            <i class="ti ti-info-circle me-2 text-blue"></i><?= gettext('How Sunday School + Events + Kiosks fit together') ?>
        </h3>
        <button class="btn btn-sm btn-ghost-secondary ms-auto" type="button"
                data-bs-toggle="collapse" data-bs-target="#ssExplainerBody"
                aria-expanded="false" aria-controls="ssExplainerBody">
            <i class="ti ti-chevron-down"></i>
        </button>
    </div>
    <div class="collapse" id="ssExplainerBody">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-secondary fw-bold mb-1">
                        <i class="ti ti-users me-1 text-blue"></i><?= gettext('1. Sunday School Class') ?>
                    </div>
                    <div class="small text-muted">
                        <?= gettext("A class is a Group with type \"Sunday School\". It holds the students and teachers (members with roles).") ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-secondary fw-bold mb-1">
                        <i class="ti ti-tags me-1 text-orange"></i><?= gettext('2. Event Type') ?>
                    </div>
                    <div class="small text-muted">
                        <?= gettext('An Event Type is a template — name, default start time, recurrence, attendance count categories. Optionally linked to one class group as its default audience.') ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-secondary fw-bold mb-1">
                        <i class="ti ti-calendar me-1 text-green"></i><?= gettext('3. Event') ?>
                    </div>
                    <div class="small text-muted">
                        <?= gettext("A specific occurrence (e.g. \"Preschool — Apr 12\"). Inherits defaults from the type and is linked to the class group via the audience. This is what volunteers take attendance against.") ?>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-secondary fw-bold mb-1">
                        <i class="ti ti-device-ipad me-1 text-purple"></i><?= gettext('4. Kiosk') ?>
                    </div>
                    <div class="small text-muted">
                        <?= gettext('A tablet assigned to one event. The kiosk pulls the event\'s linked group roster and shows tap-to-check-in. Without a linked group the kiosk has no roster to display.') ?>
                    </div>
                </div>
            </div>
            <hr class="my-3">
            <div class="alert alert-info mb-0">
                <i class="ti ti-rocket me-1"></i>
                <strong><?= gettext('Fastest workflow:') ?></strong>
                <?= gettext("Open a class → click \"Create Today's Event\" — the event is created and linked to the class in one shot, then you land on the check-in page ready for a kiosk or walk-in attendance.") ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-bolt me-2"></i><?= gettext('Quick Actions') ?></h3>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap" style="gap:.5rem;">
            <?php if ($canManageGroups) { ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-class">
                    <i class="fa-solid fa-plus me-1"></i><?= gettext('Add New Class') ?>
                </button>
            <?php } ?>
            <a href="<?= $sRootPath ?>/groups/sundayschool/reports" class="btn btn-outline-primary">
                <i class="fa-solid fa-file-pdf me-1"></i><?= gettext('Reports') ?>
            </a>
            <a href="<?= $sRootPath ?>/api/groups/sundayschool/export/classlist" class="btn btn-outline-info">
                <i class="fa-solid fa-file-csv me-1"></i><?= gettext('Class List Export') ?>
            </a>
            <a href="<?= $sRootPath ?>/api/groups/sundayschool/export/email" class="btn btn-outline-info">
                <i class="fa-solid fa-table me-1"></i><?= gettext('Email Export') ?>
            </a>
        </div>
    </div>
</div>

<div class="card border border-primary mb-3">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-chalkboard-user"></i> <?= gettext('Sunday School Classes') ?></h3>
        <div class="card-tools ms-auto">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
            <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
        </div>
    </div>
    <div class="card-body" style="overflow: visible;">
        <table id="sundayschoolClasses" class="table table-bordered data-table w-100">
            <thead>
                <tr>
                    <th><?= gettext('Class') ?></th>
                    <th><?= gettext('Teachers') ?></th>
                    <th><?= gettext('Students') ?></th>
                    <th class="w-1 no-export text-center"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classStats as $class) { ?>
                    <tr>
                        <td>
                            <a href="<?= $sRootPath ?>/groups/sundayschool/class/<?= $class['id'] ?>">
                                <?= htmlspecialchars($class['name']) ?>
                            </a>
                        </td>
                        <td><?= $class['teachers'] ?></td>
                        <td><?= $class['kids'] ?></td>
                        <td class="w-1">
                            <div class="d-flex gap-1 align-items-center">
                            <button type="button" class="btn btn-sm btn-success start-checkin-btn"
                                data-group-id="<?= $class['id'] ?>"
                                data-group-name="<?= InputUtils::escapeAttribute($class['name']) ?>"
                                title="<?= gettext('Start Check-in') ?>">
                                <i class="ti ti-clipboard-check me-1"></i><?= gettext('Check In') ?>
                            </button>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="<?= $sRootPath ?>/groups/sundayschool/class/<?= $class['id'] ?>">
                                        <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                                    </a>
                                    <a class="dropdown-item" href="<?= $sRootPath ?>/GroupEditor.php?GroupID=<?= $class['id'] ?>">
                                        <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <?php if ($class['teachers'] > 0) { ?>
                                    <button type="button" class="dropdown-item add-ss-role-to-cart" data-group-id="<?= $class['id'] ?>" data-role-name="Teacher">
                                        <i class="ti ti-shopping-cart-plus me-2"></i><?= gettext('Add Teachers to Cart') ?>
                                    </button>
                                    <?php } ?>
                                    <?php if ($class['kids'] > 0) { ?>
                                    <button type="button" class="dropdown-item add-ss-role-to-cart" data-group-id="<?= $class['id'] ?>" data-role-name="Student">
                                        <i class="ti ti-shopping-cart-plus me-2"></i><?= gettext('Add Students to Cart') ?>
                                    </button>
                                    <?php } ?>
                                    <?php if ($class['teachers'] > 0 || $class['kids'] > 0) { ?>
                                    <button type="button"
                                        class="dropdown-item AddToCart"
                                        data-cart-id="<?= $class['id'] ?>"
                                        data-cart-type="group"
                                        data-label-add="<?= gettext('Add all to Cart') ?>"
                                        data-label-remove="<?= gettext('Remove all from Cart') ?>">
                                        <i class="ti ti-shopping-cart-plus me-2"></i>
                                        <span class="cart-label"><?= gettext('Add all to Cart') ?></span>
                                    </button>
                                    <?php } ?>
                                    <div class="dropdown-divider"></div>
                                    <button type="button"
                                        class="dropdown-item text-danger delete-ss-class"
                                        data-group-id="<?= $class['id'] ?>"
                                        data-group-name="<?= InputUtils::escapeAttribute($class['name']) ?>">
                                        <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                    </button>
                                </div>
                            </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border border-warning mb-3">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-user-xmark"></i> <?= gettext('Students not in a Sunday School Class') ?></h3>
        <div class="card-tools ms-auto">
            <button type="button" class="btn btn-tool" data-card-widget="collapse"><i class="fa-solid fa-minus"></i></button>
            <button type="button" class="btn btn-tool" data-card-widget="remove"><i class="fa-solid fa-times"></i></button>
        </div>
    </div>
    <div class="card-body" style="overflow: visible;">
        <table id="sundayschoolMissing" class="table table-bordered data-table w-100">
            <thead>
                <tr>
                    <th><?= gettext('First Name') ?></th>
                    <th><?= gettext('Last Name') ?></th>
                    <th><?= gettext('Birth Date') ?></th>
                    <th><?= gettext('Age') ?></th>
                    <th><?= gettext('Home Address') ?></th>
                    <th class="w-1 no-export"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($kidsWithoutClasses as $child) {
                    extract($child);

                    $birthDate = 'N/A';
                    $age       = 'N/A';
                    try {
                        if (!$hideAge) {
                            $birthDate = MiscUtils::formatBirthDate($birthYear, $birthMonth, $birthDay, $hideAge);
                            $age       = MiscUtils::formatAge($birthMonth, $birthDay, $birthYear);
                        }
                    } catch (\Throwable $ex) {
                        LoggerUtils::getAppLogger()->error("Failed to retrieve student's age", ['exception' => $ex]);
                    }

                    $inCart = isset($_SESSION['aPeopleCart']) && in_array($kidId, $_SESSION['aPeopleCart'], false);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <img data-image-entity-type="person" data-image-entity-id="<?= $kidId ?>" class="avatar avatar-xs rounded-circle me-2" alt="" />
                                <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $kidId ?>">
                                    <?= InputUtils::escapeHTML($firstName) ?>
                                </a>
                            </div>
                        </td>
                        <td><?= InputUtils::escapeHTML($LastName) ?></td>
                        <td><?= $birthDate ?></td>
                        <td><?= $age ?></td>
                        <td><?= InputUtils::escapeHTML($Address1 . ' ' . $Address2 . ' ' . $city . ' ' . $state . ' ' . $zip) ?></td>
                        <td class="w-1">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $kidId ?>">
                                        <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                                    </a>
                                    <a class="dropdown-item" href="<?= $sRootPath ?>/PersonEditor.php?PersonID=<?= $kidId ?>">
                                        <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                    </a>
                                    <?php if ($famId): ?>
                                    <a class="dropdown-item" href="<?= $sRootPath ?>/v2/family/<?= $famId ?>">
                                        <i class="ti ti-users me-2"></i><?= gettext('View Family') ?>
                                    </a>
                                    <?php endif; ?>
                                    <div class="dropdown-divider"></div>
                                    <button type="button"
                                        class="dropdown-item <?= $inCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
                                        data-cart-id="<?= $kidId ?>"
                                        data-cart-type="person"
                                        data-label-add="<?= gettext('Add to Cart') ?>"
                                        data-label-remove="<?= gettext('Remove from Cart') ?>">
                                        <i class="<?= $inCart ? 'ti ti-shopping-cart-off' : 'ti ti-shopping-cart-plus' ?> me-2"></i>
                                        <span class="cart-label"><?= $inCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
                                    </button>
                                    <div class="dropdown-divider"></div>
                                    <button type="button"
                                        class="dropdown-item text-danger delete-person"
                                        data-person_id="<?= $kidId ?>"
                                        data-person_name="<?= InputUtils::escapeAttribute($firstName . ' ' . $LastName) ?>">
                                        <i class="ti ti-trash me-2"></i><?= gettext('Delete') ?>
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($canManageGroups) { ?>
    <div class="modal fade" id="add-class" tabindex="-1" role="dialog" aria-labelledby="add-class-label" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    <h4 class="modal-title" id="add-class-label">
                        <?= gettext('Add') . ' ' . gettext('Sunday School') . ' ' . gettext('Class') ?>
                    </h4>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" id="new-class-name" class="form-control"
                               placeholder="<?= gettext('Enter Name') ?>" maxlength="20" required>
                    </div>
                </div>
                <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Cancel') ?></button>
                        <button type="button" id="addNewClassBtn" class="btn btn-primary"
                            data-bs-dismiss="modal"><?= gettext('Add') ?></button>
                </div>
            </div>
        </div>
    </div>

<?php } ?>

<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/groups-sundayschool-dashboard.min.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
