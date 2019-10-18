ALTER TABLE `user_usr` 
ADD COLUMN `usr_TwoFactorAuthSecret` VARCHAR(255) NULL AFTER `usr_Canvasser`,
ADD COLUMN `usr_TwoFactorAuthLastKeyTimestamp` INT NULL AFTER `usr_TwoFactorAuthSecret`,
ADD COLUMN `usr_TwoFactorAuthRecoveryCodes` TEXT NULL AFTER `usr_TwoFactorAuthLastKeyTimestamp`;