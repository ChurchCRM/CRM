--
-- No Longer require password change
--

update user_usr set usr_NeedPasswordChange = 0 where usr_per_ID = 1;

--
-- All mail will go to local mailcatcher
--

update config_cfg set cfg_value = "127.0.0.1:1025" where cfg_name = "sSMTPHost";
