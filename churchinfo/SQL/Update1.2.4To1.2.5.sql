INSERT IGNORE INTO `config_cfg` VALUES (82, 'bHidePersonAddress', '1', 'boolean', '1', 'Set true to disable entering addresses in Person Editor.  Set false to enable entering addresses in Person Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (83, 'bHideFriendDate', '0', 'boolean', '0', 'Set true to disable entering Friend Date in Person Editor.  Set false to enable entering Friend Date in Person Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (84, 'bHideFamilyNewsletter', '0', 'boolean', '0', 'Set true to disable management of newsletter subscriptions in the Family Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (85, 'bHideWeddingDate', '0', 'boolean', '0', 'Set true to disable entering Wedding Date in Family Editor.  Set false to enable entering Wedding Date in Family Editor.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (86, 'bHideLatLon', '0', 'boolean', '0', 'Set true to disable entering Latitude and Longitude in Family Editor.  Set false to enable entering Latitude and Longitude in Family Editor.  Lookups are still performed, just not displayed.', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (87, 'bUseDonationEnvelopes', '0', 'boolean', '0', 'Set true to enable use of donation envelopes', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (88, 'sHeader', '', 'text', '', 'Enter in HTML code which will be displayed as a header at the top of each page. Be sure to close your tags! There is a 255 character limit. Note: You must REFRESH YOUR BROWSER A SECOND TIME in order the new header.', 'General');

ALTER TABLE `user_usr` MODIFY `usr_defaultFY` mediumint(9) NOT NULL default '10';
ALTER TABLE `user_usr` MODIFY `usr_currentDeposit` mediumint(9) NOT NULL default '0';

ALTER TABLE `family_fam` ADD COLUMN `fam_Envelope` mediumint(9) NOT NULL default '0';
