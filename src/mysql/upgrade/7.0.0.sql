-- ChurchCRM 7.0.0 Database Upgrade
-- Plugin System Migration: Migrate Nexmo to Vonage and remove deprecated keys

-- Add new Vonage SMS config keys (plugin system uses these)
-- Copy existing Nexmo values to new Vonage keys for seamless migration
INSERT INTO config_cfg (cfg_id, cfg_name, cfg_value, cfg_type, cfg_default, cfg_tooltip, cfg_section)
SELECT 2080, 'sVonageAPIKey', cfg_value, 'text', '', 'Vonage SMS API Key', 'Integration'
FROM config_cfg WHERE cfg_name = 'sNexmoAPIKey'
ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value);

INSERT INTO config_cfg (cfg_id, cfg_name, cfg_value, cfg_type, cfg_default, cfg_tooltip, cfg_section)
SELECT 2081, 'sVonageAPISecret', cfg_value, 'password', '', 'Vonage SMS API Secret', 'Integration'
FROM config_cfg WHERE cfg_name = 'sNexmoAPISecret'
ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value);

INSERT INTO config_cfg (cfg_id, cfg_name, cfg_value, cfg_type, cfg_default, cfg_tooltip, cfg_section)
SELECT 2082, 'sVonageFromNumber', cfg_value, 'text', '', 'Vonage SMS From Number (E.164 format)', 'Integration'
FROM config_cfg WHERE cfg_name = 'sNexmoFromNumber'
ON DUPLICATE KEY UPDATE cfg_value = VALUES(cfg_value);

-- If no Nexmo values exist, insert empty Vonage keys
INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value, cfg_type, cfg_default, cfg_tooltip, cfg_section)
VALUES 
    (2080, 'sVonageAPIKey', '', 'text', '', 'Vonage SMS API Key', 'Integration'),
    (2081, 'sVonageAPISecret', '', 'password', '', 'Vonage SMS API Secret', 'Integration'),
    (2082, 'sVonageFromNumber', '', 'text', '', 'Vonage SMS From Number (E.164 format)', 'Integration');

-- Remove deprecated Nexmo config keys (migrated to Vonage above)
DELETE FROM config_cfg WHERE cfg_name IN ('sNexmoAPIKey', 'sNexmoAPISecret', 'sNexmoFromNumber');

-- Update config descriptions to reference plugin system
UPDATE config_cfg SET cfg_tooltip = 'MailChimp API Key - Configure via Admin > Plugins' 
WHERE cfg_name = 'sMailChimpApiKey';

UPDATE config_cfg SET cfg_tooltip = 'Google Analytics Tracking ID - Configure via Admin > Plugins' 
WHERE cfg_name = 'sGoogleTrackingID';

UPDATE config_cfg SET cfg_tooltip = 'Enable Gravatar profile photos - Configure via Admin > Plugins' 
WHERE cfg_name = 'bEnableGravatarPhotos';

UPDATE config_cfg SET cfg_tooltip = 'OpenLP Server URL - Configure via Admin > Plugins' 
WHERE cfg_name = 'sOLPURL';
