
--
-- Table structure for table `user_settings`
--
DROP TABLE IF EXISTS `user_settings`;

CREATE TABLE `user_settings` (
  `user_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `user_settings`
--
ALTER TABLE `user_settings`
  ADD PRIMARY KEY (`user_id`,`setting_name`);

/** user interface **/
insert into user_settings select usr_per_ID as user_id, "ui.style" as  setting_name, usr_Style as setting_value from user_usr;
insert into user_settings select usr_per_ID as user_id, "ui.table.size" as  setting_name, usr_SearchLimit as setting_value from user_usr;
insert into user_settings select usr_per_ID as user_id, "ui.search.calendar.start" as  setting_name, usr_CalStart as setting_value from user_usr;
insert into user_settings select usr_per_ID as user_id, "ui.search.calendar.end" as  setting_name, usr_CalEnd as setting_value from user_usr;

/** Finance settings **/
insert into user_settings select usr_per_ID as user_id, "finance.show.pledges" as  setting_name, usr_showPledges as setting_value from user_usr;
insert into user_settings select usr_per_ID as user_id, "finance.show.payments" as  setting_name, usr_showPayments as setting_value from user_usr;
insert into user_settings select usr_per_ID as user_id, "finance.show.since" as  setting_name, usr_showSince as setting_value from user_usr;
insert into user_settings select usr_per_ID as user_id, "finance.FY" as  setting_name, usr_defaultFY as setting_value from user_usr;

/** move items from user config table **/
insert into user_settings select ucfg_per_id as user_id, "ui.email.delimiter" as  setting_name, ucfg_value as setting_value from userconfig_ucfg where ucfg_name = 'sMailtoDelimiter';


