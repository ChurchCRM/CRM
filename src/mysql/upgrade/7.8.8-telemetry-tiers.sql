UPDATE config_cfg SET cfg_value = 'default' WHERE cfg_name = 'sTelemetryLevel' AND cfg_value IN ('none', 'install', 'errors', 'warnings');
