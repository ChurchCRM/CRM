<?php

declare(strict_types=1);

use ChurchCRM\Service\TelemetryService;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

Assert::same('Ordinary warning.', TelemetryService::scrubLogMessage('Ordinary warning.'));
Assert::same('Email [email] failed', TelemetryService::scrubLogMessage('Email first.last+tag@example.org failed'));
Assert::same('Email [email] failed', TelemetryService::scrubLogMessage("Email o'brien@example.org failed"));
Assert::same('Query failed: [database error]', TelemetryService::scrubLogMessage("Query failed: SQLSTATE[42S02]: Base table not found: 1146 Table 'churchcrm_dev.email_log_eml' doesn't exist"));
Assert::same('Query failed: [database error]', TelemetryService::scrubLogMessage("Query failed: SQLSTATE[42S02]: Table churchcrm_dev.email_log_eml missing. Retry later."));
Assert::same('Insert failed: [database error]', TelemetryService::scrubLogMessage("Insert failed: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'Dr. Smith' for key 'usr_UserName'"));
Assert::same('[database error]', TelemetryService::scrubLogMessage("SQLSTATE[42000]: Syntax error\nSELECT * FROM churchcrm_dev.email_log_eml"));
Assert::same('[database error]', TelemetryService::scrubLogMessage('SQLSTATE[42000]: private.table failed. SQLSTATE[23000]: other.table failed.'));
Assert::same(str_repeat('é', 500), TelemetryService::scrubLogMessage(str_repeat('é', 501)));
