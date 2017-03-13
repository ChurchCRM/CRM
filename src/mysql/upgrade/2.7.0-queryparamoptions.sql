ALTER TABLE queryparameteroptions_qpo
  MODIFY qpo_Value VARCHAR(255) NOT NULL DEFAULT '';

UPDATE queryparameteroptions_qpo SET qpo_Value = 'CONCAT(COALESCE(`per_FirstName`,''),COALESCE(`per_MiddleName`,''),COALESCE(`per_LastName`,''))'
WHERE qpo_ID = 5;

UPDATE query_qry SET qry_SQL = 'SELECT per_ID as AddToCart, CONCAT(''<a href=PersonView.php?PersonID='',per_ID,''>'',COALESCE(`per_FirstName`,''''),'' '',COALESCE(`per_MiddleName`,''''),'' '',COALESCE(`per_LastName`,''''),''</a>'') AS Name, fam_City as City, fam_State as State, fam_Zip as ZIP, per_HomePhone as HomePhone, per_Email as Email, per_WorkEmail as WorkEmail FROM person_per RIGHT JOIN family_fam ON family_fam.fam_id = person_per.per_fam_id WHERE ~searchwhat~ LIKE ''%~searchstring~%'''
WHERE qry_ID = 15;

