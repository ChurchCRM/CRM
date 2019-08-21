delete from list_lst WHERE  lst_OptionName = 'bCommunication';
delete from list_lst WHERE  lst_OptionName = 'bMenuOptions';

ALTER TABLE user_usr DROP COLUMN usr_Communication;