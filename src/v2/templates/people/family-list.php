<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;

$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Family List');
require SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="card">
    <div class="card-body">
        <table id="families" class="table table-striped table-bordered data-table w-100">
            <thead>
                <tr>
                    <th><?= gettext('Actions') ?></th>
                    <?php
                    // Family list column definitions - defines which columns appear and their data source
                    $columns = [
                        (object) ['name' => 'Name', 'displayFunction' => 'getName', 'visible' => 'true'],
                        (object) ['name' => 'Address', 'displayFunction' => 'getAddress', 'visible' => 'true'],
                        (object) ['name' => 'Home Phone', 'displayFunction' => 'getHomePhone', 'visible' => 'true'],
                        (object) ['name' => 'Email', 'displayFunction' => 'getEmail', 'visible' => 'true'],
                        (object) ['name' => 'Created', 'displayFunction' => 'getDateEntered', 'visible' => 'true'],
                        (object) ['name' => 'Edited', 'displayFunction' => 'getDateLastEdited', 'visible' => 'true'],
                    ];
                    foreach ($columns as $column) {
                        if ($column->visible === 'true') {
                            echo '<th>' . gettext($column->name) . '</th>';
                        }
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <!--Populate the table with family details -->
                <?php foreach ($families as $family) {
                    /* @var Family $family */
                    ?>
                    <tr>
                        <td>
                            <a href='<?= SystemURLs::getRootPath() ?>/v2/family/<?= $family->getId() ?>'>
                                <button type="button" class="btn btn-sm btn-info" title="<?= gettext('View') ?>"><i class="fa-solid fa-eye fa-sm"></i></button>
                            </a>
                            <a href='<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>'>
                                <button type="button" class="btn btn-sm btn-warning" title="<?= gettext('Edit') ?>"><i class="fa-solid fa-pen fa-sm"></i></button>
                            </a>
                            <?php 
                                // Check if all family members are in cart
                                $isInCart = false;
                                if (isset($_SESSION['aPeopleCart'])) {
                                    $familyMembers = $family->getPeople();
                                    if (count($familyMembers) > 0) {
                                        $allInCart = true;
                                        foreach ($familyMembers as $member) {
                                            if (!in_array($member->getId(), $_SESSION['aPeopleCart'], false)) {
                                                $allInCart = false;
                                                break;
                                            }
                                        }
                                        $isInCart = $allInCart;
                                    }
                                }
                            ?>
                            <?php if (!$isInCart) { ?>
                                <button type="button" class="AddToCart btn btn-sm btn-primary" data-cart-id="<?= $family->getId() ?>" data-cart-type="family" title="<?= gettext('Add to Cart') ?>"><i class="fa-solid fa-cart-plus fa-sm"></i></button>
                            <?php } else { ?>
                                <button type="button" class="RemoveFromCart btn btn-sm btn-danger" data-cart-id="<?= $family->getId() ?>" data-cart-type="family" title="<?= gettext('Remove from Cart') ?>"><i class="fa-solid fa-times fa-sm"></i></button>
                            <?php } ?>
                        </td>

                        <?php
                        foreach ($columns as $column) {
                            if ($column->visible === 'true') {
                                if (str_starts_with($column->displayFunction, 'getDate')) {
                                    $columnData = [$family, $column->displayFunction](SystemConfig::getValue('sDateFormatLong'));
                                } else {
                                    $columnData = [$family, $column->displayFunction]();
                                }
                                echo '<td>' . $columnData . '</td>';
                            }
                        }
                        ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    $(document).ready(function() {
        $('#families').DataTable(window.CRM.plugin.dataTable);
    });
</script>
<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
