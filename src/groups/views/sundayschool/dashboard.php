<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="card card-info card-outline mb-3">
    <div class="card-header d-flex align-items-center">
        <h3 class="card-title"><i class="fa-solid fa-church"></i> <?= gettext('Overview') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-secondary">
                                <div class="stat-icon bg-secondary text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-chalkboard"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Classes') ?></div>
                            <div class="h2 m-0"><?= $classes ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-success">
                                <div class="stat-icon bg-success text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-person-chalkboard"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Teachers') ?></div>
                            <div class="h2 m-0"><?= $teachers ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-primary">
                                <div class="stat-icon bg-primary text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-children"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Students') ?></div>
                            <div class="h2 m-0"><?= $kids ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-info">
                                <div class="stat-icon bg-info text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-people-roof"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Families') ?></div>
                            <div class="h2 m-0"><?= $families ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-primary">
                                <div class="stat-icon bg-primary text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-child"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Boys') ?></div>
                            <div class="h2 m-0"><?= $maleKids ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card card-sm">
                    <div class="card-body">
                        <div class="text-truncate">
                            <h3 class="card-title text-danger">
                                <div class="stat-icon bg-danger text-white rounded-circle me-2" style="display:inline-flex;align-items:center;justify-content:center;width:2.5rem;height:2.5rem;">
                                    <i class="fa-solid fa-child-dress"></i>
                                </div>
                            </h3>
                            <div class="h6 text-muted"><?= gettext('Girls') ?></div>
                            <div class="h2 m-0"><?= $femaleKids ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <div class="btn-group" role="group">
                    <?php if ($canManageGroups) { ?>
                        <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#add-class" title="<?= gettext('Add New Class') ?>">
                            <i class="fa-solid fa-plus me-2"></i><?= gettext('Add New Class') ?>
                        </button>
                    <?php } ?>
                    <a href="<?= $sRootPath ?>/groups/sundayschool/reports" class="btn btn-outline-primary" title="<?= gettext('Generate class lists and attendance sheets') ?>">
                        <i class="fa-solid fa-file-pdf me-2"></i><?= gettext('Reports') ?>
                    </a>
                    <a href="<?= $sRootPath ?>/groups/sundayschool/export" class="btn btn-outline-info" title="<?= gettext('Export All Classes, Kids, and Parent to CSV file') ?>">
                        <i class="fa-solid fa-file-csv me-2"></i><?= gettext('Export to CSV') ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-primary card-outline mb-3">
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
                            <a href="<?= $sRootPath ?>/GroupEditor.php?GroupID=<?= $class['id'] ?>" class="mr-2" title="<?= gettext('Edit') ?>">
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

<div class="card card-warning card-outline mb-3">
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
                    <div class="form-group">
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
