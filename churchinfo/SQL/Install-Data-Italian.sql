/* This is some sample table data in Italian.  It is different than the sample English data,
except for the queries, which were simply translated directly */

INSERT INTO property_pro VALUES (1,'p',1,'Collegio SaFa','è iscritto al Collegio.','Note particolari');
INSERT INTO property_pro VALUES (2,'f',2,'Famiglia numero','è una famiglia molto numerosa.','');
INSERT INTO property_pro VALUES (3,'g',3,'Chiuso','L\'attività è chiusa.','');
INSERT INTO property_pro VALUES (4,'p',4,'Master','fa parte del gruppo master.','Note particolari');
INSERT INTO property_pro VALUES (5,'p',4,'Juniores','fa parte del gruppo di agonismo.','Note particolari');

INSERT INTO propertytype_prt VALUES (1,'p','Generale','Proprietà generale di persona');
INSERT INTO propertytype_prt VALUES (2,'f','Generale','Proprietà generale di famiglia');
INSERT INTO propertytype_prt VALUES (3,'g','Generale','Proprietà generale di gruppo');
INSERT INTO propertytype_prt VALUES (4,'p','Attività sportiva','Attività sportive particolari per persona');

INSERT INTO query_qry VALUES (2,'SELECT COUNT(per_ID)\nAS \'Count\'\nFROM person_per','Conteggio anagrafica','Restituisce il numero totale di iscritti nella sezione anagrafica.',0);
INSERT INTO query_qry VALUES (3,'SELECT CONCAT(\'<a href=FamilyView.php?FamilyID=\',fam_ID,\'>\',fam_Name,\'</a>\') AS \'Nome famiglia\', COUNT(*) AS \'Numero\'\nFROM person_per\nINNER JOIN family_fam\nON fam_ID = per_fam_ID\nGROUP BY per_fam_ID\nORDER BY \'Numero\' DESC','Conteggio componenti famigliari','Restituisce ogni famiglia ed il numero di componenti.',0);
INSERT INTO query_qry VALUES (4,'SELECT per_ID as AddToCart,CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_LastName,\'</a>\') AS Name, CONCAT(per_BirthMonth,\'/\',per_BirthDay,\'/\',per_BirthYear) AS \'Birth Date\', \nYEAR(CURRENT_DATE) - per_BirthYear AS \'Age\'\nFROM person_per\nWHERE\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL ~min~ YEAR) <= CURDATE()\nAND\nDATE_ADD(CONCAT(per_BirthYear,\'-\',per_BirthMonth,\'-\',per_BirthDay),INTERVAL ~max~ YEAR) >= CURDATE()','Persone per età','Restituisce le schede personali di iscritti con età nell\'intervallo considerato.',1);
INSERT INTO query_qry VALUES (6,'SELECT COUNT(per_ID) AS Totale FROM person_per WHERE per_Gender = ~gender~','Totale per sesso','Total degli iscritti per sesso.',0);
INSERT INTO query_qry VALUES (7,'SELECT per_ID as AddToCart, CONCAT(per_FirstName,\' \',per_LastName) AS Name FROM person_per WHERE per_fmr_ID = ~role~ AND per_Gender = ~gender~','Iscritti per ruolo e sesso','Seleziona gli iscritti secondo il ruolo famigliare ed il sesso specificato.',1);
INSERT INTO query_qry VALUES (9,'SELECT \r\nper_ID as AddToCart, \r\nCONCAT(per_FirstName,\' \',per_LastName) AS Name, \r\nCONCAT(r2p_Value,\' \') AS Value\r\nFROM person_per,record2property_r2p\r\nWHERE per_ID = r2p_record_ID\r\nAND r2p_pro_ID = ~PropertyID~\r\nORDER BY per_LastName','Iscritto con proprietà','Restituisce le schede personali che possiedono la proprietà selezionata.',1);
INSERT INTO query_qry VALUES (10, 'SELECT CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\' target=view>\', per_FirstName,\' \', per_MiddleName,\' \', per_LastName,\'</a>\') AS Name, CONCAT(\'<a href=DonationView.php?PersonID=\',per_ID,\' target=view>\', \'$\',sum(round(dna_amount,2)),\'</a>\') as Amount\r\nFROM donations_don, person_per\r\nLEFT JOIN donationamounts_dna ON don_ID = dna_don_ID\r\nWHERE don_DonorID = per_ID AND don_date >= \'~startdate~\'\r\nAND don_date <= \'~enddate~\'\r\nGROUP BY don_DonorID\r\nORDER BY per_LastName ASC', 'Pagamenti totali per iscritto', 'Somma dei pagamenti per iscritto in un intervallo di tempo determinato.', 1);
INSERT INTO query_qry VALUES (11, 'SELECT fun_name as Fund, CONCAT(\'$\',sum(round(dna_amount,2))) as Total\r\nFROM donations_don\r\nLEFT JOIN donationamounts_dna ON donations_don.don_ID = donationamounts_dna.dna_don_ID LEFT JOIN donationfund_fun ON donationamounts_dna.dna_fun_ID = donationfund_fun.fun_ID\r\nWHERE don_date >= \'~startdate~\'\r\nAND don_date <= \'~enddate~\'\r\nGROUP BY fun_id\r\nORDER BY fun_name', 'Pagamenti totali per fondo', 'Somma dei pagamenti per fondo in un determinato intervallo di tempo.', 1);
INSERT INTO query_qry VALUES (15, 'SELECT per_ID as AddToCart, CONCAT(\'<a href=PersonView.php?PersonID=\',per_ID,\'>\',per_FirstName,\' \',per_MiddleName,\' \',per_LastName,\'</a>\') AS Name, \r\nper_City as City, per_State as State,\r\nper_Zip as ZIP, per_HomePhone as HomePhone\r\nFROM person_per \r\nWHERE ~searchwhat~ LIKE \'%~searchstring~%\'', 'Ricerca avanzata', 'Ricerca avanzata tra i nomi, le città, le province, il codice di avviamento postale o il numero di telefono.', 1);

