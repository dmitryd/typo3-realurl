<?php

########################################################################
# Extension Manager/Repository config file for ext: "realurl"
#
# Auto generated 26-05-2008 12:21
#
# Manual updates:
# Only the data in the array - anything else is removed by next write.
# "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'RealURL: URLs like normal websites',
	'description' => 'Creates nice looking URLs for TYPO3 web pages. Public free support is provided only through TYPO3 mailing lists! Contact by e-mail for commercial support.',
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
	'version' => '1.4.0',
	'_md5_values_when_last_written' => 'a:20:{s:9:"ChangeLog";s:4:"3708";s:10:"_.htaccess";s:4:"a6b1";s:20:"class.tx_realurl.php";s:4:"5450";s:29:"class.tx_realurl_advanced.php";s:4:"0331";s:32:"class.tx_realurl_autoconfgen.php";s:4:"efca";s:26:"class.tx_realurl_dummy.php";s:4:"d1f5";s:28:"class.tx_realurl_tcemain.php";s:4:"c46d";s:33:"class.tx_realurl_userfunctest.php";s:4:"750e";s:21:"ext_conf_template.txt";s:4:"5b1a";s:12:"ext_icon.gif";s:4:"ea80";s:17:"ext_localconf.php";s:4:"11ca";s:14:"ext_tables.php";s:4:"879a";s:14:"ext_tables.sql";s:4:"3eea";s:16:"locallang_db.xml";s:4:"c029";s:12:"doc/TODO.txt";s:4:"b8cb";s:14:"doc/manual.sxw";s:4:"7bc7";s:38:"modfunc1/class.tx_realurl_modfunc1.php";s:4:"1968";s:22:"modfunc1/locallang.xml";s:4:"0593";s:16:"testmod/conf.php";s:4:"309a";s:17:"testmod/index.php";s:4:"024e";}',
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