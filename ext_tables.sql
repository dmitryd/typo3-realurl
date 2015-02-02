#
# Table structure for table 'tx_realurl_urlcache'
#
CREATE TABLE tx_realurl_urlcache (
	cache_id int(11) NOT NULL auto_increment,
	crdate int(11) DEFAULT '0' NOT NULL,
	page_id int(11) DEFAULT '0' NOT NULL,
	rootpage_id int(11) DEFAULT '0' NOT NULL,
	original_url text,
	speaking_url text,
	speaking_url_data text,

	PRIMARY KEY (cache_id),
	KEY pathq1 (rootpage_id,original_url(32)),
	KEY pathq2 (rootpage_id,speaking_url(32))
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_pathcache'
#
CREATE TABLE tx_realurl_pathcache (
	cache_id int(11) NOT NULL auto_increment,
	page_id int(11) DEFAULT '0' NOT NULL,
	language_id int(11) DEFAULT '0' NOT NULL,
	rootpage_id int(11) DEFAULT '0' NOT NULL,
	mpvar tinytext,
	pagepath text,
	expire int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (cache_id),
	KEY pathq1 (rootpage_id,pagepath(32),expire),
	KEY pathq2 (page_id,language_id,rootpage_id,expire),
	KEY expire (expire)
) ENGINE=InnoDB;

#
# Modifying pages table
#
CREATE TABLE pages (
	tx_realurl_pathsegment varchar(255) DEFAULT '' NOT NULL,
#	tx_realurl_pathoverride int(1) DEFAULT '0' NOT NULL,
#	tx_realurl_exclude int(1) DEFAULT '0' NOT NULL
);

#
# Modifying pages_language_overlay table
#
CREATE TABLE pages_language_overlay (
	tx_realurl_pathsegment varchar(255) DEFAULT '' NOT NULL
);
