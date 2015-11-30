<?php
\TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
    'DmitryDulepov.Realurl',
	'web',
	'realurl',
    '',
    array(
		'BackendModule' => 'index,aliases,cache,configuration',
	),
	array(
		'access' => 'user,group',
		'icon' => 'EXT:realurl/ext_icon.gif',
		'labels' => 'LLL:EXT:realurl/Resources/Private/Language/locallang.xml',
	)
);
