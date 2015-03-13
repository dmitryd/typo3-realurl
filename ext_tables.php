<?php
if (version_compare(TYPO3_branch, '6.2', '<')) {
	t3lib_div::loadTCA('pages');
	$extensionMamagementUtility = 't3lib_extMgm';
	$generalUtility = 't3lib_div';
	$isLegacyVersion = TRUE;
}
else {
	$extensionMamagementUtility = '\\TYPO3\\CMS\\Core\\Utility\\ExtensionManagementUtility';
	$generalUtility = '\\TYPO3\\CMS\\Core\\Utility\\GeneralUtility';
	$isLegacyVersion = FALSE;
}
/** @var t3lib_extMgm|\TYPO3\CMS\Core\Utility\ExtensionManagementUtility $extensionMamagementUtility */
/** @var t3lib_div|\TYPO3\CMS\Core\Utility\GeneralUtility $generalUtility */

if (TYPO3_MODE == 'BE')	{
	// Add Web>Info module
	$extensionMamagementUtility::insertModuleFunction(
		'web_info',
		'tx_realurl_modfunc1',
		$isLegacyVersion ? $extensionMamagementUtility::extPath('realurl') . 'modfunc1/class.tx_realurl_modfunc1.php' : NULL,
		'LLL:EXT:realurl/locallang_db.xml:moduleFunction.tx_realurl_modfunc1',
		'function',
		'online'
	);
}

$GLOBALS['TCA']['pages']['columns'] += array(
	'tx_realurl_pathsegment' => array(
		'label' => 'LLL:EXT:realurl/locallang_db.xml:pages.tx_realurl_pathsegment',
		'displayCond' => 'FIELD:tx_realurl_exclude:!=:1',
		'exclude' => 1,
		'config' => array (
			'type' => 'input',
			'max' => 255,
			'eval' => 'trim,nospace,lower'
		),
	),
	'tx_realurl_pathoverride' => array(
		'label' => 'LLL:EXT:realurl/locallang_db.xml:pages.tx_realurl_path_override',
		'exclude' => 1,
		'config' => array (
			'type' => 'check',
			'items' => array(
				array('', '')
			)
		)
	),
	'tx_realurl_exclude' => array(
		'label' => 'LLL:EXT:realurl/locallang_db.xml:pages.tx_realurl_exclude',
		'exclude' => 1,
		'config' => array (
			'type' => 'check',
			'items' => array(
				array('', '')
			)
		)
	),
	'tx_realurl_nocache' => array(
		'label' => 'LLL:EXT:realurl/locallang_db.xml:pages.tx_realurl_nocache',
		'exclude' => 1,
		'config' => array (
			'type' => 'check',
			'items' => array(
				array('', ''),
			),
		),
	)
);

$GLOBALS['TCA']['pages']['ctrl']['requestUpdate'] .= ',tx_realurl_exclude';

$GLOBALS['TCA']['pages']['palettes']['137'] = array(
	'showitem' => 'tx_realurl_pathoverride'
);

$extensionMamagementUtility::addFieldsToPalette('pages', '3', 'tx_realurl_nocache', 'after:cache_timeout');
$extensionMamagementUtility::addToAllTCAtypes('pages', 'tx_realurl_pathsegment;;137;;,tx_realurl_exclude', '1', 'after:nav_title');
$extensionMamagementUtility::addToAllTCAtypes('pages', 'tx_realurl_pathsegment;;137;;,tx_realurl_exclude', '4,199,254', 'after:title');

$extensionMamagementUtility::addLLrefForTCAdescr('pages','EXT:realurl/locallang_csh.xml');

$GLOBALS['TCA']['pages_language_overlay']['columns'] += array(
	'tx_realurl_pathsegment' => array(
		'label' => 'LLL:EXT:realurl/locallang_db.xml:pages.tx_realurl_pathsegment',
		'exclude' => 1,
		'config' => array (
			'type' => 'input',
			'max' => 255,
			'eval' => 'trim,nospace,lower'
		),
	),
);

$extensionMamagementUtility::addToAllTCAtypes('pages_language_overlay', 'tx_realurl_pathsegment', '', 'after:nav_title');

unset($extensionMamagementUtility, $generalUtility, $isLegacyVersion);

?>