<?php

use ChurchCRM\dto\SystemURLs;

$sPageTitle = gettext('Family Listing');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fa-solid fa-filter"></i> <?= gettext('Filters') ?></h3>
    </div>
    <div class="card-body">
        <form id="family-filters" method="get" action="<?= SystemURLs::getRootPath() ?>/v2/family/">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="filterCity" class="small text-muted"><?= gettext('City') ?></label>
                    <input type="text" class="form-control" id="filterCity" name="City" value="<?= htmlspecialchars($filterCity ?? '') ?>" placeholder="<?= gettext('City') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="filterState" class="small text-muted"><?= gettext('State') ?></label>
                    <input type="text" class="form-control" id="filterState" name="State" value="<?= htmlspecialchars($filterState ?? '') ?>" placeholder="<?= gettext('State') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label for="familyActiveStatus" class="small text-muted"><?= gettext('Status') ?></label>
                    <select id="familyActiveStatus" name="familyActiveStatus" class="form-control">
                        <option value="active" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'active') ? 'selected' : '' ?>><?= gettext('Active') ?></option>
                        <option value="inactive" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'inactive') ? 'selected' : '' ?>><?= gettext('Inactive') ?></option>
                        <option value="all" <?= (isset($familyActiveStatus) && $familyActiveStatus === 'all') ? 'selected' : '' ?>><?= gettext('All') ?></option>
                    </select>
                </div>
                <div class="col-md-3 mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2"><?= gettext('Apply') ?></button>
                    <a id="clear-filters" class="btn btn-secondary" href="#"><?= gettext('Clear') ?></a>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><?= gettext('Families') ?></h3>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0" id="families">
            <thead>
                <tr>
                    <th><?= gettext('Family Name') ?></th>
                    <th><?= gettext('Contact') ?></th>
                    <th><?= gettext('City/State') ?></th>
                    <th><?= gettext('Members') ?></th>
                    <th><?= gettext('Active') ?></th>
                    <th class="text-right" width="150"><?= gettext('Actions') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($families as $family) {
                    /* @var Family $family */
                    $familyMembers = $family->getPeople();
                    $memberCount = count($familyMembers);
                    
                    // Check if all family members are in cart
                    $isInCart = false;
                    if (isset($_SESSION['aPeopleCart']) && $memberCount > 0) {
                        $allInCart = true;
                        foreach ($familyMembers as $member) {
                            if (!in_array($member->getId(), $_SESSION['aPeopleCart'], false)) {
                                $allInCart = false;
                                break;
                            }
                        }
                        $isInCart = $allInCart;
                    }
                ?>
                <tr>
                    <td>
                        <?= $family->getLinkHtml(true, true) ?>
                    </td>
                    <td>
                        <?php if ($family->getEmail()): ?>
                            <a href="mailto:<?= htmlspecialchars($family->getEmail()) ?>"><?= htmlspecialchars($family->getEmail()) ?></a>
                        <?php endif; ?>
                        <?php if ($family->getHomePhone()): ?>
                            <br><?= htmlspecialchars($family->getHomePhone()) ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $family->getCityStateShort() ?></td>
                    <td><?= $memberCount ?></td>
                    <td>
                            <?php if (!$family->isActive()): ?>
                                <span class="badge badge-danger"><?= gettext('Inactive') ?></span>
                            <?php else: ?>
                                <span class="badge badge-success"><?= gettext('Active') ?></span>
                            <?php endif; ?>
                    </td>
                    <td class="text-right">
                        <a href='<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>' class="btn btn-sm btn-warning" title="<?= gettext('Edit') ?>">
                            <i class="fa-solid fa-pen"></i>
                        </a>
                        <?php if (!$isInCart && $memberCount > 0) { ?>
                            <button type="button" class="AddToCart btn btn-sm btn-primary" data-cart-id="<?= $family->getId() ?>" data-cart-type="family" title="<?= gettext('Add to Cart') ?>">
                                <i class="fa-solid fa-cart-plus"></i>
                            </button>
                        <?php } elseif ($isInCart && $memberCount > 0) { ?>
                            <button type="button" class="RemoveFromCart btn btn-sm btn-danger" data-cart-id="<?= $family->getId() ?>" data-cart-type="family" title="<?= gettext('Remove from Cart') ?>">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        <?php } ?>
                    </td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// Load compiled webpack asset for family list interactions
echo '<script src="' . SystemURLs::getRootPath() . '/skin/v2/people-family-list.min.js"></script>';
?>
<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
