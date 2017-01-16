DROP TABLE IF EXISTS `tokens`;
CREATE TABLE `tokens` (
  `token` VARCHAR(99) NOT NULL,
  `type` ENUM('verifyFamily', 'verifyPerson') NOT NULL,
  `reference_id` INT(9) NOT NULL,
  `valid_until_date` datetime NULL,
  `remainingUses` INT(2) NULL,
  PRIMARY KEY (`token`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci;

INSERT INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`) VALUES
(1048, 'sConfirmSincerely', 'Sincerely', 'Text', 'Sincerely', 'Used to end a letter before Signer', 'ChurchInfoReport'),
(1050, 'googleTrackingID', '', 'Text', '', 'Google Analytics Tracking Code', 'General');

update config_cfg set cfg_data = '{"Choices":["English - United States:en_US", "English - Canada:en_CA", "English - Australia:en_AU", "English - Great Britain:en_GB", "German - Germany:de_DE", "Spanish - Spain:es_ES", "French - France:fr_FR", "Hungarian:hu_HU", "Italian - Italy:it_IT", "Norwegian:nb_NO", "Dutch - Netherlands:nl_NL", "Polish:pl_PL", "Portuguese - Brazil:pt_BR", "Romanian - Romania:ro_RO", "Russian:ru_RU", "Sami (Northern) (Sweden):se_SE", "Albanian:sq_AL", "Swedish - Sweden:sv_SE", "Vietnamese:vi_VN", "Chinese - China:zh_CN", "Chinese - Taiwan:zh_TW"]}' where cfg_id = 39;
update config_cfg set cfg_tooltip = 'Internationalization (I18n) support' where cfg_id = 39;
update config_cfg set cfg_tooltip = 'Make user-entered zip/postcodes UPPERCASE when saving to the database.' where cfg_id = 67;

INSERT INTO `query_qry` (`qry_ID`, `qry_SQL`, `qry_Name`, `qry_Description`, `qry_Count`) VALUES
(1, 'SELECT CONCAT(''<a href=FamilyView.php?FamilyID='',fam_ID,''>'',fam_Name,''</a>'') AS ''Family Name''   FROM family_fam Where fam_WorkPhone != ""', 'Family Member Count', 'Returns each family and the total number of people assigned to them.', 0);

