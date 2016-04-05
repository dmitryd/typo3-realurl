<?php

if (!defined('TX_REALURL_AUTOCONF_FILE')) {
	define('TX_REALURL_AUTOCONF_FILE', 'typo3conf/realurl_autoconf.php');
}

if (!function_exists('includeRealurlConfiguration')) {

	/**
	 * Makes sure that some known USER_INT plugins are not included to cHash
	 * calculation. This will dynamically adjust variables if certain
	 * parameters are found in the URL. This is useful for decoding only when
	 * there is no URL cache entry for the URL because in such case these
	 * parameters will create a cHash.
	 *
	 * @return void
	 * @see \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::makeCacheHash()
	 */
	function tx_realurl_fixCacheHashExcludeList() {
		$excludeList = &$GLOBALS['TYPO3_CONF_VARS']['FE']['cHashExcludedParameters'];
		$excludeArray = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', (string)$excludeList, true);
		$newExcludeArray = $excludeArray;

		$knownUserIntParameterPrefixes = array(
			'tx_felogin_pi1',
			'tx_form_form',
			'tx_indexedsearch',
			'tx_solr'
		);
		$knownUserIntParameters = array(
			'q', // solr
		);

		foreach ($knownUserIntParameterPrefixes as $userIntParameterPrefix) {
			$urlParameters = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET($userIntParameterPrefix);
			if (is_array($urlParameters)) {
				foreach (array_keys($urlParameters) as $parameterName) {
					$fullParameterName = $userIntParameterPrefix . '[' . $parameterName . ']';
					if (!in_array($fullParameterName, $newExcludeArray)) {
						$newExcludeArray[] = $fullParameterName;
					}
				}
				unset($parameterName, $urlParameters);
			}
		}
		foreach ($knownUserIntParameters as $userIntParameter) {
			$urlParameter = \TYPO3\CMS\Core\Utility\GeneralUtility::_GET($userIntParameter);
			if (is_string($urlParameter) && !in_array($userIntParameter, $newExcludeArray)) {
				$newExcludeArray[] = $userIntParameter;
			}
		}

		if (count($newExcludeArray) !== count($excludeArray)) {
			$excludeList = implode(',', $newExcludeArray);
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'])) {
				$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = $newExcludeArray;
			}
		}
	}

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
tx_realurl_fixCacheHashExcludeList();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['realurl_all_caches'] = 'DmitryDulepov\\Realurl\\Hooks\\Cache->clearUrlCache';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['realurl_records'] = 'DmitryDulepov\\Realurl\\Hooks\\Cache->clearUrlCacheForRecords';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['realurl'] = 'DmitryDulepov\\Realurl\\Hooks\\DataHandler';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['realurl'] = 'DmitryDulepov\\Realurl\\Hooks\\DataHandler';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['realurl']['cacheImplementation'] = 'DmitryDulepov\\Realurl\\Cache\\DatabaseCache';

$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] .= ',tx_realurl_pathsegment,tx_realurl_exclude,tx_realurl_pathoverride';
$GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] .= ',tx_realurl_pathsegment';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals']['DmitryDulepov\\Realurl\\Evaluator\\SegmentFieldCleaner'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Classes/Evaluator/SegmentFieldCleaner.php');

