<?php
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['realurl'] = 'DmitryDulepov\Realurl\Encoder\UrlEncoder->encodeUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_content.php']['typoLink_PostProc']['realurl'] = 'DmitryDulepov\Realurl\Encoder\UrlEncoder->postProcessEncodedUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['realurl'] = 'DmitryDulepov\Realurl\Decoder\UrlDecoder->decodeUrl';

if (!function_exists('includeRealurlConfiguration')) {
	/**
	 * Includes RealURL configuration.
	 *
	 * @return void
	 */
	function includeRealurlConfiguration() {
		$configuration = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
		if (is_array($configuration)) {
			$realurlConfigurationFile = trim($configuration['configFile']);
			if ($realurlConfigurationFile && @file_exists(PATH_site . $realurlConfigurationFile)) {
				/** @noinspection PhpIncludeInspection */
				require_once(PATH_site . $realurlConfigurationFile);
			}
			unset($realurlConfigurationFile);


			if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']) && $configuration['enableAutoConf']) {
				if (!defined('TX_REALURL_AUTOCONF_FILE')) {
					define('TX_REALURL_AUTOCONF_FILE', 'typo3conf/realurl_autoconf.php');
				}
				/** @noinspection PhpIncludeInspection */
				@include_once(PATH_site . TX_REALURL_AUTOCONF_FILE);
			}
		}
	}

	includeRealurlConfiguration();

	/*
	 * Note: cache initialization below allows extensions to set their own RealURL caches.
	 * For that extensions had to be loaded before RealURL.
	 */

	if (!is_array($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][\DmitryDulepov\Realurl\EncodeDecoderBase::URL_CACHE_ID])) {
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'][\DmitryDulepov\Realurl\EncodeDecoderBase::URL_CACHE_ID] = array(
			'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\Typo3DatabaseBackend',
			'frontend' => 'TYPO3\\CMS\\Core\\Cache\\Frontend\\VariableFrontend',
			'groups' => array('all', 'realurl'),
			'options' => array(
				'defaultLifetime' => 31*24*60*60, // 31 days
			),
		);
	}
}