INSERT INTO queryparameteroptions_qpo VALUES (1,4,'Maschio','1');
INSERT INTO queryparameteroptions_qpo VALUES (2,4,'Femmina','2');
INSERT INTO queryparameteroptions_qpo VALUES (3,6,'Maschio','1');
INSERT INTO queryparameteroptions_qpo VALUES (4,6,'Femmina','2');
INSERT INTO queryparameteroptions_qpo VALUES (5, 15, 'Nome', 'CONCAT(per_FirstName,per_MiddleName,per_LastName)');
INSERT INTO queryparameteroptions_qpo VALUES (6, 15, 'Codice Postale', 'per_Zip');
INSERT INTO queryparameteroptions_qpo VALUES (7, 15, 'Provincia', 'per_State');
INSERT INTO queryparameteroptions_qpo VALUES (8, 15, 'Città', 'per_City');
INSERT INTO queryparameteroptions_qpo VALUES (9, 15, 'Telefono di casa', 'per_HomePhone');

INSERT INTO queryparameters_qrp VALUES (1,4,0,NULL,'Età minima','Età minima nelle schede restituite.','min','0',0,5,'n',120,0,NULL,NULL);
INSERT INTO queryparameters_qrp VALUES (2,4,0,NULL,'Età massima','Età massima nelle schede restituite.','max','120',1,5,'n',120,0,NULL,NULL);
INSERT INTO queryparameters_qrp VALUES (4,6,1,'','Sesso','Il sesso da ricercare nel database.','gender','1',1,0,'',0,0,0,0);
INSERT INTO queryparameters_qrp VALUES (5,7,2,'SELECT lst_OptionID as Value, lst_OptionName as Display FROM list_lst WHERE lst_ID=2 ORDER BY lst_OptionSequence','Ruolo famigliare','Seleziona il ruolo famigliare desiderato.','role','1',0,0,'',0,0,0,0);
INSERT INTO queryparameters_qrp VALUES (6,7,1,'','Sesso','Il sesso da ricercare nel database.','gender','1',1,0,'',0,0,0,0);
INSERT INTO queryparameters_qrp VALUES (8,9,2,'SELECT pro_ID AS Value, pro_Name as Display \r\nFROM property_pro\r\nWHERE pro_Class= \'p\' \r\nORDER BY pro_Name ','Proprietà','La proprietà che devono contenere le schede restituite.','PropertyID','0',1,0,'',0,0,0,0);
INSERT INTO queryparameters_qrp VALUES (9, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Data iniziale', 'Seleziona la data iniziale dalla quale calcolare i contributi di ciascun membro (ovvero AAAA-MM-GG). NOTA: Puoi scegliere solamente date in cui sono stati effettuati pagamenti.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (10, 10, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Data finale', 'Seleziona la data finale alla quale limitare il calcolo dei contributi per ciascun membro (ovvero AAAA-MM-GG).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (14, 15, 0, '', 'Cerca', 'Inserisci qualunque parte di nome, città, provincia, CAP o numero telefonico.', 'searchstring', '', 1, 0, '', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (15, 15, 1, '', 'Campo', 'Seleziona il campo in cui effettuare la ricerca.', 'searchwhat', '1', 1, 0, '', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (16, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don ORDER BY don_date ASC', 'Data iniziale', 'Seleziona la data iniziale dalla quale calcolare i contributi di ciascun membro (ovvero AAAA-MM-GG). NOTA: Puoi scegliere solamente date in cui sono stati effettuati pagamenti.', 'startdate', '1', 1, 0, '0', 0, 0, 0, 0);
INSERT INTO queryparameters_qrp VALUES (17, 11, 2, 'SELECT distinct don_date as Value, don_date as Display FROM donations_don\r\nORDER BY don_date DESC', 'Data finale', 'Seleziona la data finale alla quale limitare il calcolo dei contributi per ciascun membro (ovvero AAAA-MM-GG).', 'enddate', '1', 1, 0, '', 0, 0, 0, 0);

# Sample data for table `donationfund_fun`
INSERT INTO donationfund_fun VALUES (1, 'true', 'Iscrizione', 'Quota di iscrizione.');
INSERT INTO donationfund_fun VALUES (2, 'true', 'Abbonamento', 'Quota di abbonamento.');
INSERT INTO donationfund_fun VALUES (3, 'true', 'Rinnovo', 'Quota di rinnovo.');

# Sample data for member classifications
INSERT INTO list_lst VALUES (1, 1, 1, 'Stagionale');
INSERT INTO list_lst VALUES (1, 2, 2, 'Semestrale');
INSERT INTO list_lst VALUES (1, 3, 3, 'Quadrimestrale');
INSERT INTO list_lst VALUES (1, 4, 4, 'Trimestrale');
INSERT INTO list_lst VALUES (1, 5, 5, 'Bimensile');
INSERT INTO list_lst VALUES (1, 6, 6, 'Mensile');
INSERT INTO list_lst VALUES (1, 7, 7, 'Ingressi singoli');

# Sample data for family roles
INSERT INTO list_lst VALUES (2, 1, 1, 'Marito/convivente');
INSERT INTO list_lst VALUES (2, 2, 2, 'Moglie/convivente');
INSERT INTO list_lst VALUES (2, 3, 3, 'Figlio/figlia');
INSERT INTO list_lst VALUES (2, 4, 4, 'Altra parentela');
INSERT INTO list_lst VALUES (2, 5, 5, 'Nessuna parentela');

# Sample data for group types
INSERT INTO list_lst VALUES (3, 1, 1, 'Nuoto bambini');
INSERT INTO list_lst VALUES (3, 2, 2, 'Nuoto adulti');
INSERT INTO list_lst VALUES (3, 3, 3, 'Aquagym');
INSERT INTO list_lst VALUES (3, 4, 4, 'Acquaticità baby');
INSERT INTO list_lst VALUES (3, 5, 5, 'Acquaticità junior');
