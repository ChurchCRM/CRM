-- ChurchCRM 7.3.1 Database Cleanup
-- Remove unused IST address verification table

-- istlookup_lu was created for Intelligent Search Technology (IST) US address
-- verification but was never implemented. No PHP ORM class was generated and no
-- application code references this table. It has always contained 0 rows.
DROP TABLE IF EXISTS `istlookup_lu`;
