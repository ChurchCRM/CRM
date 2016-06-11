
CREATE TABLE messagein 
(
id int(11) NOT NULL auto_increment,
sender varchar(30) default NULL,
receiver varchar(30) default NULL,
msg varchar(1024) default NULL,
senttime varchar(100) default NULL,
receivedtime varchar(100) default NULL,
operator varchar(100),
msgtype varchar(160) default NULL,
PRIMARY KEY (id)
);

CREATE TABLE messageout (
id int(11) NOT NULL auto_increment,
sender varchar(30) default NULL,
receiver varchar(30) default NULL,
msg varchar(1024) default NULL,
senttime varchar(100) default NULL,
receivedtime varchar(100) default NULL,
status varchar(20) default NULL,
msgtype varchar(160) default NULL,
operator varchar(100),
PRIMARY KEY (id)
);
