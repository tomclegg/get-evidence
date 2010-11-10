USE ariel;
 
CREATE TABLE IF NOT EXISTS `evidence` (
  `phenotype` VARCHAR(255) NOT NULL,
  `chr` VARCHAR(12) NOT NULL,
  `start` INT NOT NULL,
  `end` INT NOT NULL,
  `allele` VARCHAR(255) NOT NULL,
  `inheritance` VARCHAR(12) NULL,
  `references` TEXT NULL,
  `tags` TEXT NULL,
  `id` INT NOT NULL auto_increment,
  PRIMARY KEY (`id`),
  KEY (`phenotype`),
  KEY (`chr`, `start`, `end`, `allele`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 
CREATE TABLE IF NOT EXISTS `files` (
  `id` int(11) NOT NULL auto_increment,
  `path` text NOT NULL,
  `kind` varchar(16) NOT NULL,
  `job` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `kind` (`kind`,`job`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 
CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int(11) NOT NULL auto_increment,
  `submitted` timestamp NULL default NULL,
  `processed` timestamp NULL default NULL,
  `retrieved` timestamp NULL default NULL,
  `public` tinyint(1) NOT NULL default '0',
  `user` int(11) default NULL,
  PRIMARY KEY (`id`),
  KEY `user` (`user`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL auto_increment,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(64) NOT NULL,
  `email` varchar(128) default NULL,
  `created` timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
 
USE caliban;
 
CREATE OR REPLACE VIEW `evidence` AS SELECT * FROM `ariel`.`evidence`;
 
CREATE TABLE IF NOT EXISTS `hapmap27` (
  `rs_id` VARCHAR(16) NOT NULL,
  `chr` VARCHAR(12) NOT NULL,
  `start` INT UNSIGNED NOT NULL,
  `end` INT UNSIGNED NOT NULL,
  `strand` ENUM('+','-') NOT NULL,
  `pop` VARCHAR(8) NOT NULL,
  `ref_allele` CHAR(1) NOT NULL,
  `ref_allele_freq` DECIMAL(6,4) NOT NULL,
  `ref_allele_count` INT UNSIGNED NOT NULL,
  `oth_allele` CHAR(1) NULL,
  `oth_allele_freq` DECIMAL(6,4) NULL,
  `oth_allele_count` INT UNSIGNED NULL,
  `total_count` INT UNSIGNED NOT NULL,
  UNIQUE KEY `i_rs_id_pop` (`rs_id`,`pop`),
  KEY `i_chrom_start_end` (`chr`,`start`,`end`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
CREATE TABLE IF NOT EXISTS `morbidmap` (
  `disorder` varchar(255) NOT NULL,
  `symbols` varchar(128) NOT NULL,
  `omim` int(11) NOT NULL,
  `location` varchar(24) NOT NULL,
  KEY `omim` (`omim`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
CREATE TABLE IF NOT EXISTS `omim` (
  `phenotype` VARCHAR(255) NOT NULL,
  `gene` VARCHAR(12) NOT NULL,
  `amino_acid` VARCHAR(8) NOT NULL,
  `codon` INT NOT NULL,
  `word_count` INT,
  `allelic_variant_id` VARCHAR(24),
  KEY (`gene`,`codon`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
CREATE TABLE IF NOT EXISTS `refflat` (
  `geneName` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `chrom` varchar(255) NOT NULL,
  `strand` char(1) NOT NULL,
  `txStart` int(10) unsigned NOT NULL,
  `txEnd` int(10) unsigned NOT NULL,
  `cdsStart` int(10) unsigned NOT NULL,
  `cdsEnd` int(10) unsigned NOT NULL,
  `exonCount` int(10) unsigned NOT NULL,
  `exonStarts` longblob,
  `exonEnds` longblob,
  KEY `geneName` (`geneName`),
  KEY `i_chromtxstarttxend` (`chrom`,`txStart`,`txEnd`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
CREATE OR REPLACE VIEW `refflat-complete` AS SELECT * FROM `refflat`;
 
-- unchanged from UCSC schema
CREATE TABLE IF NOT EXISTS `snp129` (
  `bin` smallint(5) unsigned NOT NULL default '0',
  `chrom` varchar(31) NOT NULL default '',
  `chromStart` int(10) unsigned NOT NULL default '0',
  `chromEnd` int(10) unsigned NOT NULL default '0',
  `name` varchar(15) NOT NULL default '',
  `score` smallint(5) unsigned NOT NULL default '0',
  `strand` enum('+','-') default NULL,
  `refNCBI` blob NOT NULL,
  `refUCSC` blob NOT NULL,
  `observed` varchar(255) NOT NULL default '',
  `molType` enum('genomic','cDNA') default NULL,
  `class` enum('unknown','single','in-del','het','microsatellite','named','mixed','mnp','insertion','deletion') NOT NULL default 'unknown',
  `valid` set('unknown','by-cluster','by-frequency','by-submitter','by-2hit-2allele','by-hapmap') NOT NULL default 'unknown',
  `avHet` float NOT NULL default '0',
  `avHetSE` float NOT NULL default '0',
  `func` set('unknown','coding-synon','intron','cds-reference','near-gene-3','near-gene-5','nonsense','missense','frameshift','untranslated-3','untranslated-5','splice-3','splice-5') NOT NULL default 'unknown',
  `locType` enum('range','exact','between','rangeInsertion','rangeSubstitution','rangeDeletion') default NULL,
  `weight` int(10) unsigned NOT NULL default '0',
  KEY `name` (`name`),
  KEY `chrom` (`chrom`,`bin`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
CREATE TABLE IF NOT EXISTS `snpedia` (
  `phenotype` VARCHAR(255) NOT NULL,
  `chr` VARCHAR(12) NOT NULL,
  `start` INT UNSIGNED NOT NULL,
  `end` INT UNSIGNED NOT NULL,
  `strand` enum('+','-') NOT NULL,
  `genotype` VARCHAR(255) NOT NULL,
  `pubmed_id` TEXT,
  `rs_id` VARCHAR(255),
  KEY (`rs_id`),
  KEY `i_chrom_start_end` (`chr`,`start`,`end`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
USE dbsnp;
 
CREATE TABLE IF NOT EXISTS `OmimVarLocusIdSNP` (
`omim_id` INT NOT NULL,
`locus_id` INT NOT NULL,
`omimvar_id` CHAR(4) NOT NULL,
`locus_symbol` CHAR(10) NOT NULL,
`var1` CHAR(2) NOT NULL,
`aa_position` INT NOT NULL,
`var2` CHAR(2) NOT NULL,
`var_class` INT NOT NULL,
`snp_id` INT NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
ALTER TABLE `OmimVarLocusIdSNP` ADD INDEX `i_snp_id` (`snp_id`);
ALTER TABLE `OmimVarLocusIdSNP` ADD INDEX `i_omim_id` (`omim_id`);
 
CREATE TABLE IF NOT EXISTS `b129_SNPChrPosOnRef_36_3` (
`snp_id` INT NOT NULL,
`chr` VARCHAR(32) NULL,
`pos` INT NULL,
`orien` INT NULL,
`neighbor_snp_list` INT NULL,
`is_par` VARCHAR(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
 
ALTER TABLE `b129_SNPChrPosOnRef_36_3` ADD UNIQUE `i_snp_id` (`snp_id`);
ALTER TABLE `b129_SNPChrPosOnRef_36_3` ADD INDEX `i_chrpos` (`chr`,`pos`);
 
CREATE OR REPLACE VIEW `SNPChrPosOnRef` AS SELECT * FROM `b129_SNPChrPosOnRef_36_3`;
