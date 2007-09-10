<?php

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['tx_realurl'] = 'EXT:realurl/class.tx_realurl.php:&tx_realurl->encodeSpURL';
$TYPO3_CONF_VARS['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['tx_realurl'] = 'EXT:realurl/class.tx_realurl.php:&tx_realurl->decodeSpURL';
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearPageCacheEval']['tx_realurl'] = 'EXT:realurl/class.tx_realurl.php:&tx_realurl->clearPageCacheMgm';

$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearAllCache_additionalTables']['tx_realurl_urldecodecache'] = 'tx_realurl_urldecodecache';
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearAllCache_additionalTables']['tx_realurl_urlencodecache'] = 'tx_realurl_urlencodecache';
$TYPO3_CONF_VARS['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearAllCache_additionalTables']['tx_realurl_pathcache'] = 'tx_realurl_pathcache';

$TYPO3_CONF_VARS['FE']['addRootLineFields'].= ',tx_realurl_pathsegment';

// Include configuration file
$_realurl_conf = @unserialize($_EXTCONF);
if (is_array($_realurl_conf)) {
	$_realurl_conf_file = trim($_realurl_conf['configFile']);
	if ($_realurl_conf_file && @file_exists(PATH_site . $_realurl_conf_file)) {
		require_once(PATH_site . $_realurl_conf_file);
	}
	unset($_realurl_conf_file);
}
unset($_realurl_conf);

// Autoconfiguration
define('TX_REALURL_AUTOCONF_FILE', 'typo3temp/realurl_autoconf.php');
if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']) && !@include_once(PATH_site . TX_REALURL_AUTOCONF_FILE)) {
	require_once(t3lib_extMgm::extPath('realurl', 'class.tx_realurl_autoconfgen.php'));
	$_realurl_gen = t3lib_div::makeInstance('tx_realurl_autoconfgen');
	$_realurl_gen->generateConfiguration();
	unset($_realurl_gen);
	@include_once(PATH_site . TX_REALURL_AUTOCONF_FILE);
}

?>