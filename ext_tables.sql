#
# Table structure for table 'tx_realurl_pathcache'
#
CREATE TABLE tx_realurl_pathcache (
  cache_id int(11) DEFAULT '0' NOT NULL auto_increment,
  page_id int(11) DEFAULT '0' NOT NULL,
  language_id int(11) DEFAULT '0' NOT NULL,
  hash varchar(10) DEFAULT '' NOT NULL,
  pagepath text NOT NULL,
  expire int(11) DEFAULT '0' NOT NULL,
  
  PRIMARY KEY (cache_id),
  KEY hash (hash)
);
