-- ChurchCRM 7.8.2 upgrade script

-- #10773: the Menu Options permission is retired (#10314). Admins manage the property
-- catalogue; people, family and group property assignment follow Edit Records and
-- Manage Groups; letters, labels and the verify report follow ordinary report access.
ALTER TABLE `user_usr` DROP COLUMN `usr_MenuOptions`;
