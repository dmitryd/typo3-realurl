<?php
// Backend module is available only in TYPO3 7.6 or newer
if (version_compare(TYPO3_version, '7.6.0', '>=')) {
	$realurlConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl'];
	if (is_string($realurlConfiguration)) {
		$realurlConfiguration = (array)@unserialize($realurlConfiguration);
	}
	else {
		$realurlConfiguration = array();
	}

	$realurlModuleIcon = ((!isset($realurlConfiguration['moduleIcon']) || $realurlConfiguration['moduleIcon'] == 0) ? 'Module.svg' :
		($realurlConfiguration['moduleIcon'] == 1 ? 'Module2.svg' : 'Module3.svg')
	);

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
			'icon' => 'EXT:realurl/Resources/Public/Icons/' . $realurlModuleIcon,
			'labels' => 'LLL:EXT:realurl/Resources/Private/Language/locallang.xlf',
		)
	);

	unset($realurlConfiguration, $realurlModuleIcon);
}
