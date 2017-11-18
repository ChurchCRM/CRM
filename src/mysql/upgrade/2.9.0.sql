ALTER TABLE person_per
  ADD COLUMN per_FacebookID bigint(20) unsigned default NULL AFTER per_Flags;

ALTER TABLE person_per
  ADD COLUMN per_Twitter varchar(50) default NULL AFTER per_FacebookID;

ALTER TABLE person_per
  ADD COLUMN per_LinkedIn varchar(50) default NULL AFTER per_Twitter;