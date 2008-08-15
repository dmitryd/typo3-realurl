#
# Table structure for table 'tx_realurl_pathcache'
#
CREATE TABLE tx_realurl_pathcache (
  cache_id int(11) NOT NULL auto_increment,
  page_id int(11) DEFAULT '0' NOT NULL,
  language_id int(11) DEFAULT '0' NOT NULL,
  rootpage_id int(11) DEFAULT '0' NOT NULL,
  mpvar tinytext NOT NULL,
  hash varchar(10) DEFAULT '' NOT NULL,
  pagepath text NOT NULL,
  expire int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (cache_id),
  KEY pathq1 (hash,rootpage_id,expire)
  KEY pathq2 (page_id,language_id,expire)
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_uniqalias'
#
CREATE TABLE tx_realurl_uniqalias (
  uid int(11) NOT NULL auto_increment,
  tstamp int(11) DEFAULT '0' NOT NULL,
  tablename varchar(50) DEFAULT '' NOT NULL,
  field_alias varchar(30) DEFAULT '' NOT NULL,
  field_id varchar(30) DEFAULT '' NOT NULL,
  value_alias varchar(255) DEFAULT '' NOT NULL,
  value_id int(11) DEFAULT '0' NOT NULL,
  lang int(11) DEFAULT '0' NOT NULL,
  expire int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (uid),
  KEY tablename (tablename),
  KEY bk_realurl01 (field_alias,field_id,value_id,lang,expire),
  KEY bk_realurl02 (tablename,field_alias,field_id,value_alias(220),expire)
);

#
# Table structure for table 'tx_realurl_chashcache'
#
CREATE TABLE tx_realurl_chashcache (
  spurl_hash int(11) DEFAULT '0' NOT NULL,
  chash_string varchar(10) DEFAULT '' NOT NULL,

  PRIMARY KEY (spurl_hash),
  KEY tablename (chash_string)
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_urldecodecache'
# Cache for Speaking URLS when translated to internal GET vars.
# Flushable
#
CREATE TABLE tx_realurl_urldecodecache (
  url_hash varchar(32) DEFAULT '' NOT NULL,
  spurl tinytext NOT NULL,
  content blob NOT NULL,
  page_id int(11) DEFAULT '0' NOT NULL,
  rootpage_id int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (url_hash),
  KEY page_id (page_id),
) ENGINE=InnoDB;

#
# Table structure for table 'tx_realurl_urlencodecache'
# Cache for GEt parameter strings being translated to Speaking URls.
# Flushable
#
CREATE TABLE tx_realurl_urlencodecache (
  url_hash varchar(32) DEFAULT '' NOT NULL,
  origparams tinytext NOT NULL,
  internalExtras tinytext NOT NULL,
  content text NOT NULL,
  page_id int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (url_hash),
  KEY page_id (page_id)
) ENGINE=InnoDB;

CREATE TABLE tx_realurl_errorlog (
  url_hash int(11) DEFAULT '0' NOT NULL,
  url text NOT NULL,
  error text NOT NULL,
  last_referer text NOT NULL,
  counter int(11) DEFAULT '0' NOT NULL,
  cr_date int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  rootpage_id int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (url_hash,rootpage_id),
  KEY counter (counter,tstamp)
);

CREATE TABLE tx_realurl_redirects (
  url_hash int(11) DEFAULT '0' NOT NULL,
  url text NOT NULL,
  destination text NOT NULL,
  last_referer text NOT NULL,
  counter int(11) DEFAULT '0' NOT NULL,
  tstamp int(11) DEFAULT '0' NOT NULL,
  has_moved int(11) DEFAULT '0' NOT NULL,

  PRIMARY KEY (url_hash)
);

#
# Modifying pages table
#
CREATE TABLE pages (
	tx_realurl_pathsegment varchar(60) DEFAULT '' NOT NULL,
	tx_realurl_exclude int(1) DEFAULT '0' NOT NULL,
);

#
# Modifying pages_language_overlay table
#
CREATE TABLE pages_language_overlay (
	tx_realurl_pathsegment varchar(60) DEFAULT '' NOT NULL,
);

#
# Modifying sys_domain table
#
CREATE TABLE sys_domain (
	KEY tx_realurl (domainName,hidden)
);
