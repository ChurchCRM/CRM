-- List IDs below 10 are reserved for permanent lists, but group role lists
-- used to take MAX(lst_ID)+1, so a database that started on ChurchInfo can
-- already use 6. The grades list takes 6 when nothing refers to it and
-- otherwise the next ID no list or reference uses, recorded in iGradeListId.
SET @grade_list_in_use = (SELECT COUNT(*) FROM `list_lst` WHERE `lst_ID` = 6)
  + (SELECT COUNT(*) FROM `group_grp` WHERE `grp_RoleListID` = 6)
  + (SELECT COUNT(*) FROM `person_custom_master` WHERE `type_ID` = 12 AND `custom_Special` = 6)
  + (SELECT COUNT(*) FROM `family_custom_master` WHERE `type_ID` = 12 AND `fam_custom_Special` = 6)
  + (SELECT COUNT(*) FROM `groupprop_master` WHERE `type_ID` = 12 AND `prop_Special` = 6);

SET @grade_list_id = IF(@grade_list_in_use = 0, 6, GREATEST(
  10,
  (SELECT COALESCE(MAX(`lst_ID`), 0) + 1 FROM `list_lst`),
  (SELECT COALESCE(MAX(`grp_RoleListID`), 0) + 1 FROM `group_grp`),
  (SELECT COALESCE(MAX(`custom_Special`), 0) + 1 FROM `person_custom_master` WHERE `type_ID` = 12),
  (SELECT COALESCE(MAX(`fam_custom_Special`), 0) + 1 FROM `family_custom_master` WHERE `type_ID` = 12),
  (SELECT COALESCE(MAX(`prop_Special`), 0) + 1 FROM `groupprop_master` WHERE `type_ID` = 12)
));

-- Persist the allocation before inserting rows: a retry must use the same list,
-- including when legacy group roles already occupy list 6.
INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'dbUpgrade.7.8.1.gradeList', CAST(COALESCE(
  (SELECT `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'iGradeListId'),
  @grade_list_id
) AS UNSIGNED);
SET @grade_list_id = (SELECT `cfg_value` FROM `config_cfg` WHERE `cfg_name` = 'dbUpgrade.7.8.1.gradeList');

INSERT IGNORE INTO `list_lst` (`lst_ID`, `lst_OptionID`, `lst_OptionSequence`, `lst_OptionName`) VALUES
  (@grade_list_id, 4, 1, 'Pre-K'),
  (@grade_list_id, 5, 2, 'Kindergarten'),
  (@grade_list_id, 6, 3, '1st'),
  (@grade_list_id, 7, 4, '2nd'),
  (@grade_list_id, 8, 5, '3rd'),
  (@grade_list_id, 9, 6, '4th'),
  (@grade_list_id, 10, 7, '5th'),
  (@grade_list_id, 11, 8, '6th'),
  (@grade_list_id, 12, 9, '7th'),
  (@grade_list_id, 13, 10, '8th'),
  (@grade_list_id, 14, 11, '9th'),
  (@grade_list_id, 15, 12, '10th'),
  (@grade_list_id, 16, 13, '11th'),
  (@grade_list_id, 17, 14, '12th');

INSERT IGNORE INTO `config_cfg` (`cfg_name`, `cfg_value`)
SELECT 'iGradeListId', @grade_list_id FROM DUAL WHERE @grade_list_id <> 6;
