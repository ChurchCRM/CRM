<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\MiscUtils;

// Functions.php is not auto-loaded in the Slim/PhpRenderer context — require it explicitly
// for helpers like generateGroupRoleEmailDropdown() that are defined there.
require_once SystemURLs::getDocumentRoot() . '/Include/Functions.php';

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Pre-calculate stats for use in the Overview card
$totalStudents = count($thisClassChildren);
$maleCount     = 0;
$femaleCount   = 0;

foreach ($thisClassChildren as $child) {
    switch ($child['kidGender']) {
        case 1: $maleCount++;   break;
        case 2: $femaleCount++; break;
    }
}

$teacherCount = count($rsTeachers);

?>

<!-- Stat Cards Row -->
<div class="row mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-primary text-white avatar rounded-circle">
                            <i class="fa-solid fa-users icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $totalStudents ?></div>
                        <div class="text-muted"><?= gettext('Total Enrolled') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-azure text-white avatar rounded-circle">
                            <i class="fa-solid fa-child icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $maleCount ?></div>
                        <div class="text-muted"><?= gettext('Boys') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-danger text-white avatar rounded-circle">
                            <i class="fa-solid fa-child-dress icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $femaleCount ?></div>
                        <div class="text-muted"><?= gettext('Girls') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <span class="bg-success text-white avatar rounded-circle">
                            <i class="fa-solid fa-person-chalkboard icon"></i>
                        </span>
                    </div>
                    <div class="col">
                        <div class="fw-medium"><?= $teacherCount ?></div>
                        <div class="text-muted"><?= gettext('Teachers') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Class Actions -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <h5 class="card-title mb-0">
            <i class="fa-solid fa-chalkboard-user me-2"></i>
            <?= gettext('Sunday School') ?>: <strong><?= htmlspecialchars($iGroupName) ?></strong>
        </h5>
        <div class="ms-auto">
            <div class="btn-group flex-wrap" role="group">
                <a class="btn btn-outline-success" href="<?= $sRootPath ?>/GroupView.php?GroupID=<?= $iGroupId ?>" title="<?= gettext('Add students to this class') ?>">
                    <i class="fa-solid fa-user-plus me-2"></i><?= gettext('Add Students') ?>
                </a>
                <a class="btn btn-outline-primary" href="<?= $sRootPath ?>/GroupEditor.php?GroupID=<?= $iGroupId ?>" title="<?= gettext('Edit class details') ?>">
                    <i class="fa-solid fa-pen me-2"></i><?= gettext('Edit Class') ?>
                </a>
                <?php if ($canEmail) { ?>
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-info dropdown-toggle" type="button"
                                id="emailClassDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                title="<?= gettext('Send email with recipients in To field') ?>">
                            <i class="fa-solid fa-paper-plane me-2"></i><?= gettext('Email (To)') ?>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="emailClassDropdown">
                            <a class="dropdown-item" href="mailto:<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All Members') ?></a>
                            <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:') ?>
                        </div>
                    </div>
                    <div class="btn-group" role="group">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                id="emailClassBccDropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false"
                                title="<?= gettext('Send email with recipients in BCC field (hidden from each other)') ?>">
                            <i class="fa-solid fa-user-secret me-2"></i><?= gettext('Email (BCC)') ?>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="emailClassBccDropdown">
                            <a class="dropdown-item" href="mailto:?bcc=<?= mb_substr($sEmailLink, 0, -3) ?>"><?= gettext('All Members') ?></a>
                            <?php generateGroupRoleEmailDropdown($roleEmails, 'mailto:?bcc=') ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Birthday Chart Card -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <h5 class="card-title mb-0"><i class="fa-solid fa-chart-bar me-2"></i><?= gettext('Birthdays by Month') ?></h5>
    </div>
    <div class="card-body">
        <div class="user-select-none">
            <div id="bar-chart"
                    data-chart="<?= htmlspecialchars($birthDayMonthChartJSON, ENT_QUOTES) ?>"
                    data-chart-label="<?= htmlspecialchars(gettext('Birthdays by Month'), ENT_QUOTES) ?>"></div>
        </div>
    </div>
</div>

<!-- Teachers Card -->
<?php if ($teacherCount > 0) { ?>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <h5 class="card-title mb-0"><i class="fa-solid fa-person-chalkboard me-2"></i><?= gettext('Teachers') ?></h5>
        <span class="badge bg-success ms-2"><?= $teacherCount ?></span>
    </div>
    <div class="card-body">
        <div class="row">
            <?php foreach ($rsTeachers as $teacher) {
                $phone = $teacher->getCellPhone() ?: $teacher->getHomePhone();
            ?>
                <div class="col-md-6 col-lg-3 mb-3">
                    <div class="card">
                        <div class="card-body p-4 text-center">
                            <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $teacher->getId() ?>"
                               title="<?= gettext('View Profile') ?>">
                                <span class="avatar avatar-xl mb-3">
                                    <img data-image-entity-type="person"
                                         data-image-entity-id="<?= $teacher->getId() ?>"
                                         alt="<?= htmlspecialchars($teacher->getFirstName() . ' ' . $teacher->getLastName()) ?>" />
                                </span>
                            </a>
                            <h3 class="m-0 mb-1">
                                <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $teacher->getId() ?>">
                                    <?= htmlspecialchars($teacher->getFirstName() . ' ' . $teacher->getLastName()) ?>
                                </a>
                            </h3>
                            <div class="text-secondary"><?= gettext('Teacher') ?></div>
                        </div>
                        <div class="d-flex">
                            <?php if ($teacher->getEmail()) { ?>
                                <a href="mailto:<?= $teacher->getEmail() ?>" class="card-btn"
                                   title="<?= gettext('Email') . ' ' . htmlspecialchars($teacher->getFirstName()) ?>">
                                    <i class="fa-solid fa-envelope me-2 text-muted"></i><?= gettext('Email') ?>
                                </a>
                            <?php } ?>
                            <?php if ($phone) { ?>
                                <a href="tel:<?= urlencode($phone) ?>" class="card-btn"
                                   title="<?= gettext('Call') . ' ' . htmlspecialchars($teacher->getFirstName()) ?>">
                                    <i class="fa-solid fa-phone me-2 text-muted"></i><?= gettext('Call') ?>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php } ?>

<!-- Students Card -->
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <h5 class="card-title mb-0">
            <i class="fa-solid fa-users me-2"></i><?= gettext('Students') ?>
        </h5>
        <span class="badge bg-primary ms-2"><?= $totalStudents ?></span>
    </div>
    <div class="card-body">
        <div class="birthday-filter d-none alert alert-info mb-3">
            <?= gettext('Showing students with birthdays in') ?> <span class="month"></span>
            <i class="fa-solid fa-times float-end birthday-filter-clear" style="cursor:pointer;" title="<?= gettext('Clear filter') ?>"></i>
        </div>
        <div style="overflow: visible;">
            <table id="sundayschool" class="table table-striped table-hover data-table w-100">
                <thead>
                    <tr>
                        <th><?= gettext('Name') ?></th>
                        <th><?= gettext('Age') ?></th>
                        <th><?= gettext('Mobile') ?></th>
                        <th><?= gettext('Email') ?></th>
                        <th><?= gettext('Father') ?></th>
                        <th><?= gettext('Mother') ?></th>
                        <th class="w-1 no-export text-center"><?= gettext('Actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($thisClassChildren as $child) {
                        $hideAge = $child['hideAge'];
                        $age     = MiscUtils::formatAge($child['birthMonth'], $child['birthDay'], $child['birthYear']);
                        ?>
                        <tr>
                            <td>
                                <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $child['kidId'] ?>">
                                    <strong><?= htmlspecialchars($child['LastName'] . ', ' . $child['firstName']) ?></strong>
                                </a>
                            </td>
                            <td><?= $hideAge ? '—' : $age ?></td>
                            <td>
                                <?php if ($child['mobilePhone']) { ?>
                                    <a href="tel:<?= urlencode($child['mobilePhone']) ?>" title="Call">
                                        <i class="fa-solid fa-phone text-primary"></i> <?= $child['mobilePhone'] ?>
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($child['kidEmail']) { ?>
                                    <a href="mailto:<?= $child['kidEmail'] ?>" title="Email">
                                        <i class="fa-solid fa-envelope text-primary"></i>
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($child['dadFirstName']) { ?>
                                    <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $child['dadId'] ?>">
                                        <?= htmlspecialchars($child['dadFirstName'] . ' ' . $child['dadLastName']) ?>
                                    </a>
                                    <?php if ($child['dadCellPhone']) { ?>
                                        <br><small><a href="tel:<?= urlencode($child['dadCellPhone']) ?>"><?= $child['dadCellPhone'] ?></a></small>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ($child['momFirstName']) { ?>
                                    <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $child['momId'] ?>">
                                        <?= htmlspecialchars($child['momFirstName'] . ' ' . $child['momLastName']) ?>
                                    </a>
                                    <?php if ($child['momCellPhone']) { ?>
                                        <br><small><a href="tel:<?= urlencode($child['momCellPhone']) ?>"><?= $child['momCellPhone'] ?></a></small>
                                    <?php } ?>
                                <?php } else { ?>
                                    <span class="text-muted">—</span>
                                <?php } ?>
                            </td>
                            <?php
                                $inCart = isset($_SESSION['aPeopleCart']) && in_array($child['kidId'], $_SESSION['aPeopleCart'], false);
                            ?>
                            <td class="w-1">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="ti ti-dots-vertical"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $child['kidId'] ?>">
                                            <i class="ti ti-eye me-2"></i><?= gettext('View') ?>
                                        </a>
                                        <a class="dropdown-item" href="<?= $sRootPath ?>/PersonEditor.php?PersonID=<?= $child['kidId'] ?>">
                                            <i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?>
                                        </a>
                                        <?php if ($child['fam_id']) { ?>
                                        <a class="dropdown-item" href="<?= $sRootPath ?>/v2/family/<?= $child['fam_id'] ?>">
                                            <i class="ti ti-users me-2"></i><?= gettext('View Family') ?>
                                        </a>
                                        <?php } ?>
                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#studentModal-<?= $child['kidId'] ?>">
                                            <i class="ti ti-info-circle me-2"></i><?= gettext('Details') ?>
                                        </button>
                                        <div class="dropdown-divider"></div>
                                        <button type="button"
                                            class="dropdown-item <?= $inCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
                                            data-cart-id="<?= $child['kidId'] ?>"
                                            data-cart-type="person"
                                            data-label-add="<?= gettext('Add to Cart') ?>"
                                            data-label-remove="<?= gettext('Remove from Cart') ?>">
                                            <i class="<?= $inCart ? 'ti ti-shopping-cart-off' : 'ti ti-shopping-cart-plus' ?> me-2"></i>
                                            <span class="cart-label"><?= $inCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
                                        </button>
                                        <div class="dropdown-divider"></div>
                                        <button type="button"
                                            class="dropdown-item text-warning remove-from-class"
                                            data-group-id="<?= $iGroupId ?>"
                                            data-person-id="<?= $child['kidId'] ?>"
                                            data-person-name="<?= \ChurchCRM\Utils\InputUtils::escapeAttribute($child['firstName'] . ' ' . $child['LastName']) ?>">
                                            <i class="ti ti-user-minus me-2"></i><?= gettext('Remove from Class') ?>
                                        </button>
                                        <div class="dropdown-divider"></div>
                                        <button type="button"
                                            class="dropdown-item text-danger delete-person"
                                            data-person_id="<?= $child['kidId'] ?>"
                                            data-person_name="<?= \ChurchCRM\Utils\InputUtils::escapeAttribute($child['firstName'] . ' ' . $child['LastName']) ?>">
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

        <!-- Student Detail Modals -->
        <?php foreach ($thisClassChildren as $child) {
            $hideAge   = $child['hideAge'];
            $birthDate = MiscUtils::formatBirthDate($child['birthYear'], $child['birthMonth'], $child['birthDay'], $hideAge);
            $address   = trim($child['Address1'] . ' ' . $child['Address2'] . ' ' . $child['city'] . ' ' . $child['state'] . ' ' . $child['zip']);
            ?>
            <div class="modal fade" id="studentModal-<?= $child['kidId'] ?>" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title">
                                <i class="fa-solid fa-user me-2"></i>
                                <?= htmlspecialchars($child['firstName'] . ' ' . $child['LastName']) ?>
                            </h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="mb-3"><i class="fa-solid fa-circle-info me-1"></i> <?= gettext('Student Information') ?></h6>
                                    <dl class="row">
                                        <dt class="col-sm-5"><?= gettext('Birth Date') . ':' ?></dt>
                                        <dd class="col-sm-7"><?= $birthDate ?></dd>
                                        <dt class="col-sm-5"><?= gettext('Email') . ':' ?></dt>
                                        <dd class="col-sm-7">
                                            <?php if ($child['kidEmail']) { ?>
                                                <a href="mailto:<?= $child['kidEmail'] ?>"><?= htmlspecialchars($child['kidEmail']) ?></a>
                                            <?php } else { ?>
                                                <span class="text-muted">—</span>
                                            <?php } ?>
                                        </dd>
                                        <dt class="col-sm-5"><?= gettext('Mobile') . ':' ?></dt>
                                        <dd class="col-sm-7">
                                            <?php if ($child['mobilePhone']) { ?>
                                                <a href="tel:<?= urlencode($child['mobilePhone']) ?>"><?= $child['mobilePhone'] ?></a>
                                            <?php } else { ?>
                                                <span class="text-muted">—</span>
                                            <?php } ?>
                                        </dd>
                                        <dt class="col-sm-5"><?= gettext('Home Phone') . ':' ?></dt>
                                        <dd class="col-sm-7">
                                            <?php if ($child['homePhone']) { ?>
                                                <a href="tel:<?= urlencode($child['homePhone']) ?>"><?= $child['homePhone'] ?></a>
                                            <?php } else { ?>
                                                <span class="text-muted">—</span>
                                            <?php } ?>
                                        </dd>
                                    </dl>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="mb-3"><i class="fa-solid fa-home me-1"></i> <?= gettext('Address') ?></h6>
                                    <address>
                                        <?php if ($address) { ?>
                                            <?= htmlspecialchars($address) ?>
                                        <?php } else { ?>
                                            <span class="text-muted">—</span>
                                        <?php } ?>
                                    </address>

                                    <h6 class="mb-3 mt-4"><i class="fa-solid fa-users me-1"></i> <?= gettext('Parents/Guardians') ?></h6>
                                    <?php if ($child['dadFirstName'] || $child['momFirstName']) { ?>
                                        <dl class="row">
                                            <?php if ($child['dadFirstName']) { ?>
                                                <dt class="col-sm-5"><?= gettext('Father') . ':' ?></dt>
                                                <dd class="col-sm-7">
                                                    <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $child['dadId'] ?>">
                                                        <?= htmlspecialchars($child['dadFirstName'] . ' ' . $child['dadLastName']) ?>
                                                    </a>
                                                    <?php if ($child['dadCellPhone']) { ?>
                                                        <br><small><a href="tel:<?= urlencode($child['dadCellPhone']) ?>"><?= $child['dadCellPhone'] ?></a></small>
                                                    <?php } ?>
                                                    <?php if ($child['dadEmail']) { ?>
                                                        <br><small><a href="mailto:<?= $child['dadEmail'] ?>"><?= htmlspecialchars($child['dadEmail']) ?></a></small>
                                                    <?php } ?>
                                                </dd>
                                            <?php } ?>
                                            <?php if ($child['momFirstName']) { ?>
                                                <dt class="col-sm-5"><?= gettext('Mother') . ':' ?></dt>
                                                <dd class="col-sm-7">
                                                    <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $child['momId'] ?>">
                                                        <?= htmlspecialchars($child['momFirstName'] . ' ' . $child['momLastName']) ?>
                                                    </a>
                                                    <?php if ($child['momCellPhone']) { ?>
                                                        <br><small><a href="tel:<?= urlencode($child['momCellPhone']) ?>"><?= $child['momCellPhone'] ?></a></small>
                                                    <?php } ?>
                                                    <?php if ($child['momEmail']) { ?>
                                                        <br><small><a href="mailto:<?= $child['momEmail'] ?>"><?= htmlspecialchars($child['momEmail']) ?></a></small>
                                                    <?php } ?>
                                                </dd>
                                            <?php } ?>
                                        </dl>
                                    <?php } else { ?>
                                        <span class="text-muted">—</span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="<?= $sRootPath ?>/PersonView.php?PersonID=<?= $child['kidId'] ?>" class="btn btn-primary">
                                <i class="fa-solid fa-user me-1"></i><?= gettext('View Profile') ?>
                            </a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<script src="<?= SystemURLs::assetVersioned('/skin/js/cart-photo-viewer.js') ?>"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/groups-sundayschool-class-view.min.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
