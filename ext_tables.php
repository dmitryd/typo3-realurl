<?php
// Backend module is available only in TYPO3 7.6 or newer
if (version_compare(TYPO3_version, '7.6.0', '>=')) {
	\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
		'DmitryDulepov.Realurl',
		'web',
		'realurl',
		'',
		array(
			'Overview' => 'index',
			'Aliases' => 'index,edit,delete,deleteAll',
			'UrlCache' => 'index,delete,deleteAll,flush',
			'PathCache' => 'index,delete',
		),
		array(
			'access' => 'user,group',
			'icon' => 'EXT:realurl/ext_icon.gif',
			'labels' => 'LLL:EXT:realurl/Resources/Private/Language/locallang.xlf',
		)
	);
}
