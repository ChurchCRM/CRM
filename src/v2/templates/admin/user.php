<?php


use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\dto\Classification;

require SystemURLs::getDocumentRoot() . '/Include/SimpleConfig.php';

//Set the page title
$sPageTitle = gettext("User API") . " - " . $user->getFullName();
include SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

    <div class="box">
        <div class="box-header">
            <h3>Api Key</h3>
        </div>
        <div class="box-body">
            <form>
                <input id="apiKey" type="text" readonly value="<?= $user->getApiKey() ?>"/>
            </form>

            <br/>

            <p/>

            <a id="regenApiKey" class="btn btn-warning"><i class="fa fa-repeat"></i> Regen API Key </a>
        </div>
    </div>

<?php include SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
