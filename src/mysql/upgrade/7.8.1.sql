-- ChurchCRM 7.8.1 upgrade script

-- #10531: a team leader adds someone to a ministry's volunteer pool and the
-- ministry's coordinators are emailed (outbox type `pool_add`). Appending an
-- ENUM value leaves every existing row as it is.
ALTER TABLE `volunteer_notification_vntf`
  MODIFY COLUMN `vntf_Type` enum('assignment','reminder','decline_alert','gap_alert','signup_confirm','swap_proposed','swap_resolved','help_offer','pool_add') NOT NULL;
