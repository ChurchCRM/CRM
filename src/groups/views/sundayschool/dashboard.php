<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

?>

<div class="card card-info card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-church"></i> <?= gettext('Sunday School Overview') ?></h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-secondary">
                    <span class="info-box-icon"><i class="fa-solid fa-chalkboard"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Classes') ?></span>
                        <span class="info-box-number"><?= $classes ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-success">
                    <span class="info-box-icon"><i class="fa-solid fa-person-chalkboard"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Teachers') ?></span>
                        <span class="info-box-number"><?= $teachers ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-primary">
                    <span class="info-box-icon"><i class="fa-solid fa-children"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Students') ?></span>
                        <span class="info-box-number"><?= $kids ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-info">
                    <span class="info-box-icon"><i class="fa-solid fa-people-roof"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Families') ?></span>
                        <span class="info-box-number"><?= $families ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-primary">
                    <span class="info-box-icon"><i class="fa-solid fa-child"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Boys') ?></span>
                        <span class="info-box-number"><?= $maleKids ?></span>
                    </div>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="info-box bg-danger">
                    <span class="info-box-icon"><i class="fa-solid fa-child-dress"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text"><?= gettext('Girls') ?></span>
                        <span class="info-box-number"><?= $femaleKids ?></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <?php if ($canManageGroups) { ?>
                    <button class="btn btn-app bg-success" data-toggle="modal" data-target="#add-class">
                        <i class="fa-solid fa-plus-square fa-3x"></i><br>
                        <?= gettext('Add New') . ' ' . gettext('Class') ?>
                    </button>
                <?php } ?>
                <a href="<?= $sRootPath ?>/groups/sundayschool/reports" class="btn btn-app bg-primary"
                   title="<?= gettext('Generate class lists and attendance sheets') ?>">
                    <i class="fa-solid fa-file-pdf fa-3x"></i><br>
                    <?= gettext('Reports') ?>
                </a>
                <a href="<?= $sRootPath ?>/groups/sundayschool/export" class="btn btn-app bg-info"
                   title="<?= gettext('Export All Classes, Kids, and Parent to CSV file') ?>">
                    <i class="fa-solid fa-file-csv fa-3x"></i><br>
                    <?= gettext('Export to CSV') ?>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card card-primary card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-chalkboard-user"></i> <?= gettext('Sunday School Classes') ?></h3>
        <div class="card-tools float-right">
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

<div class="card card-warning card-outline">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-user-xmark"></i> <?= gettext('Students not in a Sunday School Class') ?></h3>
        <div class="card-tools float-right">
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
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
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
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= gettext('Cancel') ?></button>
                    <button type="button" id="addNewClassBtn" class="btn btn-primary"
                            data-dismiss="modal"><?= gettext('Add') ?></button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= SystemURLs::assetVersioned('/skin/js/cart-photo-viewer.js') ?>"></script>
<?php } ?>

<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/groups-sundayschool-dashboard.min.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
