/*
    Remove non-functional and duplicated 'Family Member Count' query
    Issue #4794 refers
*/
DELETE FROM query_qry WHERE qry_ID=1;

/* add table for gender choices */
CREATE TABLE gender_gen (
    gen_ID tinyint(1) UNSIGNED,
    gen_Name varchar(30),
    gen_Description varchar(50),
    PRIMARY KEY (gen_ID)
);


INSERT INTO gender_gen
    (gen_ID, gen_Name, gen_Description)
VALUES
    (0,"Unassigned", ""),
    (1, "Male", ""),
    (2, "Female", "");

ALTER TABLE person_per
ADD CONSTRAINT gender
FOREIGN KEY (per_Gender) REFERENCES gender_gen(gen_ID);