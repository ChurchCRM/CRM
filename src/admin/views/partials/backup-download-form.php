<?php

use ChurchCRM\Utils\CSRFUtils;

?>
<form id="backup-download-form" method="post" class="d-none">
    <?= CSRFUtils::getTokenInputField() ?>
</form>
