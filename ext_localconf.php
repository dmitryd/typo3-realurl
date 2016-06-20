<?php

if (!defined('TX_REALURL_AUTOCONF_FILE')) {
	define('TX_REALURL_AUTOCONF_FILE', 'typo3conf/realurl_autoconf.php');
}

if (!function_exists('includeRealurlConfiguration')) {

	/**
	 * Includes RealURL configuration.
	 *
	 * @return void
	 */
	function includeRealurlConfiguration() {
		$configuration = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl'];
		if (is_string($configuration)) {
			$configuration = @unserialize($configuration);
		}

		if (!is_array($configuration)) {
			$configuration = array(
				'configFile' => 'typo3conf/realurl_conf.php',
				'enableAutoConf' => true,
			);
		}

		$realurlConfigurationFile = trim($configuration['configFile']);
		if ($realurlConfigurationFile && @file_exists(PATH_site . $realurlConfigurationFile)) {
			\TYPO3\CMS\Core\Utility\GeneralUtility::requireOnce(PATH_site . $realurlConfigurationFile);
		}
		elseif ($configuration['enableAutoConf']) {
			/** @noinspection PhpIncludeInspection */
			@include_once(PATH_site . TX_REALURL_AUTOCONF_FILE);
		}
	}
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['realurl'] = 'DmitryDulepov\\Realurl\\Encoder\\UrlEncoder->encodeUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc']['realurl'] = 'DmitryDulepov\\Realurl\\Encoder\\UrlEncoder->postProcessEncodedUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['realurl'] = 'DmitryDulepov\\Realurl\\Decoder\\UrlDecoder->decodeUrl';

includeRealurlConfiguration();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['realurl_all_caches'] = 'DmitryDulepov\\Realurl\\Hooks\\Cache->clearUrlCache';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['realurl_records'] = 'DmitryDulepov\\Realurl\\Hooks\\Cache->clearUrlCacheForRecords';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['realurl'] = 'DmitryDulepov\\Realurl\\Hooks\\DataHandler';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['realurl'] = 'DmitryDulepov\\Realurl\\Hooks\\DataHandler';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['realurl']['cacheImplementation'] = 'DmitryDulepov\\Realurl\\Cache\\DatabaseCache';

$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] .= ',tx_realurl_pathsegment,tx_realurl_exclude,tx_realurl_pathoverride';
$GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] .= ',tx_realurl_pathsegment';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals']['DmitryDulepov\\Realurl\\Evaluator\\SegmentFieldCleaner'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Classes/Evaluator/SegmentFieldCleaner.php');

