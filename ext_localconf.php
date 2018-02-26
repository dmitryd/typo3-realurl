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

		$existingConfiguration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];

		$realurlConfigurationFile = trim($configuration['configFile']);
		if ($realurlConfigurationFile && @file_exists(PATH_site . $realurlConfigurationFile)) {
			/** @noinspection PhpIncludeInspection */
			require_once(PATH_site . $realurlConfigurationFile);
		}
		elseif ($configuration['enableAutoConf'] && file_exists(PATH_site . TX_REALURL_AUTOCONF_FILE)) {
			/** @noinspection PhpIncludeInspection */
			require_once(PATH_site . TX_REALURL_AUTOCONF_FILE);
		}

		if (is_array($existingConfiguration)) {
			\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule(
				$existingConfiguration,
				$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']
			);
			$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'] = $existingConfiguration;
		}
	}
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['realurl'] = 'DmitryDulepov\\Realurl\\Encoder\\UrlEncoder->encodeUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc']['realurl'] = 'DmitryDulepov\\Realurl\\Encoder\\UrlEncoder->postProcessEncodedUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['realurl'] = 'DmitryDulepov\\Realurl\\Decoder\\UrlDecoder->decodeUrl';

includeRealurlConfiguration();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['realurl'] = 'DmitryDulepov\\Realurl\\Hooks\\DataHandler';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass']['realurl'] = 'DmitryDulepov\\Realurl\\Hooks\\DataHandler';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['realurl']['cacheImplementation'] = 'DmitryDulepov\\Realurl\\Cache\\DatabaseCache';

$GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] .= ($GLOBALS['TYPO3_CONF_VARS']['FE']['addRootLineFields'] ? ',' : '') . 'tx_realurl_pathsegment,tx_realurl_exclude,tx_realurl_pathoverride';
$GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] .= ($GLOBALS['TYPO3_CONF_VARS']['FE']['pageOverlayFields'] ? ',' : '' ) . 'tx_realurl_pathsegment';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tce']['formevals']['DmitryDulepov\\Realurl\\Evaluator\\SegmentFieldCleaner'] = \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('realurl', 'Classes/Evaluator/SegmentFieldCleaner.php');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:realurl/Configuration/TSConfig.txt">');

// Scheduler clean up of expired tables
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask']['options']['tables']['tx_realurl_urldata'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask']['options']['tables']['tx_realurl_urldata'] = array(
        'expireField' => 'expire',
    );
}
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask']['options']['tables']['tx_realurl_pathdata'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask']['options']['tables']['tx_realurl_pathdata'] = array(
        'expireField' => 'expire',
    );
}
if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask']['options']['tables']['tx_realurl_uniqalias'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks']['TYPO3\\CMS\\Scheduler\\Task\\TableGarbageCollectionTask']['options']['tables']['tx_realurl_uniqalias'] = array(
        'expireField' => 'expire',
    );
}

// Exclude gclid from cHash because TYPO3 does not do that
if (!in_array('gclid', $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'])) {
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = 'gclid';
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cHashExcludedParameters'] .= ', gclid';
}
