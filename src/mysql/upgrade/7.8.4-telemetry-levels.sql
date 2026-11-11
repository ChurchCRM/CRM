INSERT IGNORE INTO config_cfg (cfg_name, cfg_value) VALUES ('sTelemetryLevel', 'none');
UPDATE config_cfg SET cfg_value = 'errors' WHERE cfg_name = 'sTelemetryLevel' AND cfg_value = 'warnings';
