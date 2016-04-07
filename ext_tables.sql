#
# Table structure for table 'tx_realurl_uniqalias'
#
CREATE TABLE tx_realurl_uniqalias (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tablename varchar(255) DEFAULT '' NOT NULL,
	field_alias varchar(255) DEFAULT '' NOT NULL,
	field_id varchar(60) DEFAULT '' NOT NULL,
	value_alias varchar(255) DEFAULT '' NOT NULL,
	value_id int(11) DEFAULT '0' NOT NULL,
	lang int(11) DEFAULT '0' NOT NULL,
	expire int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid),
	KEY tablename (tablename),
	KEY bk_realurl01 (field_alias(20),field_id,value_id,lang,expire),
	KEY bk_realurl02 (tablename(32),field_alias(20),field_id,value_alias(20),expire)
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_uniqalias_cache_map'
#
CREATE TABLE tx_realurl_uniqalias_cache_map (
	alias_uid int(11) DEFAULT '0' NOT NULL,
	url_cache_id varchar(255) DEFAULT '' NOT NULL,

	KEY check_existence (alias_uid,url_cache_id(6))
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_urlcache'
#
CREATE TABLE tx_realurl_urlcache (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	page_id int(11) DEFAULT '0' NOT NULL,
	rootpage_id int(11) DEFAULT '0' NOT NULL,
	original_url text,
	speaking_url text,
	request_variables text,

	PRIMARY KEY (uid),
	KEY parent (pid),
	KEY pathq1 (rootpage_id,original_url(32)),
	KEY pathq2 (rootpage_id,speaking_url(32))
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_pathcache'
#
CREATE TABLE tx_realurl_pathcache (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	page_id int(11) DEFAULT '0' NOT NULL,
	language_id int(11) DEFAULT '0' NOT NULL,
	rootpage_id int(11) DEFAULT '0' NOT NULL,
	mpvar tinytext,
	pagepath text,
	expire int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	KEY parent (pid),
	KEY pathq1 (rootpage_id,pagepath(32),expire),
	KEY pathq2 (page_id,language_id,rootpage_id,expire),
	KEY expire (expire)
) ENGINE=InnoDB;

#
# Modifying pages table
#
CREATE TABLE pages (
	tx_realurl_pathsegment varchar(255) DEFAULT '' NOT NULL,
	tx_realurl_pathoverride int(1) DEFAULT '0' NOT NULL,
	tx_realurl_exclude int(1) DEFAULT '0' NOT NULL,
	tx_realurl_nocache int(1) DEFAULT '0' NOT NULL
);

#
# Modifying pages_language_overlay table
#
CREATE TABLE pages_language_overlay (
	tx_realurl_pathsegment varchar(255) DEFAULT '' NOT NULL
);
