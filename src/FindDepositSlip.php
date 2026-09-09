<?php

/**
 * Backward-compatibility redirect — FindDepositSlip.php was migrated to the
 * MVC route /finance/deposit/search in ChurchCRM 7.7.0.  Bookmarks, emailed
 * links, and third-party integrations that still point to this URL are
 * silently forwarded to the new location.
 */

require_once __DIR__ . '/Include/Config.php';

\ChurchCRM\Utils\RedirectUtils::redirect('finance/deposit/search');
