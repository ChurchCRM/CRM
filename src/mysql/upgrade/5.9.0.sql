DROP TABLE IF EXISTS canvassdata_can;
ALTER TABLE family_fam DROP COLUMN IF EXISTS fam_OkToCanvass;
ALTER TABLE family_fam DROP COLUMN IF EXISTS fam_Canvasser;
DELETE FROM list_lst WHERE lst_OptionName = 'bCanvasser';
DELETE FROM query_qry WHERE qry_ID = '27';
ALTER TABLE user_usr DROP COLUMN IF EXISTS usr_Canvasser;
DELETE FROM permissions WHERE permission_name = 'canvasser';
