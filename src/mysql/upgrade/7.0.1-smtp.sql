-- ChurchCRM 7.0.1 Database Upgrade
-- SMTP Plugin Migration: Move legacy SMTP config keys to new plugin framework
-- Legacy keys: sSMTPHost, bSMTPAuth, sSMTPUser, sSMTPPass, iSMTPTimeout,
--              bPHPMailerAutoTLS, sPHPMailerSMTPSecure
-- New keys: plugin.smtp.{host,port,auth,username,password,smtpSecure,autoTLS,timeout}

-- =============================================================================
-- STEP 1: Insert new plugin.smtp.* config keys with default values
-- =============================================================================

INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value)
VALUES
    (3070, 'plugin.smtp.enabled',    '0'),
    (3071, 'plugin.smtp.host',       ''),
    (3072, 'plugin.smtp.port',       '587'),
    (3073, 'plugin.smtp.auth',       '0'),
    (3074, 'plugin.smtp.username',   ''),
    (3075, 'plugin.smtp.password',   ''),
    (3076, 'plugin.smtp.smtpSecure', ''),
    (3077, 'plugin.smtp.autoTLS',    '0'),
    (3078, 'plugin.smtp.timeout',    '10'),
    (3079, 'plugin.smtp.bccAddress', '');

-- =============================================================================
-- STEP 2: Migrate values from legacy keys to new plugin.smtp.* keys
-- =============================================================================

-- Migrate SMTP host (strip embedded port if present, e.g. "smtp.gmail.com:587")
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sSMTPHost'
SET new_cfg.cfg_value = SUBSTRING_INDEX(old_cfg.cfg_value, ':', 1)
WHERE new_cfg.cfg_name = 'plugin.smtp.host' AND old_cfg.cfg_value != '';

-- Migrate port from embedded host:port format when present
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sSMTPHost'
SET new_cfg.cfg_value = SUBSTRING_INDEX(old_cfg.cfg_value, ':', -1)
WHERE new_cfg.cfg_name = 'plugin.smtp.port'
    AND old_cfg.cfg_value LIKE '%:%'
    AND SUBSTRING_INDEX(old_cfg.cfg_value, ':', -1) REGEXP '^[0-9]+$';

-- Migrate SMTP authentication flag
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'bSMTPAuth'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.smtp.auth';

-- Migrate SMTP username
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sSMTPUser'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.smtp.username' AND old_cfg.cfg_value != '';

-- Migrate SMTP password
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sSMTPPass'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.smtp.password' AND old_cfg.cfg_value != '';

-- Migrate SMTP encryption (legacy stored 'tls', 'ssl', or ' ' for none; trim to '')
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sPHPMailerSMTPSecure'
SET new_cfg.cfg_value = TRIM(old_cfg.cfg_value)
WHERE new_cfg.cfg_name = 'plugin.smtp.smtpSecure';

-- Migrate Auto TLS flag
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'bPHPMailerAutoTLS'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.smtp.autoTLS';

-- Migrate SMTP timeout
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'iSMTPTimeout'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.smtp.timeout' AND old_cfg.cfg_value != '';

-- Migrate BCC copy address
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sToEmailAddress'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.smtp.bccAddress' AND old_cfg.cfg_value != '';

-- Enable the SMTP plugin if a host was previously configured
UPDATE config_cfg AS new_cfg
SET new_cfg.cfg_value = '1'
WHERE new_cfg.cfg_name = 'plugin.smtp.enabled'
    AND EXISTS (
        SELECT 1 FROM (
            SELECT cfg_value FROM config_cfg
            WHERE cfg_name = 'sSMTPHost' AND cfg_value != ''
        ) AS subq
    );

-- =============================================================================
-- STEP 3: Delete legacy SMTP config keys (values now live in plugin.smtp.*)
-- =============================================================================

DELETE FROM config_cfg WHERE cfg_name IN (
    'sSMTPHost',
    'bSMTPAuth',
    'sSMTPUser',
    'sSMTPPass',
    'iSMTPTimeout',
    'bPHPMailerAutoTLS',
    'sPHPMailerSMTPSecure',
    'sToEmailAddress'
);
