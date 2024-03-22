DELETE FROM config_cfg WHERE cfg_value = cfg_default;

alter table config_cfg drop column `cfg_type`;
alter table config_cfg drop column `cfg_tooltip`;
alter table config_cfg drop column `cfg_section`;
alter table config_cfg drop column `cfg_category`;
alter table config_cfg drop column `cfg_data`;
