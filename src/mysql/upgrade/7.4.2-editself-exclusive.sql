-- ChurchCRM 7.4.2 Data Migration
-- Enforce EditSelf exclusivity: clear orphaned module permissions
--
-- Background:
--   PR #9016 (GHSA-jjcj-h3cm-p7x7) makes usr_EditSelf=1 an exclusive access
--   mode in the PHP model. A non-admin user with EditSelf=1 has all module
--   permissions suppressed at runtime via User::isEditSelfExclusive(). This
--   script clears any existing rows where EditSelf=1 AND one or more module-
--   permission columns are still non-zero, aligning the stored data with the
--   model invariant.
--
-- Scope:
--   Only non-admin rows (usr_Admin = 0) are touched. Admin accounts are exempt
--   because usr_Admin already grants all permissions; the PHP model only
--   activates exclusive mode when !isAdmin() && isEditSelf().
--
-- Columns zeroed when usr_EditSelf = 1 AND usr_Admin = 0:
--   usr_AddRecords, usr_EditRecords, usr_DeleteRecords, usr_MenuOptions,
--   usr_ManageGroups, usr_Finance, usr_Notes
--
-- Idempotent: rows where all module columns are already 0 are unaffected.
--   Safe to run multiple times.

UPDATE `user_usr`
SET
    `usr_AddRecords`    = 0,
    `usr_EditRecords`   = 0,
    `usr_DeleteRecords` = 0,
    `usr_MenuOptions`   = 0,
    `usr_ManageGroups`  = 0,
    `usr_Finance`       = 0,
    `usr_Notes`         = 0
WHERE `usr_EditSelf` = 1
  AND `usr_Admin`    = 0;
