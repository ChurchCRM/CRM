<?php

/**
 * ChurchCRM copyright line, shared by the logged-in and logged-out footers.
 *
 * Required input: $copyrightAttribution (the SystemURLs::attributed() source key).
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Utils\InputUtils;

?>
<?= InputUtils::escapeHTML(gettext('Copyright')) ?> &copy; <?= InputUtils::escapeHTML((string) SystemService::getCopyrightDate()) ?> 
<a href="<?= InputUtils::escapeAttribute(SystemURLs::attributed('https://churchcrm.io', $copyrightAttribution)) ?>" target="_blank" rel="noopener noreferrer">Church<b>CRM</b></a>.
<?= InputUtils::escapeHTML(gettext('All rights reserved')) ?>.
