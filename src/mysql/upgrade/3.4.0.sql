/*
    Remove non-functional and duplicated 'Family Member Count' query
    Issue #4794 refers
*/
DELETE FROM query_qry WHERE qry_ID=1;

/*
    Newfoundland and Labrador Provincial code changed from NF to NL
*/
update family_fam set fam_State = "NL" where fam_State = "NF";
update person_per set per_State = "NL" where per_State = "NF";