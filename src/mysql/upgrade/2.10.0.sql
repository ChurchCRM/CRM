IF NOT EXISTS (
   SELECT NULL
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE
    table_name = 'events_event' AND
    column_name = 'event_publicly_visible'
)  THEN
    ALTER TABLE `events_event`
    ADD COLUMN `event_publicly_visible` BOOLEAN DEFAULT FALSE AFTER `event_grpid`;
END IF;
