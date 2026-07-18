-- Migration: 7.5.0 — Add Tier-1 fields to fundraiser_fr
-- Adds end date, lifecycle status, goal amount, event type, and donation-fund link.

ALTER TABLE `fundraiser_fr`
    ADD COLUMN `fr_EndDate`    DATE              NULL                          AFTER `fr_EnteredDate`,
    ADD COLUMN `fr_Status`     VARCHAR(15)       NULL DEFAULT 'Active'      AFTER `fr_EndDate`,
    ADD COLUMN `fr_GoalAmount` DECIMAL(10, 2)    NULL                          AFTER `fr_Status`,
    ADD COLUMN `fr_Type`       VARCHAR(20)       NULL DEFAULT 'Auction'     AFTER `fr_GoalAmount`,
    ADD COLUMN `fr_fund_ID`    MEDIUMINT UNSIGNED NULL                         AFTER `fr_Type`;

-- Back-fill: set end date equal to start date for rows where end date is not set
UPDATE `fundraiser_fr`
SET `fr_EndDate` = `fr_date`
WHERE `fr_EndDate` IS NULL;

-- Back-fill: mark past fundraisers (by event date) as Closed
UPDATE `fundraiser_fr`
SET `fr_Status` = 'Closed'
WHERE `fr_date` < CURDATE()
  AND `fr_Status` = 'Active';
