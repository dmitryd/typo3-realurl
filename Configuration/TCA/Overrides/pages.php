<?php

if (!isset($GLOBALS['TCA']['pages']['columns']['tx_realurl_pathsegment'])) {

	$columns = array(
		'tx_realurl_pathsegment' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_pathsegment',
			'displayCond' => 'FIELD:tx_realurl_exclude:!=:1',
			'exclude' => 1,
			'config' => array (
				'type' => 'input',
				'max' => 255,
				'eval' => 'trim,nospace,lower'
			),
		),
		'tx_realurl_pathoverride' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_path_override',
			'exclude' => 1,
			'config' => array (
				'type' => 'check',
				'items' => array(
					array('', '')
				)
			)
		),
		'tx_realurl_exclude' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_exclude',
			'exclude' => 1,
			'config' => array (
				'type' => 'check',
				'items' => array(
					array('', '')
				)
			)
		),
		'tx_realurl_nocache' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_nocache',
			'exclude' => 1,
			'config' => array (
				'type' => 'check',
				'items' => array(
					array('', ''),
				),
			),
		)
	);

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $columns);

//	$GLOBALS['TCA']['pages']['ctrl']['requestUpdate'] .= ',tx_realurl_exclude';

//	$TCA['pages']['palettes']['137'] = array(
//		'showitem' => 'tx_realurl_pathoverride'
//	);

//	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette('pages', '3', 'tx_realurl_nocache', 'after:cache_timeout');
//	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages', 'tx_realurl_pathsegment;;137;;,tx_realurl_exclude', '1', 'after:nav_title');
//	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages', 'tx_realurl_pathsegment;;137;;,tx_realurl_exclude', '4,199,254', 'after:title');


	$TCA['pages']['palettes']['137'] = array(
		'showitem' => ''
	);

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages', 'tx_realurl_pathsegment;;137;;', '1', 'after:nav_title');
	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages', 'tx_realurl_pathsegment;;137;;', '4,199,254', 'after:title');

	$columns = array(
		'tx_realurl_pathsegment' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_pathsegment',
			'exclude' => 1,
			'config' => array (
				'type' => 'input',
				'max' => 255,
				'eval' => 'trim,nospace,lower'
			),
		),
	);

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages_language_overlay', $columns);

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages_language_overlay', 'tx_realurl_pathsegment', '', 'after:nav_title');

	unset($columns);
}
