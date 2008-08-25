<?php
if (!defined ('TYPO3_MODE')) 	die ('Access denied.');

if (TYPO3_MODE=='BE')	{
//	t3lib_extMgm::addModule('tools','txrealurlM1','',t3lib_extMgm::extPath($_EXTKEY).'mod1/');

	// Add Web>Info module:
	t3lib_extMgm::insertModuleFunction(
		'web_info',
		'tx_realurl_modfunc1',
		t3lib_extMgm::extPath($_EXTKEY) . 'modfunc1/class.tx_realurl_modfunc1.php',
		'LLL:EXT:realurl/locallang_db.xml:moduleFunction.tx_realurl_modfunc1',
		'function',
		'online'
	);
}

$TCA['pages']['columns'] += array(
	'tx_realurl_pathsegment' => array(
		'label' => 'LLL:EXT:realurl/locallang_db.xml:pages.tx_realurl_pathsegment',
		'displayCond' => 'FIELD:tx_realurl_exclude:!=:1',
		'exclude' => 1,
		'config' => array (
			'type' => 'input',
			'max' => 60,
			'eval' => 'trim,nospace,lower'
			//'eval' => 'uniqueInPid'	// DON'T use this anyway, it is very confusing when a path is automatically set!
		),
	),
	'tx_realurl_exclude' => array(
		'label' => 'LLL:EXT:realurl/locallang_db.xml:pages.tx_realurl_exclude',
		'exclude' => 1,
		'config' => array (
			'type' => 'check',
			'items' => array(
				array('', ''),
			),
		),
	),
);

t3lib_extMgm::addToAllTCAtypes('pages', 'tx_realurl_pathsegment,tx_realurl_exclude', (t3lib_div::compat_version('4.2') ? '1' : '2'), 'after:nav_title');
t3lib_extMgm::addToAllTCAtypes('pages', 'tx_realurl_pathsegment,tx_realurl_exclude', (t3lib_div::compat_version('4.2') ? '' : '1,5,') . '4,254', 'after:title');

$TCA['pages_language_overlay']['columns'] += array(
	'tx_realurl_pathsegment' => array(
		'label' => 'LLL:EXT:realurl/locallang_db.xml:pages.tx_realurl_pathsegment',
		'exclude' => 1,
		'config' => array (
			'type' => 'input',
			'max' => 60,
			'eval' => 'trim,nospace,lower'
		),
	),
);

t3lib_extMgm::addToAllTCAtypes('pages_language_overlay', 'tx_realurl_pathsegment', t3lib_div::compat_version('4.2') ? '1,4' : '2,4,254', 'after:nav_title');

?>