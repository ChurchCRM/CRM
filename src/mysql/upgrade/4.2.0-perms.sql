
--
-- Table structure for table `permissions`
--
DROP TABLE IF EXISTS `permissions`;

CREATE TABLE `permissions` (
  `permission_id` int(11) NOT NULL,
  `permission_name` varchar(50) NOT NULL,
  `permission_desc` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`permission_id`, `permission_name`, `permission_desc`) VALUES
(1, 'addPeople', 'Add People'),
(3, 'updatePeople', 'Update People'),
(4, 'deletePeopleRecords', 'Delete People Records'),
(5, 'curdProperties', 'Manage Properties '),
(6, 'crudClassifications', 'Manage Classifications'),
(7, 'crudGroups', 'Manage Groups'),
(8, 'crudRoles', 'Manage Roles'),
(9, 'crudDonations', 'Manage Donations'),
(10, 'curdFinance', 'Manage Finance'),
(11, 'curdNotes', 'Manage Notes'),
(12, 'canvasser', 'Canvasser volunteer'),
(13, 'editSelf', 'Edit own family only'),
(14, 'emailMailto', 'Allow to see Mailto Links'),
(15, 'createDirectory', 'Create Directories'),
(16, 'exportCSV', 'Export CSV files'),
(17, 'usAddressVerification', 'Use IST Address Verification'),
(18, 'crudEvent', 'Manage Events');

-- --------------------------------------------------------

--
-- Table structure for table `person_permission`
--

DROP TABLE IF EXISTS `person_permission`;
CREATE TABLE `person_permission` (
  `per_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `person_roles`
--
DROP TABLE IF EXISTS `person_roles`;
CREATE TABLE `person_roles` (
  `per_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `role_desc` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_desc`) VALUES
(1, 'Welcome Committee', NULL),
(2, 'Clergy', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`permission_id`),
  ADD UNIQUE KEY `permission_name` (`permission_name`);

--
-- Indexes for table `person_permission`
--
ALTER TABLE `person_permission`
  ADD PRIMARY KEY (`per_id`,`permission_id`);

--
-- Indexes for table `person_roles`
--
ALTER TABLE `person_roles`
  ADD PRIMARY KEY (`per_id`,`role_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `permission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
