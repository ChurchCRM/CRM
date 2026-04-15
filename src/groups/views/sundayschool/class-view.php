<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\MiscUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

// Pre-calculate stats
$totalStudents = count($thisClassChildren);
$maleCount     = 0;
$femaleCount   = 0;
foreach ($thisClassChildren as $child) {
    if ($child['kidGender'] === 1) { $maleCount++; }
    elseif ($child['kidGender'] === 2) { $femaleCount++; }
}
$teacherCount = count($rsTeachers);

// Available properties for the "Assign" dropdown
$availableProperties = [];
if ($bCanManageGroups) {
    foreach ($allGroupPropertyDefs as $propDefObj) {
        if (!in_array($propDefObj->getProId(), $rsAssignedPropertyIds, true)) {
            $availableProperties[] = [
                'pro_ID'     => $propDefObj->getProId(),
                'pro_Name'   => $propDefObj->getProName(),
                'pro_Prompt' => (string) $propDefObj->getProPrompt(),
            ];
        }
    }
}
?>

<!-- Stat Cards Row -->
<div class="row mb-3">
    <div class="col-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-primary text-white avatar rounded-circle"><i class="fa-solid fa-users icon"></i></span></div>
                    <div class="col"><div class="fw-medium"><?= $totalStudents ?></div><div class="text-muted"><?= gettext('Enrolled') ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-azure text-white avatar rounded-circle"><i class="fa-solid fa-child icon"></i></span></div>
                    <div class="col"><div class="fw-medium"><?= $maleCount ?></div><div class="text-muted"><?= gettext('Boys') ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-danger text-white avatar rounded-circle"><i class="fa-solid fa-child-dress icon"></i></span></div>
                    <div class="col"><div class="fw-medium"><?= $femaleCount ?></div><div class="text-muted"><?= gettext('Girls') ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto"><span class="bg-success text-white avatar rounded-circle"><i class="fa-solid fa-person-chalkboard icon"></i></span></div>
                    <div class="col"><div class="fw-medium"><?= $teacherCount ?></div><div class="text-muted"><?= gettext('Teachers') ?></div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg">
        <div class="card card-sm">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-auto">
                        <?php if ($thisGroup && $thisGroup->isActive()): ?>
                        <span class="bg-success text-white avatar rounded-circle"><i class="fa-solid fa-circle-check icon"></i></span>
                        <?php else: ?>
                        <span class="bg-danger text-white avatar rounded-circle"><i class="fa-solid fa-circle-xmark icon"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="col">
                        <div class="fw-medium">
                            <?php if ($thisGroup && $thisGroup->isActive()): ?>
                            <span class="badge bg-success-lt text-success"><?= gettext('Active') ?></span>
                            <?php else: ?>
                            <span class="badge bg-danger-lt text-danger"><?= gettext('Inactive') ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-muted"><?= gettext('Status') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- LEFT COLUMN: Actions, Teachers, Students, Birthday Chart -->
    <div class="col-lg-8">

        <!-- Action Toolbar (ghost buttons — matches group view) -->
        <div id="ss-action-toolbar" class="d-flex align-items-center mb-3 gap-2 flex-wrap d-print-none">
            <a class="btn btn-ghost-success" href="<?= $sRootPath ?>/groups/view/<?= $iGroupId ?>">
                <i class="fa-solid fa-user-plus me-1"></i><?= gettext('Add Students') ?>
            </a>
            <button class="btn btn-ghost-secondary" id="printClass" title="<?= gettext('Print') ?>">
                <i class="fa-solid fa-print me-1"></i><?= gettext('Print') ?>
            </button>
            <?php if ($bCanManageGroups): ?>
            <a class="btn btn-ghost-primary" href="<?= $sRootPath ?>/GroupEditor.php?GroupID=<?= $iGroupId ?>">
                <i class="fa-solid fa-pen me-1"></i><?= gettext('Edit Class') ?>
            </a>
            <?php endif; ?>
            <a class="btn btn-ghost-info" href="<?= $sRootPath ?>/v2/map?groupId=<?= $iGroupId ?>">
                <i class="fa-solid fa-map-location-dot me-1"></i><?= gettext('Map') ?>
            </a>
            <?php if ($canEmail): ?>
            <div class="dropdown">
                <button class="btn btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" id="ssEmailDropdownBtn">
                    <i class="fa-solid fa-paper-plane me-1"></i><?= gettext('Email') ?>
                </button>
                <div class="dropdown-menu" id="ssEmailDropdownMenu">
                    <div class="text-center py-2 text-muted"><i class="fa-solid fa-spinner fa-spin me-1"></i><?= gettext('Loading...') ?></div>
                </div>
            </div>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static" id="ssTextDropdownBtn">
                    <i class="fa-solid fa-mobile-screen me-1"></i><?= gettext('Text') ?>
                </button>
                <div class="dropdown-menu" id="ssTextDropdownMenu">
                    <div class="text-center py-2 text-muted"><i class="fa-solid fa-spinner fa-spin me-1"></i><?= gettext('Loading...') ?></div>
                </div>
            </div>
            <?php if ($bCanManageGroups): ?>
            <div class="dropdown ms-auto">
                <button class="btn btn-ghost-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-display="static">
                    <i class="fa-solid fa-ellipsis-vertical me-1"></i><?= gettext('Actions') ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end">
                    <h6 class="dropdown-header"><?= gettext('Copy to Group') ?></h6>
                    <a class="dropdown-item ss-copy-role" data-role="all" href="#"><i class="fa-solid fa-users me-2"></i><?= gettext('All Members') ?></a>
                    <a class="dropdown-item ss-copy-role" data-role="Student" href="#"><i class="fa-solid fa-child me-2"></i><?= gettext('Students') ?> <span class="badge bg-secondary-lt text-secondary ms-1"><?= $totalStudents ?></span></a>
                    <a class="dropdown-item ss-copy-role" data-role="Teacher" href="#"><i class="fa-solid fa-person-chalkboard me-2"></i><?= gettext('Teachers') ?> <span class="badge bg-secondary-lt text-secondary ms-1"><?= $teacherCount ?></span></a>
                    <div class="dropdown-divider"></div>
                    <h6 class="dropdown-header"><?= gettext('Move to Group') ?></h6>
                    <a class="dropdown-item ss-move-role" data-role="all" href="#"><i class="fa-solid fa-users me-2"></i><?= gettext('All Members') ?></a>
                    <a class="dropdown-item ss-move-role" data-role="Student" href="#"><i class="fa-solid fa-child me-2"></i><?= gettext('Students') ?> <span class="badge bg-secondary-lt text-secondary ms-1"><?= $totalStudents ?></span></a>
                    <a class="dropdown-item ss-move-role" data-role="Teacher" href="#"><i class="fa-solid fa-person-chalkboard me-2"></i><?= gettext('Teachers') ?> <span class="badge bg-secondary-lt text-secondary ms-1"><?= $teacherCount ?></span></a>
                    <div class="dropdown-divider"></div>
                    <?php if ($thisGroup && $thisGroup->getHasSpecialProps()): ?>
                    <a class="dropdown-item" href="<?= $sRootPath ?>/GroupPropsFormEditor.php?GroupID=<?= $iGroupId ?>">
                        <i class="fa-solid fa-rectangle-list me-2"></i><?= gettext('Edit Member Properties Form') ?>
                    </a>
                    <?php endif; ?>
                    <a class="dropdown-item" href="<?= $sRootPath ?>/groups/view/<?= $iGroupId ?>">
                        <i class="fa-solid fa-eye me-2"></i><?= gettext('Full Group View') ?>
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Teachers Card -->
        <?php if ($teacherCount > 0): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h5 class="card-title mb-0"><i class="fa-solid fa-person-chalkboard me-2"></i><?= gettext('Teachers') ?></h5>
                <span class="badge bg-success-lt text-success ms-2"><?= $teacherCount ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($rsTeachers as $teacher):
                    $phone = $teacher->getCellPhone() ?: $teacher->getHomePhone();
                ?>
                <div class="list-group-item p-3 ss-member" data-person-id="<?= $teacher->getId() ?>" data-role="Teacher">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <a href="<?= $sRootPath ?>/people/view/<?= $teacher->getId() ?>">
                                <span class="avatar avatar-md">
                                    <img data-image-entity-type="person" data-image-entity-id="<?= $teacher->getId() ?>"
                                         alt="<?= InputUtils::escapeAttribute($teacher->getFirstName() . ' ' . $teacher->getLastName()) ?>" />
                                </span>
                            </a>
                        </div>
                        <div class="col">
                            <a href="<?= $sRootPath ?>/people/view/<?= $teacher->getId() ?>">
                                <strong><?= InputUtils::escapeHTML($teacher->getFirstName() . ' ' . $teacher->getLastName()) ?></strong>
                            </a>
                            <div class="text-muted small"><?= gettext('Teacher') ?></div>
                        </div>
                        <div class="col-auto d-flex gap-2">
                            <?php if ($teacher->getEmail()): ?>
                            <a href="mailto:<?= InputUtils::escapeAttribute($teacher->getEmail()) ?>" class="btn btn-sm btn-ghost-primary" title="<?= InputUtils::escapeAttribute($teacher->getEmail()) ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fa-solid fa-envelope"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($phone): ?>
                            <a href="tel:<?= urlencode($phone) ?>" class="btn btn-sm btn-ghost-success" title="<?= InputUtils::escapeAttribute($phone) ?>">
                                <i class="fa-solid fa-phone"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Students Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h5 class="card-title mb-0"><i class="fa-solid fa-users me-2"></i><?= gettext('Students') ?></h5>
                <span class="badge bg-primary-lt text-primary ms-2"><?= $totalStudents ?></span>
            </div>
            <div class="card-body">
                <div class="birthday-filter d-none alert alert-info mb-3">
                    <?= gettext('Showing students with birthdays in') ?> <span class="month"></span>
                    <i class="fa-solid fa-times float-end birthday-filter-clear" style="cursor:pointer;" title="<?= gettext('Clear filter') ?>"></i>
                </div>
                <div style="overflow: visible;">
                    <table id="sundayschool" class="table table-hover data-table w-100">
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
                            <?php foreach ($thisClassChildren as $child):
                                $hideAge = $child['hideAge'];
                                $age     = MiscUtils::formatAge($child['birthMonth'], $child['birthDay'], $child['birthYear']);
                                $inCart  = isset($_SESSION['aPeopleCart']) && in_array($child['kidId'], $_SESSION['aPeopleCart'], false);
                            ?>
                            <tr class="ss-member" data-person-id="<?= (int) $child['kidId'] ?>" data-role="Student">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="<?= $sRootPath ?>/people/view/<?= $child['kidId'] ?>">
                                            <span class="avatar avatar-sm">
                                                <img data-image-entity-type="person" data-image-entity-id="<?= (int) $child['kidId'] ?>"
                                                     alt="<?= InputUtils::escapeAttribute($child['firstName'] . ' ' . $child['LastName']) ?>" />
                                            </span>
                                        </a>
                                        <a href="<?= $sRootPath ?>/people/view/<?= $child['kidId'] ?>">
                                            <strong><?= InputUtils::escapeHTML($child['LastName'] . ', ' . $child['firstName']) ?></strong>
                                        </a>
                                    </div>
                                </td>
                                <td><?= $hideAge ? '—' : InputUtils::escapeHTML($age) ?></td>
                                <td>
                                    <?php if ($child['mobilePhone']): ?>
                                    <a href="tel:<?= urlencode($child['mobilePhone']) ?>"><?= InputUtils::escapeHTML($child['mobilePhone']) ?></a>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($child['kidEmail']): ?>
                                    <a href="mailto:<?= InputUtils::escapeAttribute($child['kidEmail']) ?>" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-envelope text-primary"></i></a>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($child['dadFirstName']): ?>
                                    <a href="<?= $sRootPath ?>/people/view/<?= (int) $child['dadId'] ?>">
                                        <?= InputUtils::escapeHTML($child['dadFirstName'] . ' ' . $child['dadLastName']) ?>
                                    </a>
                                    <?php if ($child['dadCellPhone']): ?>
                                    <br><small><a href="tel:<?= urlencode($child['dadCellPhone']) ?>"><?= InputUtils::escapeHTML($child['dadCellPhone']) ?></a></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($child['momFirstName']): ?>
                                    <a href="<?= $sRootPath ?>/people/view/<?= (int) $child['momId'] ?>">
                                        <?= InputUtils::escapeHTML($child['momFirstName'] . ' ' . $child['momLastName']) ?>
                                    </a>
                                    <?php if ($child['momCellPhone']): ?>
                                    <br><small><a href="tel:<?= urlencode($child['momCellPhone']) ?>"><?= InputUtils::escapeHTML($child['momCellPhone']) ?></a></small>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="w-1">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-ghost-secondary" type="button" data-bs-toggle="dropdown" data-bs-display="static">
                                            <i class="ti ti-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <a class="dropdown-item" href="<?= $sRootPath ?>/people/view/<?= $child['kidId'] ?>"><i class="ti ti-eye me-2"></i><?= gettext('View') ?></a>
                                            <a class="dropdown-item" href="<?= $sRootPath ?>/PersonEditor.php?PersonID=<?= $child['kidId'] ?>"><i class="ti ti-pencil me-2"></i><?= gettext('Edit') ?></a>
                                            <?php if ($child['fam_id']): ?>
                                            <a class="dropdown-item" href="<?= $sRootPath ?>/people/family/<?= (int) $child['fam_id'] ?>"><i class="ti ti-users me-2"></i><?= gettext('View Family') ?></a>
                                            <?php endif; ?>
                                            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#studentModal-<?= $child['kidId'] ?>"><i class="ti ti-info-circle me-2"></i><?= gettext('Details') ?></button>
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item <?= $inCart ? 'RemoveFromCart text-danger' : 'AddToCart' ?>"
                                                data-cart-id="<?= $child['kidId'] ?>" data-cart-type="person"
                                                data-label-add="<?= gettext('Add to Cart') ?>" data-label-remove="<?= gettext('Remove from Cart') ?>">
                                                <i class="<?= $inCart ? 'ti ti-shopping-cart-off' : 'ti ti-shopping-cart-plus' ?> me-2"></i>
                                                <span class="cart-label"><?= $inCart ? gettext('Remove from Cart') : gettext('Add to Cart') ?></span>
                                            </button>
                                            <div class="dropdown-divider"></div>
                                            <button type="button" class="dropdown-item text-warning remove-from-class"
                                                data-group-id="<?= $iGroupId ?>" data-person-id="<?= $child['kidId'] ?>"
                                                data-person-name="<?= InputUtils::escapeAttribute($child['firstName'] . ' ' . $child['LastName']) ?>">
                                                <i class="ti ti-user-minus me-2"></i><?= gettext('Remove from Class') ?>
                                            </button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Student Detail Modals -->
                <?php foreach ($thisClassChildren as $child):
                    $hideAge   = $child['hideAge'];
                    $birthDate = MiscUtils::formatBirthDate($child['birthYear'], $child['birthMonth'], $child['birthDay'], $hideAge);
                    $address   = trim($child['Address1'] . ' ' . $child['Address2'] . ' ' . $child['city'] . ' ' . $child['state'] . ' ' . $child['zip']);
                ?>
                <div class="modal fade" id="studentModal-<?= $child['kidId'] ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <span class="avatar avatar-md me-2">
                                    <img data-image-entity-type="person" data-image-entity-id="<?= (int) $child['kidId'] ?>"
                                         alt="<?= InputUtils::escapeAttribute($child['firstName'] . ' ' . $child['LastName']) ?>" />
                                </span>
                                <h4 class="modal-title"><?= InputUtils::escapeHTML($child['firstName'] . ' ' . $child['LastName']) ?></h4>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="mb-3"><i class="fa-solid fa-circle-info me-1"></i> <?= gettext('Student Information') ?></h6>
                                        <dl class="row">
                                            <dt class="col-sm-5"><?= gettext('Birth Date') ?>:</dt>
                                            <dd class="col-sm-7"><?= InputUtils::escapeHTML($birthDate) ?></dd>
                                            <dt class="col-sm-5"><?= gettext('Email') ?>:</dt>
                                            <dd class="col-sm-7"><?= $child['kidEmail'] ? '<a href="mailto:' . InputUtils::escapeAttribute($child['kidEmail']) . '" target="_blank" rel="noopener noreferrer">' . InputUtils::escapeHTML($child['kidEmail']) . '</a>' : '<span class="text-muted">—</span>' ?></dd>
                                            <dt class="col-sm-5"><?= gettext('Mobile') ?>:</dt>
                                            <dd class="col-sm-7"><?= $child['mobilePhone'] ? '<a href="tel:' . urlencode($child['mobilePhone']) . '">' . InputUtils::escapeHTML($child['mobilePhone']) . '</a>' : '<span class="text-muted">—</span>' ?></dd>
                                            <dt class="col-sm-5"><?= gettext('Home Phone') ?>:</dt>
                                            <dd class="col-sm-7"><?= $child['homePhone'] ? '<a href="tel:' . urlencode($child['homePhone']) . '">' . InputUtils::escapeHTML($child['homePhone']) . '</a>' : '<span class="text-muted">—</span>' ?></dd>
                                        </dl>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mb-3"><i class="fa-solid fa-home me-1"></i> <?= gettext('Address') ?></h6>
                                        <address><?= $address ? InputUtils::escapeHTML($address) : '<span class="text-muted">—</span>' ?></address>
                                        <h6 class="mb-3 mt-4"><i class="fa-solid fa-users me-1"></i> <?= gettext('Parents/Guardians') ?></h6>
                                        <?php if ($child['dadFirstName'] || $child['momFirstName']): ?>
                                        <dl class="row">
                                            <?php if ($child['dadFirstName']): ?>
                                            <dt class="col-sm-5"><?= gettext('Father') ?>:</dt>
                                            <dd class="col-sm-7">
                                                <a href="<?= $sRootPath ?>/people/view/<?= (int) $child['dadId'] ?>"><?= InputUtils::escapeHTML($child['dadFirstName'] . ' ' . $child['dadLastName']) ?></a>
                                                <?php if ($child['dadCellPhone']): ?><br><small><a href="tel:<?= urlencode($child['dadCellPhone']) ?>"><?= InputUtils::escapeHTML($child['dadCellPhone']) ?></a></small><?php endif; ?>
                                                <?php if ($child['dadEmail']): ?><br><small><a href="mailto:<?= InputUtils::escapeAttribute($child['dadEmail']) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($child['dadEmail']) ?></a></small><?php endif; ?>
                                            </dd>
                                            <?php endif; ?>
                                            <?php if ($child['momFirstName']): ?>
                                            <dt class="col-sm-5"><?= gettext('Mother') ?>:</dt>
                                            <dd class="col-sm-7">
                                                <a href="<?= $sRootPath ?>/people/view/<?= (int) $child['momId'] ?>"><?= InputUtils::escapeHTML($child['momFirstName'] . ' ' . $child['momLastName']) ?></a>
                                                <?php if ($child['momCellPhone']): ?><br><small><a href="tel:<?= urlencode($child['momCellPhone']) ?>"><?= InputUtils::escapeHTML($child['momCellPhone']) ?></a></small><?php endif; ?>
                                                <?php if ($child['momEmail']): ?><br><small><a href="mailto:<?= InputUtils::escapeAttribute($child['momEmail']) ?>" target="_blank" rel="noopener noreferrer"><?= InputUtils::escapeHTML($child['momEmail']) ?></a></small><?php endif; ?>
                                            </dd>
                                            <?php endif; ?>
                                        </dl>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="<?= $sRootPath ?>/people/view/<?= $child['kidId'] ?>" class="btn btn-primary"><i class="fa-solid fa-user me-1"></i><?= gettext('View Profile') ?></a>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= gettext('Close') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Birthday Chart Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="fa-solid fa-chart-bar me-2"></i><?= gettext('Birthdays by Month') ?></h5>
            </div>
            <div class="card-body">
                <div class="user-select-none">
                    <div id="bar-chart"
                        data-chart="<?= InputUtils::escapeAttribute($birthDayMonthChartJSON) ?>"
                        data-chart-label="<?= InputUtils::escapeAttribute(gettext('Birthdays by Month')) ?>"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN: About, Properties -->
    <div class="col-lg-4">

        <!-- About Card -->
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title"><i class="fa-solid fa-circle-info me-2"></i><?= gettext('About') ?></h3>
            </div>
            <div class="card-body">
                <?php if ($thisGroup && $thisGroup->getDescription()): ?>
                <p class="text-muted mb-0"><?= InputUtils::escapeHTML($thisGroup->getDescription()) ?></p>
                <?php else: ?>
                <p class="text-muted mb-0"><em><?= gettext('No description set.') ?></em></p>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-3">
                    <div>
                        <i class="fa-solid fa-envelope me-1 text-muted"></i>
                        <span class="text-muted"><?= gettext('Email Export') ?>:</span>
                        <?php if ($thisGroup && $thisGroup->isIncludeInEmailExport()): ?>
                        <span class="badge bg-success-lt text-success"><?= gettext('Included') ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary-lt text-secondary"><?= gettext('Excluded') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned Properties Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-tags me-1"></i> <?= gettext('Properties') ?></h3>
                <span class="badge bg-primary-lt text-primary ms-2"><?= count($rsAssignedRows) ?></span>
            </div>
            <?php if (empty($rsAssignedRows)): ?>
            <div class="card-body text-center text-muted py-4">
                <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                <?= gettext('No properties assigned.') ?>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($rsAssignedRows as $aRow): ?>
                <div class="list-group-item">
                    <div class="d-flex align-items-start">
                        <div class="me-auto">
                            <div class="fw-bold"><?= InputUtils::escapeHTML($aRow['pro_Name']) ?></div>
                            <span class="badge bg-secondary-lt text-secondary me-1"><?= InputUtils::escapeHTML($aRow['prt_Name']) ?></span>
                            <?php if (!empty($aRow['r2p_Value'])): ?>
                            <span class="text-muted"><?= InputUtils::escapeHTML($aRow['r2p_Value']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($aRow['pro_Prompt'])): ?>
                            <div class="text-muted small mt-1"><i class="fa-solid fa-circle-question me-1"></i><?= InputUtils::escapeHTML($aRow['pro_Prompt']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if ($bCanManageGroups && !empty($availableProperties)): ?>
            <div class="card-footer">
                <div class="text-muted small">
                    <i class="fa-solid fa-info-circle me-1"></i><?= gettext('Manage properties from the') ?>
                    <a href="<?= $sRootPath ?>/groups/view/<?= $iGroupId ?>"><?= gettext('full group view') ?></a>.
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Group-Specific Properties Card -->
        <?php if ($thisGroup && $thisGroup->getHasSpecialProps() && is_object($groupSpecificProps) && $groupSpecificProps->count() > 0): ?>
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="fa-solid fa-sliders me-1"></i> <?= gettext('Member Properties') ?></h3>
                <span class="badge bg-info-lt text-info ms-2"><?= $groupSpecificProps->count() ?></span>
            </div>
            <div class="list-group list-group-flush">
                <?php foreach ($groupSpecificProps as $prop): ?>
                <div class="list-group-item">
                    <div class="fw-bold"><?= InputUtils::escapeHTML($prop->getName()) ?></div>
                    <span class="badge bg-secondary-lt text-secondary me-1"><?= InputUtils::escapeHTML($aPropTypes[$prop->getTypeId()] ?? '') ?></span>
                    <?php if ($prop->getDescription()): ?>
                    <span class="text-muted small"><?= InputUtils::escapeHTML($prop->getDescription()) ?></span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Events Card -->
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center">
                <h3 class="card-title m-0"><i class="ti ti-calendar me-1"></i> <?= gettext('Events') ?></h3>
                <span class="badge bg-primary-lt text-primary ms-2"><?= count($groupEvents) ?></span>
                <?php if ($todayEvent !== null): ?>
                <a href="<?= $sRootPath ?>/event/checkin/<?= (int) $todayEvent->getId() ?>"
                   class="btn btn-sm btn-success ms-auto">
                    <i class="ti ti-clipboard-check me-1"></i><?= gettext('Take Attendance') ?>
                </a>
                <?php else: ?>
                <button
                    type="button"
                    class="btn btn-sm btn-primary ms-auto"
                    id="quickCreateTodaysEventBtn"
                    data-group-id="<?= (int) $iGroupId ?>"
                    title="<?= gettext("Create today's class event and link it to this class for kiosk check-in") ?>">
                    <i class="ti ti-plus me-1"></i><?= gettext("Create Today's Event") ?>
                </button>
                <?php endif; ?>
            </div>

            <?php if ($todayEvent !== null): ?>
            <!-- Today's event banner -->
            <div class="card-body py-2 border-bottom bg-success-lt">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="ti ti-calendar-check me-1 text-success"></i>
                        <strong><?= InputUtils::escapeHTML($todayEvent->getTitle()) ?></strong>
                        <span class="text-muted ms-2"><?= $todayEvent->getStart() ? $todayEvent->getStart()->format('g:i A') : '' ?></span>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-success"><?= $todayEventAttendance ?> <?= gettext('checked in') ?></span>
                        <span class="text-muted small"><?= gettext('of') ?> <?= $totalStudents ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($groupEvents) === 0): ?>
            <div class="card-body text-center text-muted py-4">
                <i class="ti ti-calendar-off mb-2 d-block" style="font-size: 2rem;"></i>
                <p class="mb-2"><?= gettext('No events linked to this class.') ?></p>
                <p class="small text-muted mb-0">
                    <?= gettext("Use the button above to create today's event in one click. It will be auto-linked to this class so a Kiosk can pull the roster.") ?>
                </p>
            </div>
            <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($groupEvents as $evt):
                    $isActive  = !$evt->getInActive();
                    $hasKiosk  = isset($kioskEventSet[(int) $evt->getId()]);
                    $startDate = $evt->getStart() ? $evt->getStart()->format('M j, Y') : '';
                    $startTime = $evt->getStart() ? $evt->getStart()->format('g:i A') : '';
                    $eventId   = (int) $evt->getId();
                    $isToday   = $todayEvent !== null && $eventId === (int) $todayEvent->getId();
                ?>
                <div class="list-group-item<?= $isToday ? ' bg-success-lt' : '' ?>">
                    <div class="d-flex align-items-center gap-2">
                        <div class="me-auto">
                            <a href="<?= $sRootPath ?>/event/view/<?= $eventId ?>" class="fw-bold text-reset">
                                <?= InputUtils::escapeHTML($evt->getTitle()) ?>
                            </a>
                            <?php if ($startDate): ?>
                            <div>
                                <span class="text-muted small"><?= InputUtils::escapeHTML($startDate) ?></span>
                                <span class="text-muted small ms-1"><?= InputUtils::escapeHTML($startTime) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($hasKiosk): ?>
                        <span class="badge bg-success-lt text-success" title="<?= gettext('Kiosk Enabled') ?>">
                            <i class="ti ti-device-ipad me-1"></i><?= gettext('Kiosk') ?>
                        </span>
                        <?php endif; ?>
                        <?php if (!$isActive): ?>
                        <span class="badge bg-secondary-lt"><?= gettext('Inactive') ?></span>
                        <?php endif; ?>
                        <?php if ($isActive): ?>
                        <a href="<?= $sRootPath ?>/event/checkin/<?= $eventId ?>"
                           class="btn btn-sm btn-outline-primary"
                           title="<?= gettext('Take Attendance') ?>">
                            <i class="ti ti-clipboard-check me-1"></i><?= gettext('Check-in') ?>
                        </a>
                        <?php endif; ?>
                        <a href="<?= $sRootPath ?>/event/editor/<?= $eventId ?>"
                           class="btn btn-sm btn-ghost-secondary"
                           title="<?= gettext('Edit Event') ?>">
                            <i class="ti ti-pencil"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM.currentGroup = <?= (int) $iGroupId ?>;
    window.CRM.currentGroupName = <?= json_encode($iGroupName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= $sRootPath ?>/skin/js/sundayschool-actions.js?v=<?= filemtime(SystemURLs::getDocumentRoot() . '/skin/js/sundayschool-actions.js') ?>"></script>
<script src="<?= SystemURLs::getRootPath() ?>/skin/v2/groups-sundayschool-class-view.min.js"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
