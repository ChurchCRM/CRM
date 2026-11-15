-- #8672: a user's settings go away with the user.
--
-- user_settings rows were left behind when a user was deleted, and came back if
-- the same person was given a login again. This removes those orphans and adds
-- a foreign key to user_usr with ON DELETE CASCADE, so the database removes a
-- user's settings when the user is deleted. user_id becomes mediumint(9)
-- unsigned to match usr_per_ID (a foreign key needs the same type and sign).
--
-- Safe to run again: the DELETE finds nothing, and the column change and the
-- constraint are each guarded with information_schema.

-- BEGIN: user settings follow their user
DELETE s FROM `user_settings` s
LEFT JOIN `user_usr` u ON u.`usr_per_ID` = s.`user_id`
WHERE u.`usr_per_ID` IS NULL;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'user_settings' AND COLUMN_NAME = 'user_id'
       AND DATA_TYPE = 'mediumint' AND COLUMN_TYPE LIKE '%unsigned%') = 0,
    'ALTER TABLE `user_settings` MODIFY `user_id` mediumint(9) unsigned NOT NULL',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;

SET @_sql = IF(
    (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'user_settings'
       AND CONSTRAINT_NAME = 'fk_user_settings_user' AND CONSTRAINT_TYPE = 'FOREIGN KEY') = 0,
    'ALTER TABLE `user_settings` ADD CONSTRAINT `fk_user_settings_user` FOREIGN KEY (`user_id`) REFERENCES `user_usr` (`usr_per_ID`) ON DELETE CASCADE',
    'DO 0'
);
PREPARE _s FROM @_sql;
EXECUTE _s;
DEALLOCATE PREPARE _s;
-- END: user settings follow their user
