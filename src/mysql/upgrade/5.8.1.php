<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;

$logger = LoggerUtils::getAppLogger();

$logger->info('Deleting obsolete files');

// 5.6.0
unlink(SystemURLs::getDocumentRoot() . '/Menu.php');
// 5.8.0
unlink(SystemURLs::getDocumentRoot() . '/ChurchCRM/Emails/users/PasswordChangeEmail.php');
unlink(SystemURLs::getDocumentRoot() . '/ChurchCRM/Interfaces/SystemCalendar.php');
unlink(SystemURLs::getDocumentRoot() . '/views/email/BaseEmail.html');

$logger->info('Finished deleting obsolete files');
