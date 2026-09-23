-- 7.7.0: Add per_DateDeceased to person_per.
-- Nullable DATE, default NULL => every existing person is treated as living.
ALTER TABLE `person_per`
  ADD COLUMN `per_DateDeceased` date DEFAULT NULL AFTER `per_MembershipDate`;
