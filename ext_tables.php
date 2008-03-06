<?php
if (!defined ("TYPO3_MODE")) 	die ("Access denied.");

if (TYPO3_MODE=="BE")	{

		//
	t3lib_extMgm::addModule("tools","txrealurlM1","",t3lib_extMgm::extPath($_EXTKEY)."mod1/");


		// Add Web>Info module:
	t3lib_extMgm::insertModuleFunction(
		'web_info',
		'tx_realurl_modfunc1',
		t3lib_extMgm::extPath($_EXTKEY).'modfunc1/class.tx_realurl_modfunc1.php',
		'LLL:EXT:realurl/locallang_db.php:moduleFunction.tx_realurl_modfunc1',
		'function',
		'online'
	);
}

$TCA['pages']['columns']['tx_realurl_pathsegment'] = array(
	'label' => 'LLL:EXT:realurl/locallang_db.php:pages.tx_realurl_pathsegment',
	'exclude' => 1,
	'config' => Array (
		'type' => 'input',
		'size' => '30',
		'max' => '30',
		//'eval' => 'uniqueInPid'	// DON'T use this anyway, it is very confusing when a path is automatically set!
	)
);

t3lib_extMgm::addToAllTCAtypes('pages','tx_realurl_pathsegment','2','after:nav_title');

?>