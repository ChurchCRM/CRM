<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<!-- Stat Cards Row -->
<div class="row mb-3">
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-secondary text-white avatar rounded-circle">
                            <i class="fa-solid fa-chalkboard"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium"><?= $classes ?></div>
                        <div class="text-muted"><?= gettext('Classes') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-person-chalkboard"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium"><?= $teachers ?></div>
                        <div class="text-muted"><?= gettext('Teachers') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-children"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium"><?= $kids ?></div>
                        <div class="text-muted"><?= gettext('Students') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-info text-white avatar rounded-circle">
                            <i class="fa-solid fa-people-roof"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium"><?= $families ?></div>
                        <div class="text-muted"><?= gettext('Families') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-azure text-white avatar rounded-circle">
                            <i class="fa-solid fa-child"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium"><?= $maleKids ?></div>
                        <div class="text-muted"><?= gettext('Boys') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-2">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-danger text-white avatar rounded-circle">
                            <i class="fa-solid fa-child-dress"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="font-weight-medium"><?= $femaleKids ?></div>
                        <div class="text-muted"><?= gettext('Girls') ?></div>
                    </div>
                </div>
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
            <a href="<?= $sRootPath ?>/groups/sundayschool/export" class="btn btn-outline-info">
                <i class="fa-solid fa-file-csv me-1"></i><?= gettext('Export to CSV') ?>
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
    <div class="card-body">
        <table id="sundayschoolClasses" class="table table-striped table-bordered data-table w-100">
            <thead>
                <tr>
                    <th><?= gettext('Class') ?></th>
                    <th><?= gettext('Teachers') ?></th>
                    <th><?= gettext('Students') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classStats as $class) { ?>
                    <tr>
                        <td>
                            <a href="<?= $sRootPath ?>/GroupEditor.php?GroupID=<?= $class['id'] ?>" class="me-2" title="<?= gettext('Edit') ?>">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a href="<?= $sRootPath ?>/groups/sundayschool/class/<?= $class['id'] ?>">
                                <?= htmlspecialchars($class['name']) ?>
                            </a>
                        </td>
                        <td><?= $class['teachers'] ?></td>
                        <td><?= $class['kids'] ?></td>
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
    <div class="card-body table-responsive">
        <table id="sundayschoolMissing" class="table table-striped table-bordered data-table w-100">
            <thead>
                <tr>
                    <th><?= gettext('First Name') ?></th>
                    <th><?= gettext('Last Name') ?></th>
                    <th><?= gettext('Birth Date') ?></th>
                    <th><?= gettext('Age') ?></th>
                    <th><?= gettext('Home Address') ?></th>
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

                    $personPhoto = new \ChurchCRM\dto\Photo('person', $kidId);
                    $photoIcon   = '';
                    if ($personPhoto->hasUploadedPhoto()) {
                        $photoIcon = ' <button class="btn btn-sm btn-outline-secondary view-person-photo" data-person-id="' . $kidId . '" title="' . gettext('View Photo') . '"><i class="fa-solid fa-camera"></i></button>';
                    }
                    ?>
                    <tr>
                        <td>
                            <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $kidId ?>">
                                <?= htmlspecialchars($firstName) ?>
                            </a><?= $photoIcon ?>
                        </td>
                        <td><?= htmlspecialchars($LastName) ?></td>
                        <td><?= $birthDate ?></td>
                        <td><?= $age ?></td>
                        <td><?= htmlspecialchars($Address1 . ' ' . $Address2 . ' ' . $city . ' ' . $state . ' ' . $zip) ?></td>
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

    <script src="<?= SystemURLs::assetVersioned('/skin/js/cart-photo-viewer.js') ?>"></script>
<?php } ?>

<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/groups-sundayschool-dashboard.min.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
