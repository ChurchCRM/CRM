alter TABLE family_custom_master DROP fam_custom_Side;
alter TABLE custom_master DROP custom_Side;

delete from list_lst WHERE  lst_OptionName = 'bCommunication';
delete from list_lst WHERE  lst_OptionName = 'bMenuOptions';