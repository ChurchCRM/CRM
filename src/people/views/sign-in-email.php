<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

require SystemURLs::getDocumentRoot() . '/Include/Header.php';
?>

<div class="card" id="memberListCard">
    <?php
    $attentionBarHidden = false;
    require __DIR__ . '/partials/email-attention-toolbar.php';
    ?>
    <div class="card-body">
        <table class="table table-vcenter w-100" id="memberListTable"></table>
    </div>
</div>

<script nonce="<?= SystemURLs::getCSPNonce() ?>">
    window.CRM = window.CRM || {};
    window.CRM.signInEmailAttention = <?= InputUtils::jsonEncodeForScript(['csrfToken' => $csrfToken]) ?>;
</script>
<script src="<?= SystemURLs::assetVersioned('/skin/v2/people-sign-in-email.min.js') ?>" nonce="<?= SystemURLs::getCSPNonce() ?>"></script>

<?php require SystemURLs::getDocumentRoot() . '/Include/Footer.php'; ?>
