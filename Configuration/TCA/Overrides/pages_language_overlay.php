<?php
if (!isset($GLOBALS['TCA']['pages_language_overlay']['columns']['tx_realurl_pathsegment'])) {

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages_language_overlay', array(
		'tx_realurl_pathsegment' => array(
			'label' => 'LLL:EXT:realurl/Resources/Private/Language/locallang_db.xlf:pages.tx_realurl_pathsegment',
			'exclude' => 1,
			'config' => array(
				'type' => 'input',
				'max' => 255,
				'default' => '',
				'eval' => 'trim,nospace,lower,DmitryDulepov\\Realurl\\Evaluator\\SegmentFieldCleaner'
			),
		),
	));

	\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages_language_overlay', 'tx_realurl_pathsegment', '1,3,4,6,7', 'after:nav_title');
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('pages_language_overlay', 'tx_realurl_pathsegment', '254', 'after:title');

	// Make sure that speaking path and related options are not set when copying pages -- thanks to University of Basel EasyWeb team for the bug report!
	if (!empty($GLOBALS['TCA']['pages_language_overlay']['ctrl']['setToDefaultOnCopy'])) {
	    $GLOBALS['TCA']['pages_language_overlay']['ctrl']['setToDefaultOnCopy'] .= ',';
	}
	$GLOBALS['TCA']['pages_language_overlay']['ctrl']['setToDefaultOnCopy'] .= 'tx_realurl_pathsegment';
}

