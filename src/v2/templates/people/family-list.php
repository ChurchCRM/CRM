<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;

$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Family List');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="card">
    <div class="card-body">
        <table id="families" class="table table-striped table-bordered data-table w-100">
            <thead>
                <tr>
                    <th><?= gettext('Actions') ?></th>
                    <?php
                    $columns = json_decode(SystemConfig::getValue('sFamilyListColumns'), null, 512, JSON_THROW_ON_ERROR);
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
                                <button type="button" class="btn btn-xs btn-default" title="<?= gettext('View') ?>"><i class="fa-solid fa-search-plus"></i></button>
                            </a>
                            <a href='<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>'>
                                <button type="button" class="btn btn-xs btn-default" title="<?= gettext('Edit') ?>"><i class="fa-solid fa-pen"></i></button>
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
                                <a class="AddToPeopleCart" data-cartpersonid="<?= $family->getId() ?>-fam" data-familyid="<?= $family->getId() ?>">
                                    <button type="button" class="btn btn-xs btn-primary" title="<?= gettext('Add to Cart') ?>"><i class="fa-solid fa-cart-plus"></i></button>
                                </a>
                            <?php } else { ?>
                                <a class="RemoveFromPeopleCart" data-cartpersonid="<?= $family->getId() ?>-fam" data-familyid="<?= $family->getId() ?>">
                                    <button type="button" class="btn btn-xs btn-danger" title="<?= gettext('Remove from Cart') ?>"><i class="fa-solid fa-shopping-cart"></i></button>
                                </a>
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
        
        // Handle cart button clicks in family list
        $(document).on('click', '.AddToPeopleCart', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const $button = $(this);
            const familyId = $button.data('familyid');
            
            if (window.CRM && window.CRM.cartManager && familyId) {
                window.CRM.cartManager.addFamily(familyId, {
                    callback: function() {
                        // Update button to show remove state after successful add
                        $button.removeClass('AddToPeopleCart').addClass('RemoveFromPeopleCart');
                        $button.find('button').removeClass('btn-primary').addClass('btn-danger');
                        $button.find('i').removeClass('fa-cart-plus').addClass('fa-shopping-cart');
                        $button.find('button').attr('title', '<?= gettext('Remove from Cart') ?>');
                    }
                });
            }
        });
        
        $(document).on('click', '.RemoveFromPeopleCart', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            const $button = $(this);
            const familyId = $button.data('familyid');
            
            if (window.CRM && window.CRM.cartManager && familyId) {
                window.CRM.cartManager.removeFamily(familyId, { 
                    callback: function() {
                        // Update button to show add state after successful remove
                        $button.removeClass('RemoveFromPeopleCart').addClass('AddToPeopleCart');
                        $button.find('button').removeClass('btn-danger').addClass('btn-primary');
                        $button.find('i').removeClass('fa-shopping-cart').addClass('fa-cart-plus');
                        $button.find('button').attr('title', '<?= gettext('Add to Cart') ?>');
                    }
                });
            }
        });
    });
</script>
<?php
require SystemURLs::getDocumentRoot() .  '/Include/Footer.php';
