<?php
$extpath = (version_compare(TYPO3_branch, '6.0', '<') ? t3lib_extMgm::extPath('realurl') : \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl'));
return array(
	'tx_realurl' => $extpath . 'class.tx_realurl.php',
	'tx_realurl_autoconfgen' => $extpath . 'class.tx_realurl_autoconfgen.php',
	'tx_realurl_modfunc1' => $extpath . 'modfunc1/class.tx_realurl_modfunc1.php',
	'tx_realurl_pagebrowser' => $extpath . 'modfunc1/class.tx_realurl_pagebrowser.php',
	'tx_realurl_apiwrapper' => $extpath . 'apiwrappers/class.tx_realurl_apiwrapper.php',
	'tx_realurl_apiwrapper_4x' => $extpath . 'apiwrappers/class.tx_realurl_apiwrapper_4x.php',
	'tx_realurl_apiwrapper_6x' => $extpath . 'apiwrappers/class.tx_realurl_apiwrapper_6x.php',
);
