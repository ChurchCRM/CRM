-- Pledges and payments can name the person who gave, not only the family.
-- NULL keeps the existing meaning: the record belongs to the family as a whole.
ALTER TABLE `pledge_plg`
  ADD COLUMN `plg_PerID` mediumint(9) unsigned DEFAULT NULL AFTER `plg_GroupKey`,
  ADD INDEX `idx_plg_PerID` (`plg_PerID`);
