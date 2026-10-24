-- ChurchCRM 7.9.0 upgrade script

-- Member sign-in row 0 (#10197): counters for login throttling and temporary lockouts.
CREATE TABLE IF NOT EXISTS `rate_limit_rlm` (
  `rlm_ID`      int(10) unsigned NOT NULL AUTO_INCREMENT,
  `rlm_Bucket`  varchar(64)      NOT NULL,
  `rlm_KeyHash` char(64)         NOT NULL,
  `rlm_Hits`    int(10) unsigned NOT NULL DEFAULT 0,
  `rlm_ResetAt` int(10) unsigned NOT NULL,
  PRIMARY KEY (`rlm_ID`),
  UNIQUE KEY `rlm_bucket_key_uidx` (`rlm_Bucket`, `rlm_KeyHash`),
  KEY `rlm_reset_at_idx` (`rlm_ResetAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
