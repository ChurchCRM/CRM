INSERT INTO query_qry VALUES (50, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_MiddleName,\' \',per_LastName,\'</a>\') AS Name FROM person_per\n LEFT JOIN person2group2role_p2g2r ON p2g2r_per_ID = per_ID\nLEFT JOIN group_grp ON grp_ID = p2g2r_grp_ID\n WHERE grp_Type = ~grouptype~ GROUP BY per_ID', 'Persons in a Group Type', 'List of persons assigned to groups of a certain type', 1);

INSERT INTO queryparameters_qrp VALUES (100, 50, 2, 'SELECT lst_OptionID as Value, lst_OptionName as Display FROM list_lst WHERE lst_ID = 3 ORDER BY lst_OptionSequence', 'Group Type', 'Please select a group type', 'grouptype', '1', 1, 0, '', 0, 0, 0, 0);


