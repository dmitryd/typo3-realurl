<?php

########################################################################
# Extension Manager/Repository config file for ext "realurl".
#
# Auto generated 07-04-2010 11:50
#
# Manual updates:
# Only the data in the array - everything else is removed by next
# writing. "version" and "dependencies" must not be touched!
########################################################################

$EM_CONF[$_EXTKEY] = array(
	'title' => 'RealURL: speaking paths for TYPO3',
	'description' => 'Creates nice looking URLs for TYPO3 pages: converts http://example.com/index.phpid=12345&L=2 to http://example.com/path/to/your/page/. Please, ask for free support in TYPO3 mailing lists or contact the maintainer for paid support.',
	'category' => 'fe',
	'shy' => 0,
	'dependencies' => '',
	'conflicts' => 'cooluri,simulatestatic',
	'priority' => '',
	'loadOrder' => '',
	'module' => 'testmod',
	'state' => 'stable',
	'internal' => 0,
	'uploadfolder' => 0,
	'createDirs' => '',
	'modify_tables' => 'pages,sys_domain,pages_language_overlay,sys_template',
	'clearCacheOnLoad' => 0,
	'lockType' => '',
	'author' => 'Dmitry Dulepov',
	'author_email' => 'dmitry.dulepov@gmail.com',
	'author_company' => '',
	'CGLcompliance' => '',
	'CGLcompliance_note' => '',
	'version' => '1.8.0',
	'_md5_values_when_last_written' => 'a:26:{s:9:"ChangeLog";s:4:"0f10";s:10:"_.htaccess";s:4:"a6b1";s:20:"class.tx_realurl.php";s:4:"4b36";s:29:"class.tx_realurl_advanced.php";s:4:"1d12";s:32:"class.tx_realurl_autoconfgen.php";s:4:"e4e7";s:26:"class.tx_realurl_dummy.php";s:4:"6e1b";s:28:"class.tx_realurl_tcemain.php";s:4:"ea96";s:33:"class.tx_realurl_userfunctest.php";s:4:"dda5";s:21:"ext_conf_template.txt";s:4:"c890";s:12:"ext_icon.gif";s:4:"ea80";s:17:"ext_localconf.php";s:4:"9dff";s:14:"ext_tables.php";s:4:"8380";s:14:"ext_tables.sql";s:4:"979b";s:16:"locallang_db.xml";s:4:"fe70";s:43:"cleanup/class.tx_realurl_cleanuphandler.php";s:4:"a18c";s:12:"doc/TODO.txt";s:4:"b8cb";s:14:"doc/manual.sxw";s:4:"b1bf";s:13:"mod1/conf.php";s:4:"f960";s:14:"mod1/index.php";s:4:"9be3";s:18:"mod1/locallang.xml";s:4:"23df";s:22:"mod1/locallang_mod.xml";s:4:"9fd8";s:19:"mod1/moduleicon.png";s:4:"6b5a";s:38:"modfunc1/class.tx_realurl_modfunc1.php";s:4:"2907";s:22:"modfunc1/locallang.xml";s:4:"0593";s:16:"testmod/conf.php";s:4:"309a";s:17:"testmod/index.php";s:4:"d33b";}',
	'constraints' => array(
		'depends' => array(
			'php' => '5.2.0-0.0.0',
			'typo3' => '4.2.6-0.0.0',
		),
		'conflicts' => array(
			'cooluri' => '',
			'simulatestatic' => '',
		),
		'suggests' => array(
			'static_info_tables' => '2.0.2-',
		),
	),
	'suggests' => array(
	),
);

?>