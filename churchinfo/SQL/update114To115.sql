
CREATE TABLE autopayment_aut (
  aut_ID mediumint(9) unsigned NOT NULL auto_increment,
  aut_FamID mediumint(9) unsigned NOT NULL default '0',
  aut_EnableBankDraft tinyint(1) unsigned NOT NULL default '0',
  aut_EnableCreditCard tinyint(1) unsigned NOT NULL default '0',
  aut_NextPayDate date default NULL,
  aut_Amount decimal(6,2) NOT NULL default '0.00',
  aut_Interval tinyint(3) NOT NULL default '1',
  aut_Fund mediumint(6) NOT NULL default '0',

  aut_FirstName varchar(50) default NULL,
  aut_LastName varchar(50) default NULL,
  aut_Address1 varchar(255) default NULL,
  aut_Address2 varchar(255) default NULL,
  aut_City varchar(50) default NULL,
  aut_State varchar(50) default NULL,
  aut_Zip varchar(50) default NULL,
  aut_Country varchar(50) default NULL,
  aut_Phone varchar(30) default NULL,
  aut_Email varchar(100) default NULL,

  aut_CreditCard varchar(50) default NULL,
  aut_ExpMonth varchar(2) default NULL,
  aut_ExpYear varchar(4) default NULL,

  aut_BankName varchar (50) default NULL,
  aut_Route varchar (30) default NULL,
  aut_Account varchar (30) default NULL,

  aut_DateLastEdited datetime default NULL,
  aut_EditedBy smallint(5) unsigned default '0',

  aut_Serial mediumint(9) NOT NULL default '1',

  PRIMARY KEY  (aut_ID),
  UNIQUE KEY aut_ID (aut_ID)
) TYPE=MyISAM;

ALTER TABLE deposit_dep ADD COLUMN (dep_Type enum('Bank','CreditCard','BankDraft') NOT NULL default 'Bank');

ALTER TABLE person_per ADD COLUMN (per_FriendDate date default NULL);

ALTER TABLE pledge_plg CHANGE COLUMN plg_method plg_method enum('CREDITCARD','CHECK','CASH','BANKDRAFT') default NULL;
ALTER TABLE pledge_plg ADD COLUMN (plg_aut_ID mediumint(9) NOT NULL default '0');
ALTER TABLE pledge_plg ADD COLUMN (plg_aut_Cleared tinyint(1) NOT NULL default '0');
ALTER TABLE pledge_plg ADD COLUMN (plg_aut_ResultID mediumint(9) NOT NULL default '0');

CREATE TABLE result_res (
  res_ID mediumint(9) NOT NULL auto_increment,
  res_echotype1 text NOT NULL,
  res_echotype2 text NOT NULL,
  res_echotype3 text NOT NULL,
  res_authorization text NOT NULL,
  res_order_number text NOT NULL,
  res_reference text NOT NULL,
  res_status text NOT NULL,
  res_avs_result text NOT NULL,
  res_security_result text NOT NULL,
  res_mac text NOT NULL,
  res_decline_code text NOT NULL,
  res_tran_date text NOT NULL,
  res_merchant_name text NOT NULL,
  res_version text NOT NULL,
  res_EchoServer text NOT NULL,
  PRIMARY KEY  (res_ID)
) TYPE=MyISAM;

CREATE TABLE whycame_why (
  why_ID mediumint(9) NOT NULL auto_increment,
  why_per_ID mediumint(9) NOT NULL default '0',
  why_join text NOT NULL,
  why_come text NOT NULL,
  why_suggest text NOT NULL,
  why_hearOfUs text NOT NULL,
  PRIMARY KEY  (why_ID)
) TYPE=MyISAM;

