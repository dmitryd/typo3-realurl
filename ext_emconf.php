<?php

########################################################################
# Extension Manager/Repository config file for ext: "realurl"
#
# Auto generated 10-10-2006 09:41
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'RealURL: URLs like normal websites',
	'description' => 'Use the Path as shown in the Back-End in the URL, like: http://mydomain.com/en/products/product1/detailed_description/. Has some very neat extra features.',
	'category' => 'fe',
	'shy' => 0,
	'dependencies' => '',
	'conflicts' => '',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'testmod',
	'state' => 'beta',
	'internal' => 0,
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => 'pages,sys_domain',
	'clearCacheOnLoad' => 1,
	'lockType' => '',
	'author' => 'Martin Poelstra',
	'author_email' => 'martin@beryllium.net',
	'author_company' => 'Beryllium Engineering',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '1.1.3',
	'_md5_values_when_last_written' => 'a:17:{s:9:"ChangeLog";s:4:"7a91";s:10:"_.htaccess";s:4:"a6b1";s:20:"class.tx_realurl.php";s:4:"455d";s:29:"class.tx_realurl_advanced.php";s:4:"4a99";s:26:"class.tx_realurl_dummy.php";s:4:"a824";s:33:"class.tx_realurl_userfunctest.php";s:4:"2cfe";s:12:"ext_icon.gif";s:4:"ea80";s:17:"ext_localconf.php";s:4:"6b61";s:14:"ext_tables.php";s:4:"0c3c";s:14:"ext_tables.sql";s:4:"46a0";s:16:"locallang_db.php";s:4:"b14f";s:12:"doc/TODO.txt";s:4:"5419";s:14:"doc/manual.sxw";s:4:"a14c";s:38:"modfunc1/class.tx_realurl_modfunc1.php";s:4:"aaec";s:22:"modfunc1/locallang.php";s:4:"984b";s:16:"testmod/conf.php";s:4:"309a";s:17:"testmod/index.php";s:4:"7990";}',
	'constraints' => array(
		'depends' => array(
			'php' => '4.0.0-',
			'typo3' => '4.0.0-',
		),
		'conflicts' => array(
		),
		'suggests' => array(
		),
	),
	'suggests' => array(
	),
);

?>