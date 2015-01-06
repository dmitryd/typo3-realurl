#
# Table structure for table 'tx_realurl_pathcache'
#
CREATE TABLE tx_realurl_pathcache (
	cache_id int(11) NOT NULL auto_increment,
	page_id int(11) DEFAULT '0' NOT NULL,
	language_id int(11) DEFAULT '0' NOT NULL,
	rootpage_id int(11) DEFAULT '0' NOT NULL,
	mpvar tinytext NOT NULL,
	pagepath text NOT NULL,
	expire int(11) DEFAULT '0' NOT NULL,

	PRIMARY KEY (cache_id),
	KEY pathq1 (rootpage_id,pagepath(32),expire),
	KEY pathq2 (page_id,language_id,rootpage_id,expire),
	KEY expire (expire)
) ENGINE=InnoDB;
