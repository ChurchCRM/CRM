
ALTER TABLE person_per ADD COLUMN (per_Flags mediumint(9) NOT NULL default '0');

ALTER TABLE user_usr ADD COLUMN (usr_Canvasser tinyint(3) NOT NULL default '0');

ALTER TABLE family_fam ADD COLUMN (fam_OkToCanvass enum('FALSE','TRUE') NOT NULL default 'FALSE');
ALTER TABLE family_fam ADD COLUMN (fam_Canvasser smallint(5) unsigned NOT NULL default '0');

INSERT INTO query_qry VALUES (26,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE DATE_SUB(NOW(),INTERVAL ~friendmonths~ MONTH)<per_FriendDate ORDER BY per_MembershipDate','Recent friends','Friends who signed up in previous months',0);

INSERT INTO queryparameters_qrp VALUES (26,26,0,'','Months','Number of months since becoming a friend','friendmonths','1',1,0,'',24,1,1,2);

INSERT INTO query_qry VALUES (27,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per inner join family_fam on per_fam_ID=fam_ID where per_fmr_ID<>3 AND fam_OkToCanvass="TRUE" ORDER BY fam_Zip','Families to Canvass','People in families that are ok to canvass.',0);
