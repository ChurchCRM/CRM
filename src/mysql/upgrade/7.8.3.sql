-- ChurchCRM 7.8.3 upgrade script

-- The bundled church_letterhead.jpg is retired. Clear the setting where it still points at
-- the stock image; a path the church chose itself stays.
DELETE FROM `config_cfg` WHERE `cfg_name` = 'bDirLetterHead' AND `cfg_value` IN ('../Images/church_letterhead.jpg', 'Images/church_letterhead.jpg', 'src/Images/church_letterhead.jpg');
