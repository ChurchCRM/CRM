-- ChurchCRM 7.0.2 2FA Settings Cleanup
-- Remove bEnable2FA — 2FA self-enrollment is now always available to all users.
-- The only meaningful admin control is bRequire2FA (force mandatory enrollment).

-- cfg_id: 2068
-- cfg_name: bEnable2FA
-- Description: Previously gated optional 2FA enrollment. Removed because the system
--              now auto-generates the encryption key and 2FA enrollment is always
--              available. Use bRequire2FA to mandate enrollment for all users.
DELETE FROM config_cfg
WHERE cfg_name = 'bEnable2FA'
    OR cfg_id = 2068;
