ALTER TABLE `user_usr` 
ADD COLUMN `usr_TwoFactorAuthSecret` VARCHAR(255) NULL AFTER `usr_Canvasser`;
