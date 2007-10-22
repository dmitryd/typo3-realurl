<?php

########################################################################
# Extension Manager/Repository config file for ext: "realurl"
#
# Auto generated 21-10-2007 14:57
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
	'conflicts' => 'cooluri',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'testmod',
	'state' => 'stable',
	'internal' => 0,
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => 'pages,sys_domain',
	'clearCacheOnLoad' => 1,
	'lockType' => '',
	'author' => 'Martin Poelstra, Kasper Skaarhoj, Dmitry Dulepov',
	'author_email' => 'dmitry@typo3.org',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '1.2.0',
	'_md5_values_when_last_written' => 'a:19:{s:9:"ChangeLog";s:4:"92b6";s:10:"_.htaccess";s:4:"a6b1";s:20:"class.tx_realurl.php";s:4:"ed72";s:29:"class.tx_realurl_advanced.php";s:4:"4d2b";s:32:"class.tx_realurl_autoconfgen.php";s:4:"d1fb";s:26:"class.tx_realurl_dummy.php";s:4:"bdd3";s:33:"class.tx_realurl_userfunctest.php";s:4:"750e";s:21:"ext_conf_template.txt";s:4:"3a85";s:12:"ext_icon.gif";s:4:"ea80";s:17:"ext_localconf.php";s:4:"fdd5";s:14:"ext_tables.php";s:4:"888e";s:14:"ext_tables.sql";s:4:"5079";s:16:"locallang_db.php";s:4:"57ab";s:12:"doc/TODO.txt";s:4:"5419";s:14:"doc/manual.sxw";s:4:"8b28";s:38:"modfunc1/class.tx_realurl_modfunc1.php";s:4:"aaec";s:22:"modfunc1/locallang.php";s:4:"984b";s:16:"testmod/conf.php";s:4:"309a";s:17:"testmod/index.php";s:4:"7990";}',
	'constraints' => array(
		'depends' => array(
			'php' => '4.0.0-0.0.0',
			'typo3' => '4.0.0-0.0.0',
		),
		'conflicts' => array(
			'cooluri' => '',
		),
		'suggests' => array(
			'static_info_tables' => '2.0.2-',
		),
	),
	'suggests' => array(
	),
);

?>