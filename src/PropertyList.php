<?php

require_once __DIR__ . '/Include/Config.php';
require_once __DIR__ . '/Include/Functions.php';

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\RedirectUtils;

// Security: user must have MenuOptions permission to use this page
AuthenticationManager::redirectHomeIfFalse(AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled(), 'MenuOptions');

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
    <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
        //Display the new property link
        echo '<div class="mb-3"><a class="btn btn-primary" href="PropertyEditor.php?Type=' . InputUtils::escapeAttribute($sType) . '"><i class="fa-solid fa-plus"></i> ' . gettext('Add New') . ' ' . $sTypeName . ' ' . gettext('Property') . '</a></div>';
    }
    ?>

    <div class="table-responsive">
        <table class="table table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th><?= gettext('Name') ?></th>
                    <th><?= gettext('A') . ' ' . $sTypeName . ' ' . gettext('with this property...') ?></th>
                    <th><?= gettext('Prompt') ?></th>
                    <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
                        echo '<th class="text-center">' . gettext('Actions') . '</th>';
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <?php
                // Initialize the row shading
                $iPreviousPropertyType = -1;
                $bIsFirstPropertyType = true;

                // Loop through the records
                while ($aRow = mysqli_fetch_array($rsProperties)) {
                    $pro_Prompt = '';
                    $pro_Description = '';
                    extract($aRow);

                    // Did the Type change?
                    if ($iPreviousPropertyType != $prt_ID) {
                        //Write the header row
                        if (!$bIsFirstPropertyType) {
                            echo '</tbody></table></div>';
                        }
                        $bIsFirstPropertyType = false;
                        ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3 mb-2">
                    <strong><?= InputUtils::escapeHTML($prt_Name) ?></strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th><?= gettext('Name') ?></th>
                                <th><?= gettext('A') . ' ' . $sTypeName . ' ' . gettext('with this property...') ?></th>
                                <th><?= gettext('Prompt') ?></th>
                                <?php if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
                                    echo '<th class="text-center">' . gettext('Actions') . '</th>';
                                }
                                ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                    }

                    echo '<tr>';
                    echo '<td>' . InputUtils::escapeHTML($pro_Name) . '</td>';
                    echo '<td>';
                    if (strlen($pro_Description) > 0) {
                        echo '...' . InputUtils::escapeHTML($pro_Description);
                    }
                    echo '</td>';
                    echo '<td>' . InputUtils::escapeHTML($pro_Prompt) . '</td>';
                    if (AuthenticationManager::getCurrentUser()->isMenuOptionsEnabled()) {
                        echo '<td class="text-center"><div class="btn-group btn-group-sm" role="group">';
                        echo '<a class="btn btn-primary" href="PropertyEditor.php?PropertyID=' . InputUtils::escapeAttribute($pro_ID) . '&Type=' . InputUtils::escapeAttribute($sType) . '" title="' . gettext('Edit') . '"><i class="fa-solid fa-edit"></i></a>';
                        echo '<a class="btn btn-danger" href="PropertyDelete.php?PropertyID=' . InputUtils::escapeAttribute($pro_ID) . '&Type=' . InputUtils::escapeAttribute($sType) . '" title="' . gettext('Delete') . '"><i class="fa-solid fa-trash"></i></a>';
                        echo '</div></td>';
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

<?php
require_once __DIR__ . '/Include/Footer.php';
