CREATE TABLE AdminContact (
  ac_id mediumint(7) NOT NULL auto_increment,
  ac_username varchar(32) collate latin1_general_ci NOT NULL,
  ac_password varchar(64) collate latin1_general_ci NOT NULL,
  ac_ip varchar(32) collate latin1_general_ci NOT NULL,
  ac_lastname varchar(32) collate latin1_general_ci NOT NULL,
  ac_firstname varchar(32) collate latin1_general_ci NOT NULL,
  ac_organization varchar(128) collate latin1_general_ci NOT NULL,
  ac_address1 varchar(128) collate latin1_general_ci NOT NULL,
  ac_address2 varchar(128) collate latin1_general_ci default NULL,
  ac_city varchar(64) collate latin1_general_ci NOT NULL,
  ac_state varchar(32) collate latin1_general_ci NOT NULL,
  ac_zip varchar(16) collate latin1_general_ci NOT NULL,
  ac_phone varchar(32) collate latin1_general_ci NOT NULL,
  ac_email varchar(128) collate latin1_general_ci NOT NULL,
  ac_reviewby mediumint(7) default NULL,
  ac_reviewdate datetime default NULL,
  ac_disposition enum('ACCEPT','REJECT','EXPIRED') collate latin1_general_ci default NULL,
  ac_dir varchar(32) collate latin1_general_ci default NULL,
  PRIMARY KEY  (ac_id)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=1 ;

CREATE TABLE DBPool (
  dbp_id mediumint(7) NOT NULL auto_increment,
  dbp_dbname varchar(64) collate latin1_general_ci NOT NULL,
  dbp_username varchar(64) collate latin1_general_ci NOT NULL,
  dbp_pw varchar(32) collate latin1_general_ci NOT NULL,
  dbp_hostname varchar(128) collate latin1_general_ci NOT NULL,
  dbp_description varchar(128) collate latin1_general_ci NOT NULL,
  dbp_assignedto mediumint(7) default NULL,
  dbp_assigneddate timestamp NULL default NULL,
  PRIMARY KEY  (dbp_id)
) ENGINE=MyISAM AUTO_INCREMENT=17 DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci AUTO_INCREMENT=17 ;
