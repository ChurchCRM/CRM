create table `assets_ast` (
	ast_ID mediumint(9) unsigned NOT NULL auto_increment,
	ast_Manufacturer TEXT,
	ast_ModelNumber TEXT,
	ast_SerialNumber TEXT,
	ast_OriginalValue REAL,
	ast_PricePaid REAL,
	ast_ReplacementCost REAL,
	ast_DatePurchased TIMESTAMP default CURRENT_TIMESTAMP not null,
	ast_AllowLending BIT(1),
  PRIMARY KEY  (`ast_ID`)
) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_unicode_ci  AUTO_INCREMENT=1 ;
