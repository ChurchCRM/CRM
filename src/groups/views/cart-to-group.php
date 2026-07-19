<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

$sRootPath = $sRootPath ?? SystemURLs::getRootPath();

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<?php if ($cartCount > 0) { ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Cart Contents -->
            <div class="card mb-4">
                <div class="card-status-top bg-primary"></div>
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('People in Cart') ?></h3>
                    <span class="badge bg-primary text-white ms-auto"><?= $cartCount ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter table-hover">
                        <thead>
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('Classification') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($aPeopleInCart as $person) { ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img data-image-entity-type="person" data-image-entity-id="<?= $person->getId() ?>" class="avatar avatar-sm rounded-circle me-2" alt="" />
                                            <a href="<?= InputUtils::escapeAttribute($person->getViewURI()) ?>">
                                                <?= InputUtils::escapeHTML($person->getFullName()) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><?php
                                        $cls = $person->getClsid() ? $person->getClassification() : null;
                                        echo $cls
                                            ? InputUtils::escapeHTML($cls->getOptionName())
                                            : '<em class="text-body-secondary">' . gettext('Unclassified') . '</em>';
                                    ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Add to Group Form -->
            <div class="card">
                <div class="card-status-top bg-green"></div>
                <div class="card-header">
                    <h3 class="card-title"><?= gettext('Add to Group') ?></h3>
                </div>
                <div class="card-body">
                    <form name="CartToGroup" action="<?= $sRootPath ?>/groups/cart-to-group" method="POST">
                        <div class="mb-3">
                            <label class="form-label" for="GroupID"><?= gettext('Select Group') ?></label>
                            <select id="GroupID" name="GroupID" class="form-select" onchange="UpdateRoles();" required>
                                <option value="" disabled selected><?= gettext('Choose a group...') ?></option>
                                <?php foreach ($aGroups as $group) { ?>
                                    <option value="<?= InputUtils::escapeAttribute($group->getID()) ?>">
                                        <?= InputUtils::escapeHTML($group->getName()) ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="GroupRole"><?= gettext('Select Role') ?></label>
                            <select name="GroupRole" id="GroupRole" class="form-select">
                                <option value=""><?= gettext('No Group Selected') ?></option>
                            </select>
                        </div>

                        <div class="card-footer text-end">
                            <button type="button" id="addToGroup" class="btn btn-outline-secondary me-2">
                                <?= gettext('Create Group + ADD Cart') ?>
                            </button>
                            <button type="submit" name="Submit" class="btn btn-primary">
                                <?= gettext('Add to Group') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php } else { ?>
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="mb-3">
                        <i class="ti ti-shopping-cart-off" style="font-size: 3rem; color: var(--tblr-muted);"></i>
                    </div>
                    <h3 class="text-body-secondary"><?= gettext('Your cart is empty!') ?></h3>
                    <p class="text-secondary"><?= gettext('Add people to your cart first, then come back to add them to a group.') ?></p>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<script nonce="<?= SystemURLs::getCSPNonce() ?>" src="<?= $sRootPath ?>/skin/js/GroupRoles.js"></script>

<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
?>
