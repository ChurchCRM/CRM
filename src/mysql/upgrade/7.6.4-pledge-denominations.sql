-- Migration: add pledge_denominations_pdem table (issue #9376)
--
-- PR #8482 added code in FinancialService that INSERT/DELETE/SELECTs from
-- pledge_denominations_pdem (cash-denomination counts per pledge GroupKey/deposit)
-- but never created the table.  Every PUT /api/payments/{groupKey} call was failing
-- with "Table 'churchcrm.pledge_denominations_pdem' doesn't exist".
--
-- Column notes:
--   plg_depID        matches the name used in FinancialService.php lines 360 & 592
--                    (NOT pdem_depID — the column name mirrors pledge_plg.plg_depID)

CREATE TABLE IF NOT EXISTS `pledge_denominations_pdem` (
  `pdem_id`                  mediumint(9) unsigned NOT NULL AUTO_INCREMENT,
  `pdem_plg_GroupKey`        varchar(64)           NOT NULL,
  `plg_depID`                mediumint(9) unsigned DEFAULT NULL,
  `pdem_denominationID`      mediumint(9)          DEFAULT NULL,
  `pdem_denominationQuantity` int(11)               DEFAULT NULL,
  PRIMARY KEY (`pdem_id`),
  KEY `pdem_groupkey_idx`           (`pdem_plg_GroupKey`),
  KEY `pdem_deposit_denom_idx`      (`plg_depID`, `pdem_denominationID`),
  UNIQUE KEY `pdem_groupkey_denom_uidx` (`pdem_plg_GroupKey`, `pdem_denominationID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
