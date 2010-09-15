REVOKE ALL PRIVILEGES, GRANT OPTION FROM `reader`@`localhost`, `writer`@`localhost`, `updater`@`localhost`;

-- "reader" is the back-end user when it needs to look up variants in
-- various db/tables

GRANT SELECT ON `ariel`.* TO `reader`@`localhost`;
GRANT SELECT ON `caliban`.* TO `reader`@`localhost`;
GRANT SELECT ON `dbsnp`.* TO `reader`@`localhost`;
GRANT SELECT ON `hgmd_pro`.* TO `reader`@`localhost`;
GRANT SELECT ON `pharmgkb`.* TO `reader`@`localhost`;
GRANT SELECT ON `get_evidence`.* TO `reader`@`localhost`;
GRANT SELECT ON `hugenet`.* TO `reader`@`localhost`;
GRANT SELECT ON `genotypes`.* TO `reader`@`localhost`;

-- "updater" is the back-end user when it needs to write to the db
-- (hapmap_load_database.py and json_to_job_database.py)

GRANT SELECT, INSERT, UPDATE, DELETE ON `caliban`.* TO `updater`@`localhost`;
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, CREATE TEMPORARY TABLES, DROP ON `genotypes`.* TO `updater`@`localhost`;
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, CREATE TEMPORARY TABLES, DROP, LOCK TABLES ON `get_evidence`.* TO `updater`@`localhost`;
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, CREATE TEMPORARY TABLES, DROP, LOCK TABLES ON `hugenet`.* TO `updater`@`localhost`;

-- "writer" is the webgui user

GRANT SELECT, INSERT, UPDATE, DELETE ON `ariel`.* TO `writer`@`localhost`;
GRANT SELECT ON `genotypes`.* TO `writer`@`localhost`;
