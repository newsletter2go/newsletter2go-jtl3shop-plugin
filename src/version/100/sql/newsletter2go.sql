CREATE TABLE `xplugin_newsletter2go_keys` (`username` VARCHAR(100), `apikey` VARCHAR(100));
INSERT INTO `xplugin_newsletter2go_keys` (`username`, `apikey`) VALUES ('nl2gosync', MD5(RAND()) );