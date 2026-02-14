-- ChurchCRM 7.0.0 Database Upgrade
-- Plugin System Migration: Migrate legacy config keys to new prefixed format
-- Note: config_cfg table only has cfg_id, cfg_name, cfg_value (other columns removed in 2.6.0)
-- Plugin settings metadata (labels, types, defaults) are defined in plugin.json files

-- =============================================================================
-- STEP 1: Add new plugin config keys with prefixed naming convention
-- Format: plugin.{pluginId}.{settingKey}
-- Values are empty/defaults - actual metadata is in plugin.json files
-- =============================================================================

-- MailChimp Plugin
INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value)
VALUES 
    (3000, 'plugin.mailchimp.enabled', '0'),
    (3001, 'plugin.mailchimp.apiKey', ''),
    (3002, 'plugin.mailchimp.defaultListId', '');

-- Vonage SMS Plugin  
INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value)
VALUES 
    (3010, 'plugin.vonage.enabled', '0'),
    (3011, 'plugin.vonage.apiKey', ''),
    (3012, 'plugin.vonage.apiSecret', ''),
    (3013, 'plugin.vonage.fromNumber', '');

-- Google Analytics 4 Plugin
INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value)
VALUES 
    (3020, 'plugin.google-analytics.enabled', '0'),
    (3021, 'plugin.google-analytics.trackingId', '');

-- OpenLP Plugin
INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value)
VALUES 
    (3030, 'plugin.openlp.enabled', '0'),
    (3031, 'plugin.openlp.serverUrl', ''),
    (3032, 'plugin.openlp.username', ''),
    (3033, 'plugin.openlp.password', ''),
    (3034, 'plugin.openlp.allowSelfSigned', '0');

-- Gravatar Plugin
INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value)
VALUES 
    (3040, 'plugin.gravatar.enabled', '0'),
    (3041, 'plugin.gravatar.defaultImage', 'blank');

-- =============================================================================
-- STEP 2: Migrate existing values from legacy config keys to new prefixed keys
-- =============================================================================

-- Migrate MailChimp API Key - enable plugin if legacy key was configured
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sMailChimpApiKey'
SET new_cfg.cfg_value = IF(old_cfg.cfg_value != '', '1', '0')
WHERE new_cfg.cfg_name = 'plugin.mailchimp.enabled' AND old_cfg.cfg_value != '';

UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sMailChimpApiKey'  
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.mailchimp.apiKey' AND old_cfg.cfg_value != '';

-- Migrate Nexmo/Vonage settings (from either legacy key format)
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name IN ('sNexmoAPIKey', 'sVonageAPIKey')
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.vonage.apiKey' AND old_cfg.cfg_value != '';

UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name IN ('sNexmoAPISecret', 'sVonageAPISecret')
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.vonage.apiSecret' AND old_cfg.cfg_value != '';

UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name IN ('sNexmoFromNumber', 'sVonageFromNumber')
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.vonage.fromNumber' AND old_cfg.cfg_value != '';

-- Enable Vonage plugin if any Vonage/Nexmo API key was configured
UPDATE config_cfg AS new_cfg
SET new_cfg.cfg_value = '1'
WHERE new_cfg.cfg_name = 'plugin.vonage.enabled' 
AND EXISTS (
    SELECT 1 FROM (SELECT cfg_value FROM config_cfg WHERE cfg_name IN ('sNexmoAPIKey', 'sVonageAPIKey') AND cfg_value != '') AS subq
);

-- Migrate Google Analytics Tracking ID
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sGoogleTrackingID'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.google-analytics.trackingId' AND old_cfg.cfg_value != '';

UPDATE config_cfg AS new_cfg
SET new_cfg.cfg_value = '1'
WHERE new_cfg.cfg_name = 'plugin.google-analytics.enabled' 
AND EXISTS (SELECT 1 FROM (SELECT cfg_value FROM config_cfg WHERE cfg_name = 'sGoogleTrackingID' AND cfg_value != '') AS subq);

-- Migrate OpenLP Server URL
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sOLPURL'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.openlp.serverUrl' AND old_cfg.cfg_value != '' AND old_cfg.cfg_value != 'http://192.168.1.1:4316';

-- Migrate OpenLP Username
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sOLPUserName'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.openlp.username' AND old_cfg.cfg_value != '';

-- Migrate OpenLP Password
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'sOLPPassword'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.openlp.password' AND old_cfg.cfg_value != '';

UPDATE config_cfg AS new_cfg
SET new_cfg.cfg_value = '1'
WHERE new_cfg.cfg_name = 'plugin.openlp.enabled' 
AND EXISTS (SELECT 1 FROM (SELECT cfg_value FROM config_cfg WHERE cfg_name = 'sOLPURL' AND cfg_value != '' AND cfg_value != 'http://192.168.1.1:4316') AS subq);

-- Migrate Gravatar enabled setting
UPDATE config_cfg AS new_cfg
JOIN config_cfg AS old_cfg ON old_cfg.cfg_name = 'bEnableGravatarPhotos'
SET new_cfg.cfg_value = old_cfg.cfg_value
WHERE new_cfg.cfg_name = 'plugin.gravatar.enabled';

-- =============================================================================
-- STEP 3: Remove deprecated legacy config keys
-- =============================================================================

-- Remove old Nexmo keys (replaced by plugin.vonage.*)
DELETE FROM config_cfg WHERE cfg_name IN ('sNexmoAPIKey', 'sNexmoAPISecret', 'sNexmoFromNumber');

-- Remove old Vonage keys (replaced by plugin.vonage.*)
DELETE FROM config_cfg WHERE cfg_name IN ('sVonageAPIKey', 'sVonageAPISecret', 'sVonageFromNumber');

-- Remove old MailChimp key (replaced by plugin.mailchimp.*)
DELETE FROM config_cfg WHERE cfg_name = 'sMailChimpApiKey';

-- Remove old Google Analytics key (replaced by plugin.google-analytics.*)
DELETE FROM config_cfg WHERE cfg_name = 'sGoogleTrackingID';

-- Remove old OpenLP keys (replaced by plugin.openlp.*)
DELETE FROM config_cfg WHERE cfg_name IN ('sOLPURL', 'sOLPUserName', 'sOLPPassword');

-- Remove old Gravatar key (replaced by plugin.gravatar.*)
DELETE FROM config_cfg WHERE cfg_name = 'bEnableGravatarPhotos';
