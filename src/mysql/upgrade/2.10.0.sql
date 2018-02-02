ALTER TABLE `events_event` 
  ADD COLUMN `event_publicly_visible` BOOLEAN DEFAULT FALSE AFTER `event_grpid`;