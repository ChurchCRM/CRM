-- #11145: family verify links are retired; the member portal replaces them. Unused tokens expire anyway.
DELETE FROM `tokens` WHERE `type` = 'verifyFamily';
