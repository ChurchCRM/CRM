
/** This remove default values if it was set so that we can remove if checks  */
delete from config_cfg WHERE cfg_name = 'sToEmailAddress' and cfg_value = 'myReceiveEmailAddress'
