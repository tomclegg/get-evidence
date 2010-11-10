-- this should be run with "mysql --force" to ignore "column already
-- exists" errors.

use ariel;

CREATE TABLE IF NOT EXISTS `humans` (
  `id` int(11) NOT NULL PRIMARY KEY auto_increment,
  `global_id` varchar(16),
  `name` varchar(128),
  `location` varchar(128),
  `birthdate` date,
  `ancestry` text,
  `sex` enum('M','F'),
  UNIQUE `global_id` (`global_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `jobs` ADD `label` varchar(128) default NULL;
ALTER TABLE `jobs` ADD `human` int(11) default NULL;
ALTER TABLE `jobs` ADD INDEX `human` (`human`);
