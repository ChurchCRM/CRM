-- 7.8.0: the user_usr columns the Member Portal needs.
--
-- usr_LastPortalActivity (#9864). Nullable DATETIME, default NULL => every
-- existing account has never been seen in the Member Portal, which is exactly
-- true before this release. PortalAccessMiddleware stamps it at most once every
-- five minutes; the Admin → Member Portal statistics tab reads it for "active
-- in the last 15 minutes".
ALTER TABLE `user_usr`
  ADD COLUMN `usr_LastPortalActivity` datetime DEFAULT NULL;

-- Calendar subscription (design §5.3, "Subscribing").
--
-- usr_PortalCalendarToken is the bearer secret in the member's feed URL, 32
-- random bytes as 64 hex characters, minted the first time they save a
-- selection and rotated by "Reset link". NULL => this account has never asked
-- for a feed, and no URL answers for it. The UNIQUE index is what makes the
-- lookup a single indexed read and what stops two accounts ever sharing one
-- address; MySQL treats NULLs as distinct in a UNIQUE index, so every account
-- without a feed still fits.
--
-- usr_PortalCalendarSelection is a JSON array of the calendar ids the member
-- ticked ("calendar:3", "system:0"). TEXT rather than JSON so the column works
-- on the MySQL and MariaDB versions ChurchCRM supports; nothing queries inside
-- it. The feed always intersects it with the calendars the administrator
-- currently shares, so a stale id here can never widen what a feed serves.
ALTER TABLE `user_usr`
  ADD COLUMN `usr_PortalCalendarToken` VARCHAR(64) DEFAULT NULL,
  ADD COLUMN `usr_PortalCalendarSelection` TEXT DEFAULT NULL,
  ADD UNIQUE KEY `usr_PortalCalendarToken` (`usr_PortalCalendarToken`);
