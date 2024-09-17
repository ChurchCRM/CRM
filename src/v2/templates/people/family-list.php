<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\Family;

$sPageTitle = gettext(ucfirst($sMode)) . ' ' . gettext('Family List');
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
/* @var $families ObjectCollection */
?>

<div class="pull-right">
    <a class="btn btn-success" role="button" href="<?= SystemURLs::getRootPath() ?>/FamilyEditor.php">
        <span class="fa fa-plus" aria-hidden="true"></span><?= gettext('Add Family') ?>
    </a>
</div>
<p><br /><br /></p>
<div class="card">
    <div class="card-body">
        <table id="families" class="table table-striped table-bordered data-table" cellspacing="0" width="100%">
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
                        <td><a href='<?= SystemURLs::getRootPath() ?>/v2/family/<?= $family->getId() ?>'>
                                <i class="fa fa-search-plus"></i>
                            </a>
                            <a href='<?= SystemURLs::getRootPath() ?>/FamilyEditor.php?FamilyID=<?= $family->getId() ?>'>
                                <i class="fas fa-pen"></i>
                            </a>
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
