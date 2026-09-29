-- Table structure for linking pledges/payments to individual family members
CREATE TABLE IF NOT EXISTS `person_pledge_map` (
  `ppm_plg_id` mediumint(9) NOT NULL,
  `ppm_per_id` mediumint(9) NOT NULL,
  PRIMARY KEY (`ppm_plg_id`),
  KEY `idx_person` (`ppm_per_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
