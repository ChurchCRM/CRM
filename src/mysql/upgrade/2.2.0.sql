ALTER TABLE config_cfg CHANGE cfg_type cfg_type ENUM('text','number','date','boolean','textarea','json');


SET @JSONV = '{"date1":{"x":"12","y":"42"},"date2X":"185","leftX":"64","topY":"7","perforationY":"97","amountOffsetX":"35","lineItemInterval":{"x":"49","y":"7"},"max":{"x":"200","y":"140"},"numberOfItems":{"x":"136","y":"68"},"subTotal":{"x":"197","y":"42"},"topTotal":{"x":"197","y":"68"},"titleX":"85"}';
INSERT IGNORE INTO `config_cfg` (`cfg_id`, `cfg_name`, `cfg_value`, `cfg_type`, `cfg_default`, `cfg_tooltip`, `cfg_section`, `cfg_category`) VALUES
(1043, 'sQBDTSettings', @JSONV , 'json', @JSONV , 'QuickBooks Deposit Ticket Settings', 'ChurchInfoReport', 'Step7');

DELETE FROM `config_cfg` WHERE cfg_id = 3;

/*Convert the Group Special Properties Field from an ENUM("true","false") 
  to a proper BOOLEAN field without losing data. 
*/
ALTER TABLE group_grp
MODIFY grp_hasSpecialProps INT(1);

UPDATE group_grp
SET grp_hasSpecialProps = grp_hasSpecialProps = 1  ;

ALTER TABLE group_grp
MODIFY grp_hasSpecialProps BOOLEAN;
