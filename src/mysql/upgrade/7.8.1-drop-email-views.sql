-- ChurchCRM 7.8.1 upgrade script

-- #9773: email_count and email_list were created by rebuild_views.sql on every
-- upgrade and restore, and nothing reads them.
DROP VIEW IF EXISTS `email_count`;
DROP VIEW IF EXISTS `email_list`;
