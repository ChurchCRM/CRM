
delete from query_qry where qry_ID in (10, 11);

INSERT INTO query_qry VALUES (28,'SELECT fam_Name, a.plg_amount as PlgFY1, b.plg_amount as PlgFY2 from family_fam left join pledge_plg a on a.plg_famID = fam_ID and a.plg_FYID=~fyid1~ and a.plg_PledgeOrPayment=\'Pledge\' left join pledge_plg b on b.plg_famID = fam_ID and b.plg_FYID=~fyid2~ and b.plg_PledgeOrPayment=\'Pledge\' order by fam_Name','Pledge comparison','Compare pledges between two fiscal years',1);

INSERT INTO queryparameteroptions_qpo VALUES (10, 27, '2004/2005', '9');
INSERT INTO queryparameteroptions_qpo VALUES (11, 27, '2005/2006', '10');
INSERT INTO queryparameteroptions_qpo VALUES (12, 27, '2006/2007', '11');
INSERT INTO queryparameteroptions_qpo VALUES (13, 27, '2007/2008', '12');

INSERT INTO queryparameteroptions_qpo VALUES (14, 28, '2004/2005', '9');
INSERT INTO queryparameteroptions_qpo VALUES (15, 28, '2005/2006', '10');
INSERT INTO queryparameteroptions_qpo VALUES (16, 28, '2006/2007', '11');
INSERT INTO queryparameteroptions_qpo VALUES (17, 28, '2007/2008', '12');

INSERT INTO queryparameters_qrp VALUES (27,28,1,'','First Fiscal Year','First fiscal year for comparison','fyid1','9',1,0,'',12,9,0,0);
INSERT INTO queryparameters_qrp VALUES (28,28,1,'','Second Fiscal Year','Second fiscal year for comparison','fyid2','9',1,0,'',12,9,0,0);

ALTER TABLE user_usr ADD UNIQUE KEY `usr_UserName` (`usr_UserName`);
