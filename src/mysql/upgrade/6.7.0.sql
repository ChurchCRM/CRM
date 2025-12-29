-- Add order column to donation funds table for custom sorting
ALTER TABLE donationfund_fun ADD COLUMN fun_Order INT NOT NULL DEFAULT 0 AFTER fun_Description;

-- Initialize order values based on current fund IDs
SET @row_number = 0;
UPDATE donationfund_fun
SET fun_Order = (@row_number:=@row_number + 1)
ORDER BY fun_ID;

-- Remove obsolete pending email tables that were never fully implemented
DROP TABLE IF EXISTS `email_recipient_pending_erp`;
DROP TABLE IF EXISTS `email_message_pending_emp`;
