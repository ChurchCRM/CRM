-- The 7.8.3 member sign-in cutover removes non-admin self-service accounts first.
-- BEGIN: drop retired Edit Self column
SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_usr' AND COLUMN_NAME = 'usr_EditSelf') > 0,
    'ALTER TABLE `user_usr` DROP COLUMN `usr_EditSelf`',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;
-- END: drop retired Edit Self column
