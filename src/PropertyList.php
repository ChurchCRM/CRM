<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Get the type to display
$sType = InputUtils::legacyFilterInput($_GET['Type'], 'char', 1);

// Based on the type, set the TypeName
switch ($sType) {
    case 'p':
        $sTypeName = gettext('Person');
        break;

    case 'f':
        $sTypeName = gettext('Family');
        break;

    case 'g':
        $sTypeName = gettext('Group');
        break;

    default:
        RedirectUtils::redirect('v2/dashboard');
        break;
}

$sPageTitle = $sTypeName . ' ' . gettext('Property List');

// Get the properties
$sSQL = "SELECT * FROM property_pro, propertytype_prt WHERE prt_ID = pro_prt_ID AND pro_Class = '" . $sType . "' ORDER BY prt_Name,pro_Name";
$rsProperties = RunQuery($sSQL);

require_once __DIR__ . '/Include/Header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) { ?>
                <a class="btn btn-primary" href="PropertyEditor.php?Type=<?= InputUtils::escapeAttribute($sType) ?>">
                    <i class="fa fa-plus"></i> <?= gettext('Add a New') ?> <?= $sTypeName ?> <?= gettext('Property') ?>
                </a>
            <?php } ?>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= gettext('Name') ?></th>
                        <th><?= gettext('A') ?> <?= $sTypeName ?> <?= gettext('with this Property...') ?></th>
                        <th><?= gettext('Prompt') ?></th>
                        <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) { ?>
                            <th class="text-center" style="width: 120px;"><?= gettext('Actions') ?></th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Initialize the row shading
                    $iPreviousPropertyType = -1;
                    $sBlankLine = '';

                    // Loop through the records
                    while ($aRow = mysqli_fetch_array($rsProperties)) {
                        $pro_Prompt = '';
                        $pro_Description = '';
                        extract($aRow);

                        // Did the Type change?
                        if ($iPreviousPropertyType != $prt_ID) {
                            // Write the header row
                            if ($sBlankLine !== '') {
                                echo $sBlankLine;
                            }
                            echo '<tr class="table-secondary"><td colspan="' . (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled() ? 4 : 3) . '"><strong>' . InputUtils::escapeHTML($prt_Name) . '</strong></td></tr>';
                            $sBlankLine = '';

                            // Reset the row color
                        }

                        echo '<tr>';
                        echo '<td>' . InputUtils::escapeHTML($pro_Name) . '</td>';
                        echo '<td>';
                        if (strlen($pro_Description) > 0) {
                            echo '<small class="text-muted">...' . InputUtils::escapeHTML($pro_Description) . '</small>';
                        }
                        echo '</td>';
                        echo '<td>';
                        if (strlen($pro_Prompt) > 0) {
                            echo '<small>' . InputUtils::escapeHTML($pro_Prompt) . '</small>';
                        }
                        echo '</td>';
                        if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
                            echo '<td class="text-center">';
                            echo '<div class="btn-group btn-group-sm" role="group">';
                            echo '<a class="btn btn-outline-primary" href="PropertyEditor.php?PropertyID=' . $pro_ID . '&Type=' . InputUtils::escapeAttribute($sType) . '" title="' . gettext('Edit') . '">';
                            echo '<i class="fa fa-edit"></i>';
                            echo '</a>';
                            echo '<a class="btn btn-outline-danger" href="PropertyDelete.php?PropertyID=' . $pro_ID . '&Type=' . InputUtils::escapeAttribute($sType) . '" title="' . gettext('Delete') . '">';
                            echo '<i class="fa fa-trash"></i>';
                            echo '</a>';
                            echo '</div>';
                            echo '</td>';
                        }
                        echo '</tr>';

                        // Store the PropertyType
                        $iPreviousPropertyType = $prt_ID;
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/Include/Footer.php';
