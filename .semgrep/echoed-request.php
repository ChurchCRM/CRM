<?php
use ChurchCRM\Utils\InputUtils;

// ok: churchcrm.php.echoed-request
echo InputUtils::escapeHTML($_GET['name']);
// ok: churchcrm.php.echoed-request
echo InputUtils::escapeAttribute($_POST['title']);
// ok: churchcrm.php.echoed-request
echo (int) $_REQUEST['id'];

// ruleid: churchcrm.php.echoed-request
echo $_GET['name'];
// ruleid: churchcrm.php.echoed-request
echo "Value: " . $_POST['title'];
// ruleid: churchcrm.php.echoed-request
echo InputUtils::sanitizeText($_REQUEST['name']);
// ruleid: churchcrm.php.echoed-request
echo InputUtils::jsonEncodeForScript($_POST['payload']);
