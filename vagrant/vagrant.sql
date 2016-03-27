--
-- No Longer require password change
--



--
-- All mail will go to local mailcatcher
--

update config_cfg set cfg_value = "127.0.0.1:1025" where cfg_name = "sSMTPHost";
