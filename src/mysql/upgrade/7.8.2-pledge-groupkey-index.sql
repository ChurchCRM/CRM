-- #10730: GroupKey lookups in pledge_plg must use an index on existing installs.
-- Non-unique: multiple pledge/payment rows can legitimately share a group key.
ALTER TABLE `pledge_plg`
  ADD INDEX `idx_plg_GroupKey` (`plg_GroupKey`);
