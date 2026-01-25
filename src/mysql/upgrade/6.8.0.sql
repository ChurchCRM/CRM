-- ChurchCRM 6.8.0 Database Upgrade
-- 
-- Changes:
-- 1. Add SMTP Port configuration setting (iSMTPPort)

-- Add SMTP Port config if it doesn't already exist
-- This allows users to configure SMTP port separately from the host
-- Default port is 587 (standard for STARTTLS/TLS)
INSERT IGNORE INTO config_cfg (cfg_id, cfg_name, cfg_value) 
VALUES (25, 'iSMTPPort', '587');
