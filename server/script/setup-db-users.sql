-- should be run with "mysql --force" so grant statements run even
-- though databases already exist

CREATE DATABASE `genotypes` DEFAULT CHARACTER SET ASCII COLLATE ascii_general_ci;
CREATE DATABASE `ariel` DEFAULT CHARACTER SET ASCII COLLATE ascii_general_ci;
CREATE DATABASE `caliban` DEFAULT CHARACTER SET ASCII COLLATE ascii_general_ci;
CREATE DATABASE `dbsnp` DEFAULT CHARACTER SET ascii COLLATE ascii_general_ci;
CREATE DATABASE `hgmd_pro` DEFAULT CHARACTER SET ascii COLLATE ascii_general_ci;
CREATE DATABASE `pharmgkb` DEFAULT CHARACTER SET ascii COLLATE ascii_general_ci;
CREATE DATABASE `get_evidence` DEFAULT CHARACTER SET ascii COLLATE ascii_general_ci;
CREATE DATABASE `hugenet` DEFAULT CHARACTER SET ascii COLLATE ascii_general_ci;

-- drop non-local users if they exist (from previous installation),
-- first giving them a row in the access table to prevent mysql errors
-- in case they're *not* there

GRANT USAGE ON `ariel`.* TO `reader`@`%`, `writer`@`%`, `updater`@`%`, `installer`@`%`;
DROP USER 'reader'@'%';
DROP USER 'writer'@'%';
DROP USER 'updater'@'%';
DROP USER 'installer'@'%';

-- similarly, drop the local users so we don't get errors while creating them

GRANT USAGE ON `ariel`.* TO `reader`@`localhost`, `writer`@`localhost`, `updater`@`localhost`, `installer`@`localhost`;
DROP USER 'reader'@'localhost';
DROP USER 'writer'@'localhost';
DROP USER 'updater'@'localhost';
DROP USER 'installer'@'localhost';

CREATE USER `reader`@`localhost` IDENTIFIED BY 'shakespeare';
CREATE USER 'updater'@`localhost` IDENTIFIED BY 'shakespeare';
CREATE USER 'writer'@`localhost` IDENTIFIED BY 'shakespeare';
CREATE USER 'installer'@`localhost` IDENTIFIED BY 'shakespeare';

-- "installer" is the install script

GRANT ALL PRIVILEGES ON `ariel`.* TO `installer`@`localhost` WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `caliban`.* TO `installer`@`localhost` WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `dbsnp`.* TO `installer`@`localhost` WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `hgmd_pro`.* TO `installer`@`localhost` WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `genotypes`.* TO `installer`@`localhost` WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `pharmgkb`.* TO `installer`@`localhost` WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `get_evidence`.* TO `installer`@`localhost` WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON `hugenet`.* TO `installer`@`localhost` WITH GRANT OPTION;
GRANT CREATE USER ON *.* TO `installer`@`localhost`;
