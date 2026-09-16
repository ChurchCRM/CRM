-- 7.8.0: Add usr_LastPortalActivity to user_usr (Member Portal, #9864).
-- Nullable DATETIME, default NULL => every existing account has never been
-- seen in the Member Portal, which is exactly true before this release.
-- PortalAccessMiddleware stamps it at most once every five minutes; the
-- Admin → Member Portal statistics tab reads it for "active in the last 15
-- minutes".
ALTER TABLE `user_usr`
  ADD COLUMN `usr_LastPortalActivity` datetime DEFAULT NULL;
