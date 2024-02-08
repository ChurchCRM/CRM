IF NOT EXISTS (
   SELECT NULL
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE
    table_name = 'person_per' AND
    column_name = 'per_Facebook'
)  THEN
    ALTER TABLE person_per
    ADD per_Facebook VARCHAR(50) NULL;
END IF;

IF EXISTS (
   SELECT NULL
   FROM INFORMATION_SCHEMA.COLUMNS
   WHERE
    table_name = 'person_per' AND
    column_name = 'per_FacebookID'
)  THEN
    ALTER TABLE person_per
    DROP COLUMN per_FacebookID;
END IF;


