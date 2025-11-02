-- Remove obsolete photo size configuration items
-- These were replaced with hardcoded values for optimal bandwidth/storage efficiency
-- The application never displays photos larger than 200px, so 400x400 storage was wasteful

-- iPhotoHeight (cfg_id 2034)
-- iPhotoWidth (cfg_id 2035)  
-- iThumbnailWidth (cfg_id 2036)
-- iInitialsPointSize (cfg_id 2037)
-- iPhotoClientCacheDuration (cfg_id 2038) - Replaced with hardcoded 2-hour cache via Slim HttpCache middleware
-- bBackupExtraneousImages (cfg_id 2062) - Initials and remote images are never backed up (can be regenerated)
DELETE FROM config_cfg WHERE cfg_id IN (2034, 2035, 2036, 2037, 2038, 2062);
