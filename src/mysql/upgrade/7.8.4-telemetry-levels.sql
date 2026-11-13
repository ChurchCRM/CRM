INSERT IGNORE INTO config_cfg (cfg_name, cfg_value) VALUES ('sTelemetryLevel', 'none');
UPDATE config_cfg SET cfg_value = 'errors' WHERE cfg_name = 'sTelemetryLevel' AND cfg_value = 'warnings';
UPDATE config_cfg SET cfg_value = '' WHERE cfg_name = 'sTelemetryAskedVersion' AND cfg_value <> '' AND 'none' = (SELECT level_value FROM (SELECT cfg_value AS level_value FROM config_cfg WHERE cfg_name = 'sTelemetryLevel') AS telemetry_level);
