ALTER TABLE events_event
  ADD COLUMN event_grpid mediumint(9) AFTER event_typename;

ALTER TABLE event_types
  ADD COLUMN type_grpid mediumint(9) AFTER type_active;