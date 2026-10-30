-- #10201: Token expiry carries a time of day. Older installs may still have a DATE column;
-- Install.sql already creates DATETIME. MODIFY is safe to rerun.
ALTER TABLE `tokens` MODIFY `valid_until_date` DATETIME NULL;
