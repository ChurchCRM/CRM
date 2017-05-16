ALTER TABLE events_event
  ADD COLUMN event_grpid mediumint(9) AFTER event_typename,
  ADD COLUMN event_kioskid mediumint(9) AFTER event_grpid;   

ALTER TABLE event_types
  ADD COLUMN type_grpid mediumint(9) AFTER type_active,
  ADD COLUMN type_kioskid mediumint(9) AFTER type_grpid;