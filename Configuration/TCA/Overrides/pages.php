<?php

if (!isset($GLOBALS['TCA']['pages']['columns']['tx_realurl_pathsegment'])) {

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', array(
		'tx_realurl_pathsegment' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_pathsegment',
			'exclude' => 1,
			'config' => array (
				'type' => 'input',
				'max' => 255,
				'default' => '',
				'eval' => 'trim,nospace,lower,DmitryDulepov\\Realurl\\Evaluator\\SegmentFieldCleaner'
			),
		),
		'tx_realurl_pathoverride' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_path_override',
			'exclude' => 1,
			'config' => array (
				'type' => 'check',
				'default' => 0,
				'items' => array(
					array('LLL:EXT:lang/locallang_core.xlf:labels.enabled', '')
				)
			)
		),
		'tx_realurl_exclude' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_exclude',
			'exclude' => 1,
			'config' => array (
				'type' => 'check',
				'default' => 0,
				'items' => array(
					array('LLL:EXT:lang/locallang_core.xlf:labels.enabled', '')
				)
			)
		),
	));

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages', '--palette--;LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.palette_title;tx_realurl', '1,3', 'after:nav_title');
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages', '--palette--;LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.palette_title;tx_realurl', '4,7,199,254', 'after:title');

	$GLOBALS['TCA']['pages']['palettes']['tx_realurl'] = array(
		'showitem' => 'tx_realurl_pathsegment,--linebreak--,tx_realurl_exclude,tx_realurl_pathoverride'
	);

	// Make sure that speaking path and related options are not set when copying pages -- thanks to University of Basel EasyWeb team for the bug report!
	if (!empty($GLOBALS['TCA']['pages']['ctrl']['setToDefaultOnCopy'])) {
	    $GLOBALS['TCA']['pages']['ctrl']['setToDefaultOnCopy'] .= ',';
	}
	$GLOBALS['TCA']['pages']['ctrl']['setToDefaultOnCopy'] .= 'tx_realurl_pathsegment,tx_realurl_pathoverride,tx_realurl_exclude';
}
