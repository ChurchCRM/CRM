-- Store 4-byte characters (emoji) in the remaining user-text tables (#11846).
-- Aborts before altering if an index or foreign key would break under utf8mb4.

-- No index may exceed InnoDB's 3072-byte key limit at 4 bytes per character.
SET @remaining_max_key_bytes := (
    SELECT IFNULL(MAX(k.key_bytes), 0)
    FROM (
        SELECT s.TABLE_NAME, s.INDEX_NAME,
               SUM(
                   CASE
                       WHEN c.CHARACTER_MAXIMUM_LENGTH IS NULL THEN 8
                       ELSE IFNULL(s.SUB_PART, c.CHARACTER_MAXIMUM_LENGTH) * 4
                   END
               ) AS key_bytes
        FROM information_schema.STATISTICS s
        JOIN information_schema.COLUMNS c
          ON c.TABLE_SCHEMA = s.TABLE_SCHEMA
         AND c.TABLE_NAME   = s.TABLE_NAME
         AND c.COLUMN_NAME  = s.COLUMN_NAME
        WHERE s.TABLE_SCHEMA = DATABASE()
          AND s.TABLE_NAME IN (
              'calendars', 'user_usr', 'group_grp', 'locations', 'property_pro',
              'propertytype_prt', 'record2property_r2p', 'pledge_plg',
              'donationfund_fun', 'deposit_dep', 'whycame_why', 'fundraiser_fr',
              'donateditem_di', 'kioskdevice_kdev', 'list_lst', 'family_custom_master',
              'person_custom_master', 'groupprop_master', 'menu_links', 'config_cfg',
              'userconfig_ucfg', 'user_settings', 'eventcountnames_evctnm',
              'eventcounts_evtcnt'
          )
        GROUP BY s.TABLE_NAME, s.INDEX_NAME
    ) k
);

SET @remaining_guard_sql := IF(
    @remaining_max_key_bytes > 3072,
    'SELECT 1 FROM `ABORT_remaining_utf8mb4_index_would_exceed_3072_bytes`',
    'DO 0'
);
PREPARE remaining_guard FROM @remaining_guard_sql;
EXECUTE remaining_guard;
DEALLOCATE PREPARE remaining_guard;

-- No foreign key may join these tables on a character column.
SET @remaining_char_fk_count := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE u
    JOIN information_schema.COLUMNS c
      ON c.TABLE_SCHEMA = u.TABLE_SCHEMA
     AND c.TABLE_NAME   = u.TABLE_NAME
     AND c.COLUMN_NAME  = u.COLUMN_NAME
    WHERE u.TABLE_SCHEMA = DATABASE()
      AND u.REFERENCED_TABLE_NAME IS NOT NULL
      AND c.CHARACTER_SET_NAME IS NOT NULL
      AND (
            u.TABLE_NAME IN (
                'calendars', 'user_usr', 'group_grp', 'locations', 'property_pro',
                'propertytype_prt', 'record2property_r2p', 'pledge_plg',
                'donationfund_fun', 'deposit_dep', 'whycame_why', 'fundraiser_fr',
                'donateditem_di', 'kioskdevice_kdev', 'list_lst', 'family_custom_master',
                'person_custom_master', 'groupprop_master', 'menu_links', 'config_cfg',
                'userconfig_ucfg', 'user_settings', 'eventcountnames_evctnm',
                'eventcounts_evtcnt'
            )
         OR u.REFERENCED_TABLE_NAME IN (
                'calendars', 'user_usr', 'group_grp', 'locations', 'property_pro',
                'propertytype_prt', 'record2property_r2p', 'pledge_plg',
                'donationfund_fun', 'deposit_dep', 'whycame_why', 'fundraiser_fr',
                'donateditem_di', 'kioskdevice_kdev', 'list_lst', 'family_custom_master',
                'person_custom_master', 'groupprop_master', 'menu_links', 'config_cfg',
                'userconfig_ucfg', 'user_settings', 'eventcountnames_evctnm',
                'eventcounts_evtcnt'
            )
      )
);

SET @remaining_fk_guard_sql := IF(
    @remaining_char_fk_count > 0,
    'SELECT 1 FROM `ABORT_remaining_utf8mb4_character_column_foreign_key`',
    'DO 0'
);
PREPARE remaining_fk_guard FROM @remaining_fk_guard_sql;
EXECUTE remaining_fk_guard;
DEALLOCATE PREPARE remaining_fk_guard;

-- calendars and user_usr have varchar(255) unique keys. Converting them first means a
-- server that caps an index column at 767 bytes stops before any table changes.
ALTER TABLE `calendars`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `user_usr`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `group_grp`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `locations`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `property_pro`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `propertytype_prt`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `record2property_r2p`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `pledge_plg`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `donationfund_fun`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `deposit_dep`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `whycame_why`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `fundraiser_fr`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `donateditem_di`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `kioskdevice_kdev`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `list_lst`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `family_custom_master`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `person_custom_master`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `groupprop_master`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `menu_links`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `config_cfg`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `userconfig_ucfg`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `user_settings`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `eventcountnames_evctnm`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `eventcounts_evtcnt`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
