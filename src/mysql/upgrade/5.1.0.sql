delete from permissions where permission_name ='usAddressVerification';

delete from config_cfg where cfg_id ='54';
delete from config_cfg where cfg_id ='55';

delete from userconfig_ucfg where ucfg_name = "bUSAddressVerification";

Update `config_cfg` set `cfg_value` = 0 where `cfg_name` = "bRegistered";