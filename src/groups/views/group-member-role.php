<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?= $sRootPath ?>/groups/<?= $iGroupID ?>/members/<?= $iPersonID ?>/role">
        <input type="hidden" name="return" value="<?= InputUtils::escapeAttribute($sReturn) ?>">

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label fw-bold text-end"><?= gettext('Group Name') ?>:</label>
            <div class="col-sm-9 col-form-label"><?= InputUtils::escapeHTML($group->getName()) ?></div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label fw-bold text-end"><?= gettext("Member's Name") ?>:</label>
            <div class="col-sm-9 col-form-label"><?= InputUtils::escapeHTML($person->getLastName()) . ', ' . InputUtils::escapeHTML($person->getFirstName()) ?></div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label fw-bold text-end"><?= gettext('Current Role') ?>:</label>
            <div class="col-sm-9 col-form-label"><?= gettext($sRoleName) ?></div>
        </div>

        <div class="mb-3 row">
            <label class="col-sm-3 col-form-label fw-bold text-end" for="NewRole"><?= gettext('New Role') ?>:</label>
            <div class="col-sm-4">
                <select name="NewRole" id="NewRole" class="form-select">
                    <?php foreach ($allRoles as $role): ?>
                        <option value="<?= (int) $role->getOptionId() ?>"<?= ($iRoleID == $role->getOptionId()) ? ' selected' : '' ?>><?= InputUtils::escapeHTML(gettext($role->getOptionName())) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-3">
            <input type="submit" class="btn btn-primary" name="Submit" value="<?= gettext('Update') ?>">
            <?php if ($sReturn === 'group'): ?>
                <a href="<?= $sRootPath ?>/groups/view/<?= $iGroupID ?>" class="btn btn-secondary ms-2"><?= gettext('Cancel') ?></a>
            <?php else: ?>
                <a href="<?= Person::getViewURIForId($iPersonID) ?>" class="btn btn-secondary ms-2"><?= gettext('Cancel') ?></a>
            <?php endif; ?>
        </div>

    </form>
  </div>
</div>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
