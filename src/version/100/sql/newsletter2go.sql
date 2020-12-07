CREATE TABLE IF NOT EXISTS `xplugin_newsletter2go_keys` (`username` VARCHAR(100), `apikey` VARCHAR(100));
DELETE FROM `xplugin_newsletter2go_keys`;
INSERT INTO `xplugin_newsletter2go_keys` (`username`, `apikey`) VALUES ('nl2gosync', MD5(RAND()) );