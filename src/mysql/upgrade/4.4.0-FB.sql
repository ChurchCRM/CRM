alter table person_per
    add per_Facebook varchar(50) null after per_FacebookID;

alter table person_per
    drop column per_FacebookID;
