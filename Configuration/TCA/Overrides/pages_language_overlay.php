<?php
if (!isset($GLOBALS['TCA']['pages_language_overlay']['columns']['tx_realurl_pathsegment'])) {

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages_language_overlay', array(
		'tx_realurl_pathsegment' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_pathsegment',
			'exclude' => 1,
			'config' => array(
				'type' => 'input',
				'max' => 255,
				'eval' => 'trim,nospace,lower,uniqueInPid,DmitryDulepov\\Realurl\\Evaluator\\SegmentFieldCleaner'
			),
		),
	));

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages_language_overlay', 'tx_realurl_pathsegment', '', 'after:nav_title');
}

