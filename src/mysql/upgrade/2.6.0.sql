DELETE FROM config_cfg WHERE cfg_value = cfg_default;

ALTER TABLE config_cfg DROP COLUMN cfg_type;
ALTER TABLE config_cfg DROP COLUMN cfg_default;
ALTER TABLE config_cfg DROP COLUMN cfg_tooltip;
ALTER TABLE config_cfg DROP COLUMN cfg_section;
ALTER TABLE config_cfg DROP COLUMN cfg_category;
ALTER TABLE config_cfg DROP COLUMN cfg_order;
ALTER TABLE config_cfg DROP COLUMN cfg_data;