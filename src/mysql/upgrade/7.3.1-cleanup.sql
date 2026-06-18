-- ChurchCRM 7.3.1 Database Cleanup
-- Remove unused IST address verification table

-- istlookup_lu was created for Intelligent Search Technology (IST) US address
-- verification but was never implemented. No PHP ORM class was generated and no
-- application code references this table. It has always contained 0 rows.
DROP TABLE IF EXISTS `istlookup_lu`;

-- Convert note_nte to utf8mb4 so emoji and other 4-byte Unicode characters
-- can be stored in note text (previously caused SQLSTATE[22007] on save).
ALTER TABLE `note_nte`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
