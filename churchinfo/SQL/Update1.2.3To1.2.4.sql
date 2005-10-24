
INSERT IGNORE INTO `config_cfg` VALUES (76, 'sXML_RPC_PATH', 'XML/RPC.php', 'text', 'XML/RPC.php', 'Path to RPC.php, required for Lat/Lon address lookup', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (77, 'sGeocoderID', '', 'text', '', 'User ID for rpc.geocoder.us', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (78, 'sGeocoderPW', '', 'text', '', 'Password for rpc.geocoder.us', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (79, 'sGoogleMapKey', '', 'text', '', 'Google map API requires a unique key from http://maps.google.com/apis/maps/signup.html', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (80, 'nChurchLatitude', '', 'number', '', 'Latitude of the church, used to center the Google map', 'General');
INSERT IGNORE INTO `config_cfg` VALUES (81, 'nChurchLongitude', '', 'number', '', 'Longitude of the church, used to center the Google map', 'General');

ALTER TABLE  `family_fam` ADD  `fam_Latitude` double default NULL;
ALTER TABLE  `family_fam` ADD  `fam_Longitude` double default NULL;
