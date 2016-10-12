-- ------ Notes - start

ALTER TABLE note_nte
  ADD COLUMN nte_Type VARCHAR(45) NOT NULL DEFAULT 'note' AFTER nte_EditedBy;

INSERT INTO note_nte
	(nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
select per_id, 0, 0, "Original Entry", per_EnteredBy, per_DateEntered, "create"
from person_per;

INSERT INTO note_nte
	(nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
select per_id, 0, 0, "Last Edit", per_EditedBy, per_DateLastEdited, "edit"
from person_per
where per_DateLastEdited is not null;

INSERT INTO note_nte
(nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
  SELECT
    0,
    fam_ID,
    0,
    "Original Entry",
    fam_EnteredBy,
    fam_DateEntered,
    "create"
  FROM family_fam;

INSERT INTO note_nte
(nte_per_ID, nte_fam_ID, nte_Private, nte_Text, nte_EnteredBy, nte_DateEntered, nte_Type)
  SELECT
    0,
    fam_ID,
    0,
    "Last Edit",
    fam_EditedBy,
    fam_DateLastEdited,
    "edit"
  FROM family_fam
  WHERE fam_DateLastEdited IS NOT NULL;

-- ------ Notes - end

-- 'sFPDF_PATH', 'vendor/fpdf17'
DELETE FROM config_cfg WHERE cfg_id IN (4);
