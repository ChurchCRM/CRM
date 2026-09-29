<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\CustomFieldUtils;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';

$hasPropRows = $propMasterRows->count() > 0;
?>

<?php if (!$hasPropRows): ?>
    <form>
        <p class="text-body-secondary"><?= gettext('This group currently has no properties!  You can add them in the Group Editor.') ?></p>
        <a href="<?= $sRootPath ?>/people/view/<?= $iPersonID ?>" class="btn btn-secondary"><?= gettext('Return to Person Record') ?></a>
    </form>
<?php else: ?>
    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title">
                <?= gettext('Editing') ?>
                <i><?= InputUtils::escapeHTML($thisGroup->getName()) ?></i>
                <?= gettext('data for member') ?>
                <i><?= InputUtils::escapeHTML($thisPerson->getFirstName() . ' ' . $thisPerson->getLastName()) ?></i>
            </h3>
        </div>
        <div class="card-body">
            <?php if (array_key_exists('_db', $aPropErrors)): ?>
                <div class="alert alert-danger"><?= InputUtils::escapeHTML($aPropErrors['_db']) ?></div>
            <?php endif; ?>
            <form method="post" action="<?= $sRootPath ?>/groups/<?= $iGroupID ?>/members/<?= $iPersonID ?>/properties" name="GroupPropEditor">
                <table class="table">
                    <?php foreach ($propMasterRows as $propRow): ?>
                        <?php
                        $prop_Field   = $propRow->getField();
                        $type_ID      = $propRow->getTypeId();
                        $prop_Special = $propRow->getSpecial();
                        $prop_Name    = $propRow->getName();
                        $prop_Description = $propRow->getDescription();

                        $currentFieldData = trim((string) ($aPersonProps[$prop_Field] ?? ''));

                        if ($type_ID == 11) {
                            $prop_Special = null;
                        }
                        ?>
                        <tr>
                            <td><?= InputUtils::escapeHTML($prop_Name) ?>: </td>
                            <td>
                                <?php CustomFieldUtils::renderForm($type_ID, $prop_Field, $currentFieldData, $prop_Special, !$isPostPass); ?>
                                <?php if (array_key_exists($prop_Field, $aPropErrors)): ?>
                                    <span class="text-danger"><?= InputUtils::escapeHTML($aPropErrors[$prop_Field]) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= InputUtils::escapeHTML($prop_Description) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td colspan="3" class="pt-3">
                            <div class="d-flex gap-2">
                                <input type="submit" class="btn btn-primary" value="<?= gettext('Save') ?>" name="GroupPropSubmit">
                                <a href="<?= $sRootPath ?>/people/view/<?= $iPersonID ?>" class="btn btn-secondary"><?= gettext('Cancel') ?></a>
                            </div>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>
<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    document.addEventListener('DOMContentLoaded', function() {
        if (window.CRM && window.CRM.formUtils && typeof window.CRM.formUtils.initializeAllPhoneMaskToggles === 'function') {
            try {
                window.CRM.formUtils.initializeAllPhoneMaskToggles();
            } catch (e) {
                // silent
            }
        }
    });
</script>
<?php endif; ?>
<?php
require SystemURLs::getDocumentRoot() . '/Include/Footer.php';
