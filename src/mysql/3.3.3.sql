/*
    Remove non-functional and duplicated 'Family Member Count' query
    #4794 refers
*/
DELETE FROM query_qry WHERE qry_ID=1;
