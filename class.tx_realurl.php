<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2004 Kasper Skaarhoj (kasper@typo3.com)
 *  (c) 2005-2010 Dmitry Dulepov (dmitry@typo3.org)
 *  All rights reserved
 *
 *  This script is part of the Typo3 project. The Typo3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
/**
 * Class for creating and parsing Speaking Urls
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *  107: class tx_realurl
 *
 *              SECTION: Translate parameters to a Speaking URL (t3lib_tstemplate::linkData)
 *  152:     function tx_realurl()
 *  170:     function encodeSpURL(&$params, $ref)
 *  241:     function encodeSpURL_doEncode($inputQuery, $cHashCache = FALSE, $origUrl = '')
 *  324:     function encodeSpURL_pathFromId(&$paramKeyValues, &$pathParts)
 *  353:     function encodeSpURL_gettingPostVarSets(&$paramKeyValues, &$pathParts, $postVarSetCfg)
 *  390:     function encodeSpURL_fileName(&$paramKeyValues)
 *  412:     function encodeSpURL_setSequence($varSetCfg, &$paramKeyValues, &$pathParts)
 *  516:     function encodeSpURL_setSingle($keyWord, $keyValues, &$paramKeyValues, &$pathParts)
 *  550:     function encodeSpURL_encodeCache($urlToEncode, $internalExtras, $setEncodedURL = '')
 *  617:     function encodeSpURL_cHashCache($newUrl, &$paramKeyValues)
 *
 *              SECTION: Translate a Speaking URL to parameters (tslib_fe)
 *  669:     function decodeSpURL($params, $ref)
 *  759:     function decodeSpURL_checkRedirects($speakingURIpath)
 *  801:     function decodeSpURL_doDecode($speakingURIpath, $cHashCache = FALSE)
 *  877:     function decodeSpURL_createQueryStringParam($paramArr, $prependString = '')
 *  900:     function decodeSpURL_createQueryString(&$getVars)
 *  925:     function decodeSpURL_idFromPath(&$pathParts)
 *  966:     function decodeSpURL_settingPreVars(&$pathParts, $config)
 *  989:     function decodeSpURL_settingPostVarSets(&$pathParts, $postVarSetCfg)
 * 1054:     function decodeSpURL_fixBrackets(&$arr)
 * 1080:     function decodeSpURL_fileName($fileName)
 * 1108:     function decodeSpURL_getSequence(&$pathParts, $setupArr)
 * 1201:     function decodeSpURL_getSingle($keyValues)
 * 1217:     function decodeSpURL_throw404($msg)
 * 1251:     function decodeSpURL_jumpAdmin()
 * 1275:     function decodeSpURL_jumpAdmin_goBackend($pageId)
 * 1290:     function decodeSpURL_decodeCache($speakingURIpath, $cachedInfo = '')
 * 1354:     function decodeSpURL_cHashCache($speakingURIpath)
 *
 *              SECTION: Alias-ID look up functions
 * 1385:     function lookUpTranslation($cfg, $value, $aliasToUid = FALSE)
 * 1495:     function lookUp_uniqAliasToId($cfg, $aliasValue, $onlyNonExpired = FALSE)
 * 1522:     function lookUp_idToUniqAlias($cfg, $idValue, $lang, $aliasValue = '')
 * 1550:     function lookUp_newAlias($cfg, $newAliasValue, $idValue, $lang)
 * 1620:     function lookUp_cleanAlias($cfg, $newAliasValue)
 *
 *              SECTION: General helper functions (both decode/encode)
 * 1665:     function setConfig()
 * 1699:     function getPostVarSetConfig($page_id, $mainCat = 'postVarSets')
 * 1721:     function pageAliasToID($alias)
 * 1744:     function rawurlencodeParam($str)
 * 1759:     function checkCondition($setup, $prevVal)
 * 1777:     function isBEUserLoggedIn()
 *
 *              SECTION: External Hooks
 * 1794:     function clearPageCacheMgm($params, $ref)
 * 1809:     function isString(&$str, $paramName)
 * 1834:     function findRootPageId($host = '')
 *
 * TOTAL FUNCTIONS: 41
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * Class for creating and parsing Speaking Urls
 * This class interfaces with hooks in TYPO3 inside tslib_fe (for parsing speaking URLs to GET parameters) and in t3lib_tstemplate (for parsing GET parameters into a speaking URL)
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 * @package TYPO3
 * @subpackage tx_realurl
 */
class tx_realurl {

	// External, static:
	var $NA = '-'; // Substitute value for "blank" values
	var $maxLookUpLgd = 100; // Max. length of look-up strings. Just a "brake"
	var $prefixEnablingSpURL = 'index.php'; // Only work Speaking URL on URLs starting with "index.php"
	var $decodeCacheTTL = 1; // TTL for decode cache, default is 1 day.
	var $encodeCacheTTL = 1; // TTL for encode cache, default is 1 day.


	// Internal:
	/** @var tslib_fe */
	var $pObj; // tslib_fe / GLOBALS['TSFE'] (for ->decodeSpURL())
	var $extConf; // Configuration for extension, from $TYPO3_CONF_VARS['EXTCONF']['realurl']
	var $adminJumpSet = FALSE; // Is set true (->encodeSpURL) if AdminJump is active in some way. Is set false again when captured first time!
	var $fe_user_prefix_set = FALSE; // Is set true (->encodeSpURL) if there is a frontend user logged in
	var $filePart; // Contains the filename when a Speaking URL is decoded.
	var $dirParts; // All directory parts of the string
	var $orig_paramKeyValues = array(); // Contains the index of GETvars that the URL had when the encoding began.
	var $appendedSlash = false; // Set true if slash is appended
	var $encodePageId = 0; // Set with the page id during encoding. for internal use only.
	var $speakingURIpath_procValue = ''; // For decoding, the path we are processing.
	var $disableDecodeCache = FALSE; // If set internally, decode caching is disabled. Used when a 303 header is set in tx_realurl_advanced.


	var $decode_editInBackend = FALSE; // If set (in adminjump function) then we will redirect to edit the found page id in the backend.
	var $encodeError = FALSE; // If set true encoding failed , probably because the url was outside of root line - and the input url is returned directly.


	var $host = ''; // Current host name. Set in setConfig()

	/**
	 * Actual host name (configuration key) for the current request. This can
	 * be different from the $this->host if there are host aliases.
	 *
	 * @var string
	 */
	protected $hostConfigured = '';

	var $multidomain = false;
	var $urlPrepend = array();

	var $useMySQLExtendedSyntax = false;

	/**
	 * Inidicates wwether devLog is enabled
	 *
	 * @var true
	 */
	protected $enableDevLog = false;

	/**
	 * Contains a request id. This is to simplify identification of a single
	 * request when the site is accessed concurently
	 *
	 * @var string
	 */
	protected $devLogId;

	/**
	 * Mime type that can be set according to the file extension (decoding only).
	 *
	 * @var string
	 */
	protected $mimeType = null;

	var $enableStrictMode = false;
	var $enableChashDebug = false;

	/**
	 * If non-empty, corresponding URL query parameter will be ignored in preVars
	 * (note: preVars only!). This is necessary for _DOMAINS feature. This value
	 * is set to empty in adjustConfigurationByHostEncode().
	 *
	 * @see tx_realurl::adjustConfigurationByHostEncode()
	 * @see tx_realurl::encodeSpURL_doEncode()
	 * @var	string
	 */
	protected $ignoreGETvar;

	/**
	 * Contains URL parameters that were merged into URL. This is necessary
	 * if cHash has to be recalculated due to bypassed parameters. Used during
	 * encoding only.
	 *
	 * @var array
	 * @see http://bugs.typo3.org/view.php?id=11219
	 */
	protected $cHashParameters;

	/**
	 * Indicates wether cHash should be rebuilt for the URL. Used during
	 * encoding only.
	 *
	 * @var boolean
	 * @see http://bugs.typo3.org/view.php?id=11219
	 */
	protected $rebuildCHash;

	/************************************
	 *
	 * Translate parameters to a Speaking URL (t3lib_tstemplate::linkData)
	 *
	 ************************************/

	/**
	 * Creates an instance of this class
	 *
	 * @return	void
	 */
	public function __construct() {
		if (!t3lib_extMgm::isLoaded('dbal') && strpos(get_resource_type($GLOBALS['TYPO3_DB']->link), 'mysql link') !== false) {
			$res = $GLOBALS['TYPO3_DB']->sql_query('SELECT @@VERSION');
			$rec = $GLOBALS['TYPO3_DB']->sql_fetch_row($res);
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			$this->useMySQLExtendedSyntax = version_compare($rec[0], '4.1.0', '>');
		}
		$sysconf = (array)unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
		$this->enableStrictMode = (boolean)$sysconf['enableStrictMode'];
		$this->enableChashUrlDebug = (boolean)$sysconf['enableChashUrlDebug'];

		$this->initDevLog($sysconf);
	}

	/**
	 * Initializes devLog support
	 *
	 * @param array $sysconf
	 * @return void
	 */
	protected function initDevLog(array $sysconf) {
		$this->enableDevLog = (boolean)$sysconf['enableDevLog'];
		if ($this->enableDevLog) {
			$this->devLogId = (isset($_SERVER['UNIQUE_ID']) ? $_SERVER['UNIQUE_ID'] : uniqid(''));
		}
	}

	/**
	 * Translates a URL with query string (GET parameters) into Speaking URL.
	 * Called from t3lib_tstemplate::linkData
	 *
	 * @param	array		Array of parameters from t3lib_tstemplate::linkData - the function creating all links inside TYPO3
	 * @return	void
	 */
	public function encodeSpURL(&$params) {
		$this->devLog('Entering encodeSpURL for ' . $params['LD']['totalURL']);

		if ($this->isInWorkspace()) {
			$this->devLog('Workspace detected. Not doing anything!');
			return;
		}

		if (!$params['TCEmainHook']) {
			// Return directly, if simulateStaticDocuments is set:
			if ($GLOBALS['TSFE']->config['config']['simulateStaticDocuments']) {
				$GLOBALS['TT']->setTSlogMessage('SimulateStaticDocuments is enabled. RealURL disables itself.', 2);
				return;
			}

			// Return directly, if realurl is not enabled:
			if (!$GLOBALS['TSFE']->config['config']['tx_realurl_enable']) {
				$GLOBALS['TT']->setTSlogMessage('RealURL is not enabled in TS setup. Finished.');
				return;
			}
		}

		// Checking prefix:
		$prefix = $GLOBALS['TSFE']->absRefPrefix . $this->prefixEnablingSpURL;
		if (substr($params['LD']['totalURL'], 0, strlen($prefix)) != $prefix) {
			return;
		}

		$this->devLog('Starting URL encode');

		// Initializing config / request URL:
		$this->setConfig();
		$adjustedConfiguration = $this->adjustConfigurationByHost('encode', $params);
		$this->adjustRootPageId();
		$internalExtras = array();

		// Init "Admin Jump"; If frontend edit was enabled by the current URL of the page, set it again in the generated URL (and disable caching!)
		if (!$params['TCEmainHook']) {
			if ($GLOBALS['TSFE']->applicationData['tx_realurl']['adminJumpActive']) {
				$GLOBALS['TSFE']->set_no_cache();
				$this->adminJumpSet = TRUE;
				$internalExtras['adminJump'] = 1;
			}

			// If there is a frontend user logged in, set fe_user_prefix
			if (is_array($GLOBALS['TSFE']->fe_user->user)) {
				$this->fe_user_prefix_set = TRUE;
				$internalExtras['feLogin'] = 1;
			}
		}

		// Parse current URL into main parts:
		$uParts = parse_url($params['LD']['totalURL']);

		// Look in memory cache first
		$urlData = $this->hostConfigured . ' | ' . $uParts['query'];
		$newUrl = $this->encodeSpURL_encodeCache($urlData, $internalExtras);
		if (!$newUrl) {
			// Encode URL
			$newUrl = $this->encodeSpURL_doEncode($uParts['query'], $this->extConf['init']['enableCHashCache'], $params['LD']['totalURL']);

			// Set new URL in cache
			$this->encodeSpURL_encodeCache($urlData, $internalExtras, $newUrl);
		}
		unset($urlData);

		// Adding any anchor there might be:
		if ($uParts['fragment']) {
			$newUrl .= '#' . $uParts['fragment'];
		}

		// Reapply config.absRefPrefix if necessary
		if ((!isset($this->extConf['init']['reapplyAbsRefPrefix']) || $this->extConf['init']['reapplyAbsRefPrefix']) && $GLOBALS['TSFE']->absRefPrefix) {
			// Prevent // in case of absRefPrefix ending with / and emptyUrlReturnValue=/
			if (substr($GLOBALS['TSFE']->absRefPrefix, -1, 1) == '/' && substr($newUrl, 0, 1) == '/') {
				$newUrl = substr($newUrl, 1);
			}
			$newUrl = $GLOBALS['TSFE']->absRefPrefix . $newUrl;
		}

		// Set prepending of URL (e.g. hostname) which will be processed by typoLink_PostProc hook in tslib_content:
		if (isset($adjustedConfiguration['urlPrepend']) && !isset($this->urlPrepend[$newUrl])) {
			$urlPrepend = $adjustedConfiguration['urlPrepend'];
			if (substr($urlPrepend, -1) == '/') {
				$urlPrepend = substr($urlPrepend, 0, -1);
			}
			$this->urlPrepend[$newUrl] = $urlPrepend;
		}

		// Call hooks
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['encodeSpURL_postProc'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['encodeSpURL_postProc'] as $userFunc) {
				$hookParams = array(
					'pObj' => &$this,
					'params' => $params,
					'URL' => &$newUrl,
				);
				t3lib_div::callUserFunction($userFunc, $hookParams, $this);
			}
		}

		// Setting the encoded URL in the LD key of the params array - that value is passed by reference and thus returned to the linkData function!
		$params['LD']['totalURL'] = $newUrl;
	}

	/**
	 * Prepends URL generated by RealURL by something (e.g. a host).
	 * This method gets called by the typoLink_PostProc hook in tslib_content:
	 *
	 * @param	array		$parameters: Array of parameters from typoLink_PostProc hook in tslib_content
	 * @param	object		$cObj: Reference to the calling tslib_content instance
	 * @return	void
	 */
	public function encodeSpURL_urlPrepend(&$parameters, &$pObj) {
		if (isset($parameters['finalTagParts']['url']) && isset($this->urlPrepend[$parameters['finalTagParts']['url']])) {
			$urlKey = $url = $parameters['finalTagParts']['url'];

			// Remove absRefPrefix if necessary
			$absRefPrefixLength = strlen($GLOBALS['TSFE']->absRefPrefix);
			if ($absRefPrefixLength != 0 && substr($url, 0, $absRefPrefixLength) == $GLOBALS['TSFE']->absRefPrefix) {
				$url = substr($url, $absRefPrefixLength);
			}

			$url = $this->urlPrepend[$urlKey] . ($url{0} != '/' ? '/' : '') . $url;

			unset($this->urlPrepend[$parameters['finalTagParts']['url']]);

			// Adjust the URL:
			$parameters['finalTag'] = str_replace(
				'"' . $parameters['finalTagParts']['url'] . '"',
				'"' . $url . '"',
				$parameters['finalTag']
			);
			$parameters['finalTagParts']['url'] = $url;
			$pObj->lastTypoLinkUrl = $url;
		}
	}

	/**
	 * Transforms a query string into a speaking URL according to the configuration in ->extConf
	 *
	 * @param	string		Input query string
	 * @param	boolean		If set, the cHashCache table is used for "&cHash"
	 * @param	string		Original URL
	 * @return	string		Output Speaking URL (with as many GET parameters encoded into the URL as possible).
	 * @see encodeSpURL()
	 */
	protected function encodeSpURL_doEncode($inputQuery, $cHashCache = FALSE, $origUrl = '') {

		$this->cHashParameters = array();
		$this->rebuildCHash = false;

		// Extract all GET parameters into an ARRAY:
		$paramKeyValues = array();
		$additionalVariables = array();
		$GETparams = explode('&', $inputQuery);
		foreach ($GETparams as $paramAndValue) {
			list($p, $v) = explode('=', $paramAndValue, 2);
			$p = rawurldecode($p);
			if ($p != '') {
				$paramKeyValues[$p] = rawurldecode($v);
			}
		}
		$this->orig_paramKeyValues = $paramKeyValues;

		// Init array in which to collect the "directories" of the URL:
		$pathParts = array();

		// Pre-vars:
		$this->encodeSpURL_setSequence($this->extConf['preVars'], $paramKeyValues, $pathParts);

		// Create path from ID value:
		$page_id = $this->encodePageId = $paramKeyValues['id'];
		$this->encodeError = FALSE;
		if ($this->ignoreGETvar) {
			$paramKeyValues = array_merge($paramKeyValues, $additionalVariables);
		}
		$this->encodeSpURL_pathFromId($paramKeyValues, $pathParts);
		if ($this->ignoreGETvar) {
			$paramKeyValues = array_diff($paramKeyValues, $additionalVariables);
		}
		if ($this->encodeError) {
			return $origUrl;
		}

		// Fixed Post-vars:
		$fixedPostVarSetCfg = $this->getPostVarSetConfig($page_id, 'fixedPostVars');
		if (is_array($fixedPostVarSetCfg)) {
			$this->encodeSpURL_setSequence($fixedPostVarSetCfg, $paramKeyValues, $pathParts);
		}

		// Post var sets:
		$postVarSetCfg = $this->getPostVarSetConfig($page_id);
		$this->encodeSpURL_gettingPostVarSets($paramKeyValues, $pathParts, $postVarSetCfg);

		// Compile Speaking URL path
		$pathParts = $this->cleanUpPathParts($pathParts);

		// Add filename, if any:
		$newUrl = $this->createURLWithFileName($paramKeyValues, $pathParts);

		// Fix empty URLs
		$newUrl = $this->fixEmptyUrl($newUrl);

		// Clear ignored var
		if (isset($paramKeyValues[$this->ignoreGETvar])) {
			unset($paramKeyValues[$this->ignoreGETvar]);
		}

		// Store cHash cache:
		if ($cHashCache) {
			$this->encodeSpURL_cHashCache($newUrl, $paramKeyValues);
		}

		// Manage remaining GET parameters:
		if (count($paramKeyValues)) {
			$q = array();
			foreach ($paramKeyValues as $k => $v) {
				$q[] = $this->rawurlencodeParam($k) . '=' . rawurlencode($v);
			}
			$newUrl .= '?' . implode('&', $q);
		}

		// Memory clean up
		unset($this->cHashParameters);

		// Return new, Speaking URL encoded URL:
		return $newUrl;
	}

	/**
	 * Creating the TYPO3 Page path into $pathParts from the "id" value in $paramKeyValues
	 *
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts: Here we take out "id" GET var.
	 * @param	array		Numerical array of path-parts, continously being filled. Here, the "page path" is being added by which-ever method is preferred. Passed by reference.
	 * @return	void		Unsetting "id" from $paramKeyValues / Setting page path in $pathParts
	 * @see encodeSpURL_doEncode()
	 */
	protected function encodeSpURL_pathFromId(&$paramKeyValues, &$pathParts) {

		// Return immediately if no GET vars remain to be translated:
		if (!count($paramKeyValues)) {
			return;
		}

		// Creating page path:
		switch ((string)$this->extConf['pagePath']['type']) {
			case 'user':
				$params = array('paramKeyValues' => &$paramKeyValues, 'pathParts' => &$pathParts, 'pObj' => &$this, 'conf' => $this->extConf['pagePath'], 'mode' => 'encode');
				t3lib_div::callUserFunction($this->extConf['pagePath']['userFunc'], $params, $this);
				break;
			default: // Default: Just passing through the ID/alias of the page:
				$pathParts[] = rawurlencode($paramKeyValues['id']);
				unset($paramKeyValues['id']);
				break;
		}
	}

	/**
	 * Traversing setup for variables AFTER the page path.
	 *
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts, continously shortend. Passed by reference.
	 * @param	array		Numerical array of path-parts, continously being filled. Passed by reference.
	 * @param	array		$postVarSetCfg config
	 * @return	void		Removing values from $paramKeyValues / Setting values in $pathParts
	 * @see encodeSpURL_doEncode(), decodeSpURL_settingPostVarSets()
	 */
	protected function encodeSpURL_gettingPostVarSets(&$paramKeyValues, &$pathParts, $postVarSetCfg) {

		// Traverse setup for postVarSets. If any of those matches
		if (is_array($postVarSetCfg)) {
			foreach ($postVarSetCfg as $keyWord => $cfg) {
				switch ((string)$cfg['type']) {
					case 'admin':
						if ($this->adminJumpSet) {
							$pathParts[] = rawurlencode($keyWord);
							$this->adminJumpSet = FALSE; // ... this makes sure that any subsequent "admin-jump" activation is set...
						}
						break;
					case 'single':
						$this->encodeSpURL_setSingle($keyWord, $cfg['keyValues'], $paramKeyValues, $pathParts);
						break;
					default:
						unset($cfg['type']); // Just to make sure it is NOT set.
						foreach ($cfg as $Gcfg) {
							if (isset($paramKeyValues[$Gcfg['GETvar']])) {
								$pathParts[] = rawurlencode($keyWord);
								$pathPartsSize = count($pathParts);
								$this->encodeSpURL_setSequence($cfg, $paramKeyValues, $pathParts);
								// If (1) nothing was added or (2) only empty segments added, remove this part completely
								if (count($pathParts) == $pathPartsSize) {
									array_pop($pathParts);
								}
								else {
									$dropSegment = true;
									for ($i = $pathPartsSize; $i < count($pathParts); $i++) {
										if ($pathParts[$i] != '') {
											$dropSegment = false;
											break;
										}
									}
									if ($dropSegment) {
										$pathParts = array_slice($pathParts, 0, $pathPartsSize - 1);
									}
								}
								break;
							}
						}
						break;
				}
			}
		}
	}

	/**
	 * Setting a filename if any filename is configured to match remaining variables.
	 *
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts, continously shortend. Passed by reference.
	 * @return	string		Returns the filename to prepend, if any
	 * @see encodeSpURL_doEncode(), decodeSpURL_fileName()
	 */
	protected function encodeSpURL_fileName(array &$paramKeyValues) {

		// Look if any filename matches the remaining variables:
		if (is_array($this->extConf['fileName']['index'])) {
			foreach ($this->extConf['fileName']['index'] as $keyWord => $cfg) {
				$pathParts = array();
				if ($this->encodeSpURL_setSingle($keyWord, $cfg['keyValues'], $paramKeyValues, $pathParts)) {
					return $keyWord != '_DEFAULT' ? $keyWord : '';
				}
			}
		}
		return '';
	}

	/**
	 * Traverses a set of GETvars configured (array of segments)
	 *
	 * @param	array		Array of segment-configurations.
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts, continously shortend. Passed by reference.
	 * @param	array		Numerical array of path-parts, continously being filled. Passed by reference.
	 * @return	void		Removing values from $paramKeyValues / Setting values in $pathParts
	 * @see encodeSpURL_doEncode(), encodeSpURL_gettingPostVarSets(), decodeSpURL_getSequence()
	 */
	protected function encodeSpURL_setSequence($varSetCfg, &$paramKeyValues, &$pathParts) {

		// Traverse array of segments configuration
		$prevVal = '';
		if (is_array($varSetCfg)) {
			foreach ($varSetCfg as $setup) {
				switch ($setup['type']) {
					case 'action':
						$pathPartVal = '';

						// Look for admin jump:
						if ($this->adminJumpSet) {
							foreach ($setup['index'] as $pKey => $pCfg) {
								if ((string)$pCfg['type'] == 'admin') {
									$pathPartVal = $pKey;
									$this->adminJumpSet = FALSE;
									break;
								}
							}
						}

						// Look for frontend user login:
						if ($this->fe_user_prefix_set) {
							foreach ($setup['index'] as $pKey => $pCfg) {
								if ((string)$pCfg['type'] == 'feLogin') {
									$pathPartVal = $pKey;
									$this->fe_user_prefix_set = FALSE;
									break;
								}
							}
						}

						// If either pathPartVal has been set OR if _DEFAULT type is not bypass, set a value:
						if (strlen($pathPartVal) || $setup['index']['_DEFAULT']['type'] != 'bypass') {

							// If admin jump did not set $pathPartVal, look for first pass-through (no "type" set):
							if (!strlen($pathPartVal)) {
								foreach ($setup['index'] as $pKey => $pCfg) {
									if (!strlen($pCfg['type'])) {
										$pathPartVal = $pKey;
										break;
									}
								}
							}

							// Setting part of path:
							$pathParts[] = rawurlencode(strlen($pathPartVal) ? $pathPartVal : $this->NA);
						}
						break;
					default:
						if (!is_array($setup['cond']) || $this->checkCondition($setup['cond'], $prevVal)) {

							// Looking if the GET var is found in parameter index
							$GETvar = $setup['GETvar'];
							if ($GETvar == $this->ignoreGETvar) {
								// Do not do anything with this var!
								continue;
							}
							$parameterSet = isset($paramKeyValues[$GETvar]);
							$GETvarVal = $parameterSet ? $paramKeyValues[$GETvar] : '';

							// Set reverse map:
							$revMap = is_array($setup['valueMap']) ? array_flip($setup['valueMap']) : array();

							if (isset($revMap[$GETvarVal])) {
								$prevVal = $GETvarVal;
								$pathParts[] = rawurlencode($revMap[$GETvarVal]);
								$this->cHashParameters[$GETvar] = $GETvarVal;
							} elseif ($setup['noMatch'] == 'bypass') {
								// If no match in reverse value map and "bypass" is set, remove the parameter from the URL
								// Must rebuild cHash because we remove a parameter!
								$this->rebuildCHash |= $parameterSet;
							} elseif ($setup['noMatch'] == 'null') {
								// If no match and "null" is set, then set "dummy" value
								// Set "dummy" value (?)
								$prevVal = '';
								$pathParts[] = '';
								$this->rebuildCHash |= $parameterSet;
							} elseif ($setup['userFunc']) {
								$params = array(
									'pObj' => &$this,
									'value' => $GETvarVal,
									'decodeAlias' => false,
									'pathParts' => &$pathParts
								);
								$prevVal = $GETvarVal;
								$GETvarVal = t3lib_div::callUserFunction($setup['userFunc'], $params, $this);
								$pathParts[] = rawurlencode($GETvarVal);
								$this->cHashParameters[$GETvar] = $prevVal;
							} elseif (is_array($setup['lookUpTable'])) {
								$prevVal = $GETvarVal;
								$GETvarVal = $this->lookUpTranslation($setup['lookUpTable'], $GETvarVal);
								$pathParts[] = rawurlencode($GETvarVal);
								$this->cHashParameters[$GETvar] = $prevVal;
							} elseif (isset($setup['valueDefault'])) {
								$prevVal = $setup['valueDefault'];
								$pathParts[] = rawurlencode($setup['valueDefault']);
								$this->cHashParameters[$GETvar] = $setup['valueDefault'];
								$this->rebuildCHash |= !$parameterSet;
							} else {
								$prevVal = $GETvarVal;
								$pathParts[] = rawurlencode($GETvarVal);
								$this->cHashParameters[$GETvar] = $prevVal;
								$this->rebuildCHash |= !$parameterSet;
							}

							// Finally, unset GET var so it doesn't get processed once more:
							unset($paramKeyValues[$setup['GETvar']]);
						}
						break;
				}
			}
		}
	}

	/**
	 * Traversing an array of GETvar => value pairs and checking if both variable names AND values are matching any found in $paramKeyValues; If so, the keyword representing those values is set and the GEtvars are unset from $paramkeyValues array
	 *
	 * @param	string		Keyword to set as a representation of the GETvars configured.
	 * @param	array		Array of GETvar => values which content in $paramKeyvalues must match exactly in order to be substituted with the keyword, $keyWord
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts, continously shortend. Passed by reference.
	 * @param	array		Numerical array of path-parts, continously being filled. Passed by reference.
	 * @return	boolean		Return true, if any value from $paramKeyValues was removed.
	 * @see encodeSpURL_fileName(), encodeSpURL_gettingPostVarSets(), decodeSpURL_getSingle()
	 */
	protected function encodeSpURL_setSingle($keyWord, $keyValues, &$paramKeyValues, &$pathParts) {
		if (is_array($keyValues)) {
			$allSet = TRUE;

			// Check if all GETvars configured are found in $paramKeyValues:
			foreach ($keyValues as $getVar => $value) {
				if (!isset($paramKeyValues[$getVar]) || strcmp($paramKeyValues[$getVar], $value)) {
					$allSet = FALSE;
					break;
				}
			}

			// If all is set, unset the GETvars and set the value.
			if ($allSet) {
				$pathParts[] = rawurlencode($keyWord);
				foreach ($keyValues as $getVar => $value) {
					$this->cHashParameters[$getVar] = $value;
					unset($paramKeyValues[$getVar]);
				}

				return TRUE;
			}
		}
		return FALSE;
	}

	/**
	 * Setting / Getting encoded URL to/from cache (memory cache, but could be extended to database cache)
	 *
	 * @param	string		Host + the original URL with GET parameters - identifying the cached version to find
	 * @param	array		Array with extra data to include in encoding. This is flags if adminJump url or feLogin flags are set since these are NOT a part of the URL to encode and therefore are needed for the hash to be true.
	 * @param	string		If set, this URL will be cached as the encoded version of $urlToEncode. Otherwise the function will look for and return the cached version of $urlToEncode
	 * @return	mixed		If $setEncodedURL is true, this will be STORED as the cached version and the function returns false, otherwise the cached version is returned (string).
	 * @see encodeSpURL()
	 */
	protected function encodeSpURL_encodeCache($urlData, $internalExtras, $setEncodedURL = '') {

		// Create hash string:
		$hash = md5($urlData . '///' . serialize($internalExtras));

		if (!$setEncodedURL) { // Asking for cached encoded URL:

			// First, check memory, otherwise ask database:
			if (!isset($GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE'][$hash]) && $this->extConf['init']['enableUrlEncodeCache']) {
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('content', 'tx_realurl_urlencodecache',
								'url_hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($hash, 'tx_realurl_urlencodecache') .
								' AND tstamp>' . strtotime('midnight', time() - 24 * 3600 * $this->encodeCacheTTL));
				if (false != ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
					$GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE'][$hash] = $row['content'];
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
			}
			return $GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE'][$hash];
		}
		else { // Setting encoded URL in cache:
			// No caching if FE editing is enabled!
			if (!$this->isBEUserLoggedIn()) {
				$GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE'][$hash] = $setEncodedURL;

				// If the page id is NOT an integer, it's an alias we have to look up:
				if (!t3lib_div::testInt($this->encodePageId)) {
					$this->encodePageId = $this->pageAliasToID($this->encodePageId);
				}

				if ($this->extConf['init']['enableUrlEncodeCache'] && $this->canCachePageURL($this->encodePageId)) {
					$insertFields = array(
							'url_hash' => $hash,
							'origparams' => $urlData,
							'internalExtras' => count($internalExtras) ? serialize($internalExtras) : '',
							'content' => $setEncodedURL,
							'page_id' => $this->encodePageId,
							'tstamp' => time()
						);
					if ($this->useMySQLExtendedSyntax) {
						$query = $GLOBALS['TYPO3_DB']->INSERTquery('tx_realurl_urlencodecache', $insertFields);
						$query .= ' ON DUPLICATE KEY UPDATE tstamp=' . $insertFields['tstamp'];
						$GLOBALS['TYPO3_DB']->sql_query($query);
					} else {
						$GLOBALS['TYPO3_DB']->sql_query('START TRANSACTION');
						$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_urlencodecache', 'url_hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($hash, 'tx_realurl_urlencodecache'));
						$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_urlencodecache', $insertFields);
						$GLOBALS['TYPO3_DB']->sql_query('COMMIT');
					}
				}
			}
		}
		return '';
	}

	/**
	 * Will store a record in a cachetable holding the value of the "cHash" parameter in a link, if any.
	 * Background:
	 * The "cHash" parameter is a hash over the values in the Query String of a URL and it "authenticates" the URL to the frontend so we can safely cache page content with that parameter combination.
	 * Technically, there is no problem with the "cHash" parameter - it is like any other parameter something we could encode with Speaking URLs. The problem is: a cHash string is not "speaking" (and never will be!)
	 * So; the only option we are left with if we want to remove the "?cHash=...:" remains in URLs and at the same time do not want to include it in the virtual path is; store it in the database!
	 * This is what this function does: Stores a record in the database which relates the cHash value to a hash id of the URL. This is done ONLY if the "cHash" parameter is the only one left which would make the URL non-speaking. Otherwise it is left behind.
	 * Obviously, this whole thing only works if there is a function in the decode part which will look up the cHash again and include it in the GET parameters resolved from the Speaking URL - but there is of course...
	 *
	 * @param	string		Speaking URL path (being hashed to an integer and cHash value related to this.)
	 * @param	array		Params array, passed by reference. If "cHash" is the only value left it will be put in the cache table and the value is unset in the array.
	 * @return	void
	 * @see decodeSpURL_cHashCache()
	 */
	protected function encodeSpURL_cHashCache($newUrl, &$paramKeyValues) {

		// If "cHash" is the ONLY parameter left...
		// (if there are others our problem is that the cHash probably covers those
		// as well and if we include the cHash anyways we might get duplicates for
		// the same speaking URL in the cache table!)
		if (isset($paramKeyValues['cHash'])) {

			if ($this->rebuildCHash) {
				$cHashParameters = array_merge($this->cHashParameters, $paramKeyValues);
				unset($cHashParameters['cHash']);
				$cHashParameters = t3lib_div::cHashParams(t3lib_div::implodeArrayForUrl('', $cHashParameters));
				if (method_exists('t3lib_div', 'calculateCHash')) {
					$paramKeyValues['cHash'] = t3lib_div::calculateCHash($cHashParameters);
				}
				else {
					$paramKeyValues['cHash'] = t3lib_div::shortMD5(serialize($cHashParameters));
				}
				unset($cHashParameters);
			}

			if (count($paramKeyValues) == 1) {

				$spUrlHash = md5($newUrl);
				$spUrlHashQuoted = $GLOBALS['TYPO3_DB']->fullQuoteStr($spUrlHash, 'tx_realurl_chashcache');

				// first, look if a cHash is already there for this SpURL
				list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('chash_string',
					'tx_realurl_chashcache', 'spurl_hash=' . $spUrlHashQuoted);

				if (!is_array($row)) {
					// Nothing found, insert to the cache
					$data = array(
						'spurl_hash' => $spUrlHash,
						'spurl_string' => $this->enableChashUrlDebug ? $newUrl : null,
						'chash_string' => $paramKeyValues['cHash']
					);
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_chashcache', $data);
				}
				else {
					// If one found, check if it is different, and if so update:
					if ($row['chash_string'] != $paramKeyValues['cHash']) {
						// If that chash_string is different from the one we want to
						// insert, that might be a bug or mean that encryptionKey was
						// changed so cHash values will be different now
						// In any case we will just silently update the value:
						$data = array(
							'chash_string' => $paramKeyValues['cHash']
						);
						$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_chashcache',
							'spurl_hash=' . $spUrlHashQuoted, $data);
					}
				}

				// Unset "cHash" (and array should now be empty!)
				unset($paramKeyValues['cHash']);
			}
		}
	}

	/************************************
	 *
	 * Translate a Speaking URL to parameters (tslib_fe)
	 *
	 ************************************/

	/**
	 * Parse speaking URL and translate it to parameters understood by TYPO3
	 * Function is called from tslib_fe
	 * The overall format of a speaking URL is these five parts [TYPO3_SITE_URL] / [pre-var] / [page-identification] / [post-vars] / [file.ext]
	 * - "TYPO3_SITE_URL" is fixed value from the environment,
	 * - "pre-var" is any number of segments separated by "/" mapping to GETvars AND with a known lenght,
	 * - "page-identification" identifies the page id in TYPO3 possibly with multiple segments separated by "/" BUT with an UNKNOWN length,
	 * - "post-vars" is sets of segments offering the same features as "pre-var"
	 * - "file.ext" is any filename that might apply
	 *
	 * @param	array		Params for hook
	 * @return	void		Setting internal variables.
	 */
	public function decodeSpURL($params) {

		$this->devLog('Entering decodeSpURL');

		// Setting parent object reference (which is $GLOBALS['TSFE'])
		$this->pObj = &$params['pObj'];

		// Initializing config / request URL:
		$this->setConfig();
		$this->adjustConfigurationByHost('decode');
		$this->adjustRootPageId();

		// If there has been a redirect (basically; we arrived here otherwise than via "index.php" in the URL) this can happend either due to a CGI-script or because of reWrite rule. Earlier we used $GLOBALS['HTTP_SERVER_VARS']['REDIRECT_URL'] to check but...
		if ($this->pObj->siteScript && substr($this->pObj->siteScript, 0, 9) != 'index.php' && substr($this->pObj->siteScript, 0, 1) != '?') {

			// Getting the path which is above the current site url:
			// For instance "first/second/third/index.html?&param1=value1&param2=value2"
			// should be the result of the URL
			// "http://localhost/typo3/dev/dummy_1/first/second/third/index.html?&param1=value1&param2=value2"
			// Note: sometimes in fcgi installations it is absolute, so we have to make it
			// relative to work properly.
			$speakingURIpath = $this->pObj->siteScript{0} == '/' ? substr($this->pObj->siteScript, 1) : $this->pObj->siteScript;

			// Call hooks
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'] as $userFunc) {
					$hookParams = array(
						'pObj' => &$this,
						'params' => $params,
						'URL' => &$speakingURIpath,
					);
					t3lib_div::callUserFunction($userFunc, $hookParams, $this);
				}
			}

			// Append missing slash if configured for:
			if ($this->extConf['init']['appendMissingSlash']) {
				$regexp = '~^([^\?]*[^/])(\?.*)?$~';
				if (substr($speakingURIpath, -1, 1) == '?') {
					$speakingURIpath = substr($speakingURIpath, 0, -1);
				}
				if (preg_match($regexp, $speakingURIpath)) { // Only process if a slash is missing:
					$options = t3lib_div::trimExplode(',', $this->extConf['init']['appendMissingSlash'], true);
					if (in_array('ifNotFile', $options)) {
						if (!preg_match('/\/[^\/]+\.[^\/]+(\?.*)?$/', '/' . $speakingURIpath)) {
							$speakingURIpath = preg_replace($regexp, '\1/\2', $speakingURIpath);
							$this->appendedSlash = true;
						}
					}
					else {
						$speakingURIpath = preg_replace($regexp, '\1/\2', $speakingURIpath);
						$this->appendedSlash = true;
					}
					if ($this->appendedSlash && count($options) > 0) {
						foreach ($options as $option) {
							$matches = array();
							if (preg_match('/^redirect(\[(30[1237])\])?$/', $option, $matches)) {
								$code = count($matches) > 1 ? $matches[2] : 301;
								$status = 'HTTP/1.0 ' . $code . ' TYPO3 RealURL redirect';

								// Check path segment to be relative for the current site.
								// parse_url() does not work with relative URLs, so we use it to test
								if (!@parse_url($speakingURIpath, PHP_URL_HOST)) {
									@ob_end_clean();
									header($status);
									header('Location: ' . t3lib_div::locationHeaderUrl($speakingURIpath));
									exit;
								}
							}
						}
					}
				}
			}

			// If the URL is a single script like "123.1.html" it might be an "old" simulateStaticDocument request. If this is the case and support for this is configured, do NOT try and resolve it as a Speaking URL
			$fI = t3lib_div::split_fileref($speakingURIpath);
			if (!t3lib_div::testInt($this->pObj->id) && $fI['path'] == '' && $this->extConf['fileName']['defaultToHTMLsuffixOnPrev'] && $this->extConf['init']['respectSimulateStaticURLs']) {
				// If page ID does not exist yet and page is on the root level and both
				// respectSimulateStaticURLs and defaultToHTMLsuffixOnPrev are set, than
				// ignore respectSimulateStaticURLs and attempt to resolve page id.
				// See http://bugs.typo3.org/view.php?id=1530
				$GLOBALS['TT']->setTSlogMessage('decodeSpURL: ignoring respectSimulateStaticURLs due defaultToHTMLsuffixOnPrev for the root level page!)', 2);
				$this->extConf['init']['respectSimulateStaticURLs'] = false;
			}
			if (!$this->extConf['init']['respectSimulateStaticURLs'] || $fI['path']) {
				$this->devLog('RealURL powered decoding (TM) starting!');

				// Parse path:
				$uParts = @parse_url($speakingURIpath);
				if (!is_array($uParts)) {
					$this->decodeSpURL_throw404('Current URL is invalid');
				}
				$speakingURIpath = $this->speakingURIpath_procValue = $uParts['path'];

				// Redirecting if needed (exits if so).
				$this->decodeSpURL_checkRedirects($speakingURIpath);

				// Looking for cached information:
				$cachedInfo = $this->decodeSpURL_decodeCache($speakingURIpath);

				// If no cached info was found, create it:
				if (!is_array($cachedInfo)) {
					// Decode URL:
					$cachedInfo = $this->decodeSpURL_doDecode($speakingURIpath, $this->extConf['init']['enableCHashCache']);

					// Storing cached information:
					$this->decodeSpURL_decodeCache($speakingURIpath, $cachedInfo);
				}

				// Re-create QUERY_STRING from Get vars for use with typoLink()
				$_SERVER['QUERY_STRING'] = $this->decodeSpURL_createQueryString($cachedInfo['GET_VARS']);

				// Jump-admin if configured:
				$this->decodeSpURL_jumpAdmin_goBackend($cachedInfo['id']);

				// Setting info in TSFE:
				$this->pObj->mergingWithGetVars($cachedInfo['GET_VARS']);
				$this->pObj->id = $cachedInfo['id'];

				if ($this->mimeType) {
					header('Content-type: ' . $this->mimeType);
					$this->mimeType = null;
				}
			}
		}
	}

	/**
	 * Look for redirect configuration.
	 * If the input path is found as key in $this->extConf['redirects'] this method redirects to the URL found as value
	 *
	 * @param	string		Path from SpeakingURL.
	 * @return	void
	 * @see decodeSpURL_doDecode()
	 */
	protected function decodeSpURL_checkRedirects($speakingURIpath) {
		$speakingURIpath = trim($speakingURIpath);

		if (isset($this->extConf['redirects'][$speakingURIpath])) {
			$url = $this->extConf['redirects'][$speakingURIpath];
			if (preg_match('/^30[1237];/', $url)) {
				$redirectCode = intval(substr($url, 0, 3));
				$url = substr($url, 4);
				header('HTTP/1.0 ' . $redirectCode . ' Redirect');
			}
			header('Location: ' . t3lib_div::locationHeaderUrl($url));
			exit();
		}

		// Regex redirects:
		if (is_array($this->extConf['redirects_regex'])) {
			foreach ($this->extConf['redirects_regex'] as $regex => $substString) {
				if (preg_match('/' . $regex . '/', $speakingURIpath)) {
					$url = @preg_replace('/' . $regex . '/', $substString, $speakingURIpath);
					if ($url) {
						if (preg_match('/^30[1237];/', $url)) {
							$redirectCode = intval(substr($url, 0, 3));
							header('HTTP/1.0 ' . $redirectCode . ' Redirect');
							$url = substr($url, 4);
						}
						header('Location: ' . t3lib_div::locationHeaderUrl($url));
						exit();
					}
				}
			}
		}

		// DB defined redirects:
		$hash = t3lib_div::md5int($speakingURIpath);
		$url = $GLOBALS['TYPO3_DB']->fullQuoteStr($speakingURIpath, 'tx_realurl_redirects');
		list($redirect_row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
			'destination,has_moved', 'tx_realurl_redirects',
			'url_hash=' . $hash . ' AND url=' . $url);
		if (is_array($redirect_row)) {
			// Update statistics
			$fields_values = array(
				'counter' => 'counter+1',
				'tstamp' => time(),
				'last_referer' => t3lib_div::getIndpEnv('HTTP_REFERER')
			);
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_redirects',
				'url_hash=' . $hash . ' AND url=' . $url,
				$fields_values, array('counter'));

			// Redirect
			if ($redirect_row['has_moved']) {
				header('HTTP/1.1 301 Moved Permanently');
			}

			header('Location: ' . t3lib_div::locationHeaderUrl($redirect_row['destination']));
			exit();
		}
	}

	/**
	 * Decodes a speaking URL path into an array of GET parameters and a page id.
	 *
	 * @param	string		Speaking URL path (after the "root" path of the website!) but without query parameters
	 * @param	boolean		If cHash caching is enabled or not.
	 * @return	array		Array with id and GET parameters.
	 * @see decodeSpURL()
	 */
	protected function decodeSpURL_doDecode($speakingURIpath, $cHashCache = FALSE) {

		// Cached info:
		$cachedInfo = array();

		// Convert URL to segments
		$pathParts = explode('/', $speakingURIpath);
		array_walk($pathParts, create_function('&$value', '$value = rawurldecode($value);'));

		// Strip/process file name or extension first
		$file_GET_VARS = $this->decodeSpURL_decodeFileName($pathParts);

		// Setting original dir-parts:
		$this->dirParts = $pathParts;

		// Setting "preVars":
		$pre_GET_VARS = $this->decodeSpURL_settingPreVars($pathParts, $this->extConf['preVars']);

		// Setting page id:
		list($cachedInfo['id'], $id_GET_VARS, $cachedInfo['rootpage_id']) = $this->decodeSpURL_idFromPath($pathParts);

		// Fixed Post-vars:
		$fixedPostVarSetCfg = $this->getPostVarSetConfig($cachedInfo['id'], 'fixedPostVars');
		$fixedPost_GET_VARS = $this->decodeSpURL_settingPreVars($pathParts, $fixedPostVarSetCfg);

		// Setting "postVarSets":
		$postVarSetCfg = $this->getPostVarSetConfig($cachedInfo['id']);
		$post_GET_VARS = $this->decodeSpURL_settingPostVarSets($pathParts, $postVarSetCfg);

		// Looking for remaining parts:
		if (count($pathParts)) {
			$this->decodeSpURL_throw404('"' . $speakingURIpath . '" could not be found, closest page matching is ' . substr(implode('/', $this->dirParts), 0, -strlen(implode('/', $pathParts))) . '');
		}

		// Merge Get vars together:
		$cachedInfo['GET_VARS'] = array();
		if (is_array($pre_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $pre_GET_VARS);
		if (is_array($id_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $id_GET_VARS);
		if (is_array($fixedPost_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $fixedPost_GET_VARS);
		if (is_array($post_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $post_GET_VARS);
		if (is_array($file_GET_VARS))
			$cachedInfo['GET_VARS'] = t3lib_div::array_merge_recursive_overrule($cachedInfo['GET_VARS'], $file_GET_VARS);

		// cHash handling:
		if ($cHashCache) {
			$cHash_value = $this->decodeSpURL_cHashCache($speakingURIpath);
			if ($cHash_value) {
				$cachedInfo['GET_VARS']['cHash'] = $cHash_value;
			}
		}

		// Return information found:
		return $cachedInfo;
	}

	/**
	 * Generates a parameter string from an array recursively
	 *
	 * @param	array		Array to generate strings from
	 * @param	string		path to prepend to every parameter
	 * @return	array		Array with parameter strings
	 */
	protected function decodeSpURL_createQueryStringParam($paramArr, $prependString = '') {
		if (!is_array($paramArr)) {
			return array($prependString . '=' . $paramArr);
		}

		if (count($paramArr) == 0) {
			return array();
		}

		$paramList = array();
		foreach ($paramArr as $var => $value) {
			$paramList = array_merge($paramList, $this->decodeSpURL_createQueryStringParam($value, $prependString . '[' . $var . ']'));
		}

		return $paramList;
	}

	/**
	 * Re-creates QUERY_STRING for use with typoLink()
	 *
	 * @param	array		List of Get vars
	 * @return	string		QUERY_STRING value
	 */
	protected function decodeSpURL_createQueryString(&$getVars) {
		if (!is_array($getVars) || count($getVars) == 0) {
			return $_SERVER['QUERY_STRING'];
		}

		$parameters = array();
		foreach ($getVars as $var => $value) {
			$parameters = array_merge($parameters, $this->decodeSpURL_createQueryStringParam($value, $var));
		}

		// If cHash is provided in the query string, replace it in $getVars
		$cHash_override = t3lib_div::_GET('cHash');
		if ($cHash_override) {
			$getVars['cHash'] = $cHash_override;
		}

		$queryString = t3lib_div::getIndpEnv('QUERY_STRING');
		if ($queryString) {
			array_push($parameters, $queryString);
		}

		return implode('&', $parameters);
	}

	/**
	 * Extracts the page ID from URL.
	 *
	 * @param	array		Parts of path. NOTICE: Passed by reference.
	 * @return	array		array(Page ID, GETvars array if any eg. MP)
	 * @see decodeSpURL_doDecode()
	 */
	protected function decodeSpURL_idFromPath(&$pathParts) {
		// Creating page path:
		switch ((string)$this->extConf['pagePath']['type']) {
			case 'user':
				$params = array('pathParts' => &$pathParts, 'pObj' => &$this, 'conf' => $this->extConf['pagePath'], 'mode' => 'decode');

				$result = t3lib_div::callUserFunction($this->extConf['pagePath']['userFunc'], $params, $this);
				break;
			default: // Default: Just passing through the ID/alias of the page:
				$value = array_shift($pathParts);
				$result = array($value);
				break;
		}

		if (count($result) < 2) {
			$result[1] = null;
			$result[2] = $this->extConf['pagePath']['rootpage_id'];
		} else if (count($result) < 3) {
			$result[2] = $this->extConf['pagePath']['rootpage_id'];
		}
		return $result;
	}

	/**
	 * Analysing the path BEFORE the page identification part of the URL
	 *
	 * @param	array		The path splitted by "/". NOTICE: Passed by reference and shortend for each time a segment is matching configuration
	 * @param	array		Configuration
	 * @return	array		GET-vars resulting from the analysis
	 * @see decodeSpURL_doDecode()
	 */
	protected function decodeSpURL_settingPreVars(&$pathParts, $config) {
		if (is_array($config)) {

			// Pulling vars of the pathParts
			$GET_string = $this->decodeSpURL_getSequence($pathParts, $config);

			// If a get string is created, then:
			if ($GET_string) {
				$GET_VARS = false;
				parse_str($GET_string, $GET_VARS);
				return $GET_VARS;
			}
		}
		return null;
	}

	/**
	 * Analysing the path AFTER the page identification part of the URL
	 *
	 * @param	array		The path splitted by "/". NOTICE: Passed by reference and shortend for each time a segment is matching configuration
	 * @param	array		$postVarSetCfg config
	 * @return	array		GET-vars resulting from the analysis
	 * @see decodeSpURL_doDecode(), encodeSpURL_gettingPostVarSets()
	 */
	protected function decodeSpURL_settingPostVarSets(&$pathParts, $postVarSetCfg) {
		if (is_array($postVarSetCfg)) {
			$GET_string = '';

			// Getting first value, the key (and keep stripping of sets of segments until the end is reached!)
			while (false != ($key = array_shift($pathParts))) {
				$key = rawurldecode($key);
				if (is_array($postVarSetCfg[$key])) {
					switch ((string)$postVarSetCfg[$key]['type']) {
						case 'admin':
							$this->decodeSpURL_jumpAdmin();
							break;
						case 'single':
							$GET_string .= $this->decodeSpURL_getSingle($postVarSetCfg[$key]['keyValues']);
							break;
						default:
							unset($postVarSetCfg[$key]['type']); // Just to make sure it is not set!
							$GET_string .= $this->decodeSpURL_getSequence($pathParts, $postVarSetCfg[$key]);
							break;
					}
				} elseif ($this->extConf['init']['postVarSet_failureMode'] == 'redirect_goodUpperDir') {
					// Add the element just taken off. What is left now will be the post-parts that were not mapped to anything.
					array_unshift($pathParts, $key);

					$originalDirs = $this->dirParts;

					// Popping of pages of original dirs (as many as are remaining in $pathParts)
					while (count($pathParts)) {
						array_pop($pathParts);
						array_pop($originalDirs);
					}
					// If a file part was detected, add that:
					$this->appendFilePart($originalDirs);

					// Implode URL and redirect:
					$redirectUrl = implode('/', $originalDirs);
					header('HTTP/1.1 301 Moved Permanently');
					header('Location: ' . t3lib_div::locationHeaderUrl($redirectUrl));
					exit();
				} elseif ($this->extConf['init']['postVarSet_failureMode'] == 'ignore') {
					// Add the element just taken off. What is left now will be the post-parts that were not mapped to anything.
					array_unshift($pathParts, $key);
					break;
				} else {
					$this->decodeSpURL_throw404('Segment "' . $key . '" was not a keyword for a postVarSet as expected!');
				}
			}

			// If a get string is created, then:
			if ($GET_string) {
				$GET_VARS = false;
				parse_str($GET_string, $GET_VARS);
				$this->decodeSpURL_fixBrackets($GET_VARS);
				return $GET_VARS;
			}
		}
		return null;
	}

	/**
	 * Fixes a problem with parse_url that returns `a[b[c]` instead of `a[b[c]]` when parsing `a%5Bb%5Bc%5D%5D`
	 *
	 * @param	array		Input array
	 * @return	[type]		...
	 * @see decodeSpURL_settingPostVarSets()
	 */
	protected function decodeSpURL_fixBrackets(&$arr) {
		$bad_keys = array();
		foreach ($arr as $k => $v) {
			if (is_array($v)) {
				$this->decodeSpURL_fixBrackets($arr[$k]);
			} else {
				if (strchr($k, '[') && !strchr($k, ']')) {
					$bad_keys[] = $k;
				}
			}
		}
		if (count($bad_keys) > 0) {
			foreach ($bad_keys as $key) {
				$arr[$key . ']'] = $arr[$key];
				unset($arr[$key]);
			}
		}
	}

	/**
	 * Decodes the file name and adjusts file parts accordingly
	 *
	 * @param array $pathParts Path parts of the URLs (can be modified)
	 * @return array GET varaibles from the file name or empty array
	 */
	protected function decodeSpURL_decodeFileName(array &$pathParts) {
		$getVars = array();
		$fileName = rawurldecode(array_pop($pathParts));
		list($segment, $extension) = t3lib_div::revExplode('.', $fileName, 2);
		if ($extension) {
			$getVars = array();
			if (!$this->decodeSpURL_decodeFileName_lookupInIndex($fileName, $segment, $extension, $pathParts, $getVars)) {
				if (!$this->decodeSpURL_decodeFileName_checkHtmlSuffix($fileName, $segment, $extension, $pathParts)) {
					$this->decodeSpURL_throw404('File "' . $fileName . '" was not found (1)!');
				}
			}
		}
		return $getVars;
	}

	/**
	 * Checks if the suffix matches to the configured one.
	 *
	 * @param string $fileName
	 * @param string $segment
	 * @param string $extension
	 * @param array $pathPartsCopy
	 * @see tx_realurl::decodeSpURL_decodeFileName()
	 */
	protected function decodeSpURL_decodeFileName_checkHtmlSuffix($fileName, $segment, $extension, array &$pathParts) {
		$handled = false;
		if (isset($this->extConf['fileName']['defaultToHTMLsuffixOnPrev'])) {
			$suffix = $this->extConf['fileName']['defaultToHTMLsuffixOnPrev'];
			$suffix = (!$this->isString($suffix, 'defaultToHTMLsuffixOnPrev') ? '.html' : $suffix);
			if ($suffix == '.' . $extension) {
				$pathParts[] = rawurlencode($segment);
				$this->filePart = '.' . $extension;
			}
			else {
				$this->decodeSpURL_throw404('File "' . $fileName . '" was not found (2)!');
			}
			$handled = true;
		}
		return $handled;
	}

	/**
	 * Looks up the file name or the extension in the index.
	 *
	 * @param string $fileName
	 * @param string $segment
	 * @param string $extension
	 * @param array $pathPartsCopy Path parts (can be modified)
	 * @return array GET variables (can be enpty in case if there is a default file name)
	 * @see tx_realurl::decodeSpURL_decodeFileName()
	 */
	protected function decodeSpURL_decodeFileName_lookupInIndex($fileName, $segment, $extension, array &$pathPartsCopy, array &$getVars) {
		$handled = false;
		$keyValues = '';
		if (is_array($this->extConf['fileName']['index'])) {
			foreach ($this->extConf['fileName']['index'] as $key => $config) {
				// Note: strict comparison because the following is true in PHP: 0 == 'whatever'
				if ($key === $fileName) {
					$keyValues = $config['keyValues'];
					$this->filePart = $fileName;
					if (isset($config['mimetype'])) {
						$this->mimeType = $config['mimetype'];
					}
					$handled = true;
					break;
				}
				elseif ($key === '.' . $extension) {
					$keyValues = $config['keyValues'];
					$pathPartsCopy[] = urlencode($segment);
					$this->filePart = '.' . $extension;
					if (isset($config['mimetype'])) {
						$this->mimeType = $config['mimetype'];
					}
					$handled = true;
					break;
				}
			}
		}
		// Must decode key values if set
		if ($keyValues) {
			$getString = $this->decodeSpURL_getSingle($keyValues);
			parse_str($getString, $getVars);
		}
		return $handled;
	}

	/**
	 * Pulling variables of the path parts
	 *
	 * @param	array		Parts of path. NOTICE: Passed by reference.
	 * @param	array		Setup array for segments in set.
	 * @return	string		GET parameter string
	 * @see decodeSpURL_settingPreVars(), decodeSpURL_settingPostVarSets()
	 */
	protected function decodeSpURL_getSequence(&$pathParts, $setupArr) {
		$GET_string = '';
		$prevVal = '';
		foreach ($setupArr as $setup) {
			if (count($pathParts) == 0) {
				// If we are here, it means we are at the end of the URL.
				// Since some items still remain in the $setupArr, it means
				// we stripped empty segments at the end of the URL on encoding.
				// Reconstruct them or cHash check will fail in TSFE.
				// Related to bug #15906.
				$GET_string .= '&' . rawurlencode($setup['GETvar']) . '=';
			}
			else {
				// Get value and remove from path parts:
				$value = $origValue = array_shift($pathParts);
				$value = rawurldecode($value);

				switch ($setup['type']) {
					case 'action':
						// Find index key:
						$idx = isset($setup['index'][$value]) ? $value : '_DEFAULT';

						// Look up type:
						switch ((string)$setup['index'][$idx]['type']) {
							case 'redirect':
								$url = (string)$setup['index'][$idx]['url'];
								$url = str_replace('###INDEX###', rawurlencode($value), $url);
								$this->appendFilePart($pathParts);
								$remainPath = implode('/', $pathParts);
								if ($this->appendedSlash) {
									$remainPath = substr($remainPath, 0, -1);
								}
								$url = str_replace('###REMAIN_PATH###', rawurlencode(rawurldecode($remainPath)), $url);

								header('Location: ' . t3lib_div::locationHeaderUrl($url));
								exit();
								break;
							case 'admin':
								$this->decodeSpURL_jumpAdmin();
								break;
							case 'notfound':
								$this->decodeSpURL_throw404('A required value from "' . @implode(',', @array_keys($setup['match'])) . '" of path was not matching "' . $value . '" which was actually found.');
								break;
							case 'bypass':
								array_unshift($pathParts, $origValue);
								break;
							case 'feLogin':
								// Do nothing.
								break;
						}
						break;
					default:
						if (!is_array($setup['cond']) || $this->checkCondition($setup['cond'], $prevVal)) {

							// Map value if applicable:
							if (isset($setup['valueMap'][$value])) {
								$value = $setup['valueMap'][$value];
							} elseif ($setup['noMatch'] == 'bypass') {
								// If no match and "bypass" is set, then return the value to $pathParts and break
								array_unshift($pathParts, $origValue);
								break;
							} elseif ($setup['noMatch'] == 'null') { // If no match and "null" is set, then break (without setting any value!)
								break;
							} elseif ($setup['userFunc']) {
								$params = array(
									'decodeAlias' => true,
									'origValue' => $origValue,
									'pathParts' => &$pathParts,
									'pObj' => &$this,
									'value' => $value,
								);
								$value = t3lib_div::callUserFunction($setup['userFunc'], $params, $this);
							} elseif (is_array($setup['lookUpTable'])) {
								$temp = $value;
								$value = $this->lookUpTranslation($setup['lookUpTable'], $value, TRUE);
								if ($setup['lookUpTable']['enable404forInvalidAlias'] && !t3lib_div::testInt($value) && !strcmp($value, $temp)) {
									$this->decodeSpURL_throw404('Couldn\'t map alias "' . $value . '" to an ID');
								}
							} elseif (isset($setup['valueDefault'])) { // If no matching value and a default value is given, set that:
								$value = $setup['valueDefault'];
							}

							// Set previous value:
							$prevVal = $value;

							// Add to GET string:
							if ($setup['GETvar'] && strlen($value)) { // Checking length of value; normally a *blank* parameter is not found in the URL! And if we don't do this we may disturb "cHash" calculations!
								if (isset($this->extConf['init']['emptySegmentValue']) && $this->extConf['init']['emptySegmentValue'] === $value) {
									$value = '';
								}
								$GET_string .= '&' . rawurlencode($setup['GETvar']) . '=' . rawurlencode($value);
							}
						} else {
							array_unshift($pathParts, $origValue);
							break;
						}
						break;
				}
			}
		}

		return $GET_string;
	}

	/**
	 * Traverses incoming array of GET-var => value pairs and implodes that to a string of GET parameters
	 *
	 * @param	array		Parameters
	 * @return	string		GET parameters
	 * @see decodeSpURL_fileName(), decodeSpURL_settingPostVarSets(), encodeSpURL_setSingle()
	 */
	protected function decodeSpURL_getSingle($keyValues) {
		$GET_string = '';
		if (is_array($keyValues)) {
			foreach ($keyValues as $kkey => $vval) {
				$GET_string .= '&' . rawurlencode($kkey) . '=' . rawurlencode($vval);
			}
		}
		return $GET_string;
	}

	/**
	 * Throws a 404 message.
	 *
	 * @param	string		Message string
	 * @return	void
	 */
	public function decodeSpURL_throw404($msg) {

		// Log error:
		if (!$this->extConf['init']['disableErrorLog']) {
			$hash = t3lib_div::md5int($this->speakingURIpath_procValue);
			$rootpage_id = intval($this->extConf['pagePath']['rootpage_id']);
			$cond = 'url_hash=' . intval($hash) . ' AND rootpage_id=' . $rootpage_id;
			$fields_values = array('url_hash' => $hash, 'url' => $this->speakingURIpath_procValue, 'error' => $msg, 'counter' => 1, 'tstamp' => time(), 'cr_date' => time(), 'rootpage_id' => $rootpage_id, 'last_referer' => t3lib_div::getIndpEnv('HTTP_REFERER'));
			if ($this->useMySQLExtendedSyntax) {
				$query = $GLOBALS['TYPO3_DB']->INSERTquery('tx_realurl_errorlog', $fields_values);
				$query .= ' ON DUPLICATE KEY UPDATE ' . 'error=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($msg, 'tx_realurl_errorlog') . ',' . 'counter=counter+1,' . 'tstamp=' . $fields_values['tstamp'] . ',' . 'last_referer=' . $GLOBALS['TYPO3_DB']->fullQuoteStr(t3lib_div::getIndpEnv('HTTP_REFERER'), 'tx_realurl_errorlog');
				$GLOBALS['TYPO3_DB']->sql_query($query);
			} else {
				$GLOBALS['TYPO3_DB']->sql_query('START TRANSACTION');
				list($error_row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('counter', 'tx_realurl_errorlog', $cond);
				if (count($error_row)) {
					$fields_values = array('error' => $msg, 'counter' => $error_row['counter'] + 1, 'tstamp' => time(), 'last_referer' => t3lib_div::getIndpEnv('HTTP_REFERER'));
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_errorlog', $cond, $fields_values);
				} else {
					$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_errorlog', $fields_values);
				}
				$GLOBALS['TYPO3_DB']->sql_query('COMMIT');
			}
		}

		// Call handler
		$this->pObj->pageNotFoundAndExit($msg);
	}

	/**
	 * This function either a) jumps to the Backend Login page with redirect URL to current page (that is if no BE-login is currently found) or b) it enables edit icons on the page
	 *
	 * @return	void
	 */
	protected function decodeSpURL_jumpAdmin() {
		if ($this->pObj->beUserLogin && is_object($GLOBALS['BE_USER'])) {
			if ($this->extConf['init']['adminJumpToBackend']) {
				$this->decode_editInBackend = TRUE;
			} elseif ($GLOBALS['BE_USER']->extAdmEnabled) {
				$GLOBALS['TSFE']->displayFieldEditIcons = 1;
				$GLOBALS['BE_USER']->uc['TSFE_adminConfig']['edit_editNoPopup'] = 1;

				$GLOBALS['TSFE']->applicationData['tx_realurl']['adminJumpActive'] = 1;
				$GLOBALS['TSFE']->set_no_cache();
			}
		} else {
			$adminUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . TYPO3_mainDir . 'index.php?redirect_url=' . rawurlencode(t3lib_div::getIndpEnv('REQUEST_URI'));
			header('Location: ' . t3lib_div::locationHeaderUrl($adminUrl));
			exit();
		}
	}

	/**
	 * Will exit after redirect to backend (with "&edit=...") if $this->decode_editInBackend is set
	 *
	 * @param	integer		Page id.
	 * @return	void
	 */
	protected function decodeSpURL_jumpAdmin_goBackend($pageId) {
		if ($this->decode_editInBackend) {
			$editUrl = t3lib_div::getIndpEnv('TYPO3_SITE_URL') . TYPO3_mainDir . 'alt_main.php?edit=' . intval($pageId);
			header('Location: ' . t3lib_div::locationHeaderUrl($editUrl));
			exit();
		}
	}

	/**
	 * Manages caching of URLs to be decoded.
	 *
	 * @param	string		Speaking URL path to be decoded
	 * @param	array		Optional; If supplied array then this array is stored as the cached information for the input $speakingURIpath. If this argument is not set the method tries to look up such an array associated with input speakingURIpath
	 * @return	mixed		Returns array with cached information related to $speakingURIpath (unless $cachedInfo is an array in which case it is stored back to database).
	 */
	protected function decodeSpURL_decodeCache($speakingURIpath, $cachedInfo = '') {

		if ($this->extConf['init']['enableUrlDecodeCache'] && !$this->disableDecodeCache) {

			// Create hash string:
			if (is_array($cachedInfo)) { // STORE cachedInfo


				if (!$this->isBEUserLoggedIn() && $this->canCachePageURL($cachedInfo['id'])) {
					$rootpage_id = intval($cachedInfo['rootpage_id']);
					$hash = md5($speakingURIpath . $rootpage_id);

					$insertFields = array('url_hash' => $hash, 'spurl' => $speakingURIpath, 'content' => serialize($cachedInfo), 'page_id' => $cachedInfo['id'], 'rootpage_id' => $rootpage_id, 'tstamp' => time());
					if ($this->useMySQLExtendedSyntax) {
						$query = $GLOBALS['TYPO3_DB']->INSERTquery('tx_realurl_urldecodecache', $insertFields);
						$query .= ' ON DUPLICATE KEY UPDATE tstamp=' . $insertFields['tstamp'];
						$GLOBALS['TYPO3_DB']->sql_query($query);
					} else {
						$GLOBALS['TYPO3_DB']->sql_query('START TRANSACTION');
						$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_urldecodecache', 'url_hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($hash, 'tx_realurl_urldecodecache'));
						$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_urldecodecache', $insertFields);
						$GLOBALS['TYPO3_DB']->sql_query('COMMIT');
					}
				}
			}
			else {
				// GET cachedInfo.
				$rootpage_id = intval($this->extConf['pagePath']['rootpage_id']);
				$hash = md5($speakingURIpath . $rootpage_id);
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('content', 'tx_realurl_urldecodecache',
								'url_hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($hash, 'tx_realurl_urldecodecache') .
								' AND ' .
								//No need for root page id if we use full md5!
								//'rootpage_id='.intval($rootpage_id) . ' AND ' .
								'tstamp>' . strtotime('midnight', time() - 24 * 3600 * $this->decodeCacheTTL));
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
				if ($row) {
					return unserialize($row['content']);
				}
			}
		}
		return null;
	}

	/**
	 * Get "cHash" GET var from database. See explanation in encodeSpURL_cHashCache()
	 *
	 * @param	string		Speaking URL path (virtual path)
	 * @return	string		cHash value, if any.
	 * @see encodeSpURL_cHashCache()
	 */
	protected function decodeSpURL_cHashCache($speakingURIpath) {
		// Look up cHash for this spURL
		// Apart from returning the right cHash value it can also:
		// - Return no value (eg. if table has been cleared) even if there should
		//   be one! In this scenario it will look to the outside as if all
		//   parameters except cHash has been set.
		// - Return a WRONG value (eg. if URLs has been changed internally, there
		//   are dublets etc.). In this scenario the cHash value should not match
		//   the calculated one in the tslib_fe and the usual error of that problem
		//   be issued (whatever that is). This scenario could even mean that a
		//   cHash value is returned even if no cHash value applies at all.
		// Bottomline is: the realurl extension makes it more likely that a wrong
		// cHash value is passed to the frontend but as such it doesn't do anything
		// which a fabricated URL couldn't contain.
		// I still don't know how to handle wrong cHash values in this table. It
		// will seldomly be a problem (when parameters are changed manually mostly)
		// but when it is, we have no standard procedure to clean it up. Of course
		// clearing it will mean it is built up again - but also that tons of URLs
		// will not work reliably!
		list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('chash_string',
			'tx_realurl_chashcache', 'spurl_hash=' .
			$GLOBALS['TYPO3_DB']->fullQuoteStr(md5($speakingURIpath),
				'tx_realurl_chashcache'));
		return is_array($row) ? $row['chash_string'] : false;
	}

	/*******************************
	 *
	 * Alias-ID look up functions
	 *
	 ******************************/

	/**
	 * Doing database lookup between "alias values" and "id numbers". Translation is bi-directional.
	 *
	 * @param	array		Configuration of look-up table, field names etc.
	 * @param	string		Value to match field in database to.
	 * @param	boolean		If TRUE, the input $value is an alias-string that needs translation to an ID integer. FALSE (default) means the reverse direction
	 * @return	string		Result value of lookup. If no value was found the $value is returned.
	 */
	protected function lookUpTranslation($cfg, $value, $aliasToUid = FALSE) {
		// Assemble list of fields to look up. This includes localization related fields:
		$langEnabled = FALSE;
		$fieldList = array();
		if ($cfg['languageGetVar'] && $cfg['transOrigPointerField'] && $cfg['languageField']) {

			$fieldList[] = 'uid';
			$fieldList[] = $cfg['transOrigPointerField'];
			$fieldList[] = $cfg['languageField'];
			$langEnabled = TRUE;
		}

		// Translate an alias string to an ID:
		if ($aliasToUid) {

			// First, test if there is an entry in cache for the alias:
			if ($cfg['useUniqueCache'] && $returnId = $this->lookUp_uniqAliasToId($cfg, $value)) {
				return $returnId;
			} else { // If no cached entry, look it up directly in the table:


				$fieldList[] = $cfg['id_field'];
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(implode(',', $fieldList), $cfg['table'],
									$cfg['alias_field'] . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $cfg['table']) .
									' ' . $cfg['addWhereClause']);
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
				if ($row) {
					$returnId = $row[$cfg['id_field']];

					// If localization is enabled, check if this record is a localized version and if so, find uid of the original version.
					if ($langEnabled && $row[$cfg['languageField']] > 0) {
						$returnId = $row[$cfg['transOrigPointerField']];
					}

					// Return the id:
					return $returnId;
				}
			}
		} else { // Translate an ID to alias string


			// Define the language for the alias:
			$lang = intval($this->orig_paramKeyValues[$cfg['languageGetVar']]);
			if (t3lib_div::inList($cfg['languageExceptionUids'], $lang)) { // Might be excepted (like you should for CJK cases which does not translate to ASCII equivalents)
				$lang = 0;
			}

			// First, test if there is an entry in cache for the id:
			if ($cfg['useUniqueCache'] && !$cfg['autoUpdate'] && $returnAlias = $this->lookUp_idToUniqAlias($cfg, $value, $lang)) {
				return $returnAlias;
			} else { // If no cached entry, look up alias directly in the table (and possibly store cache value)


				$fieldList[] = $cfg['alias_field'];
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(implode(',', $fieldList), $cfg['table'],
							$cfg['id_field'] . '=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($value, $cfg['table']) .
							' ' . $cfg['addWhereClause']);
				$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				$GLOBALS['TYPO3_DB']->sql_free_result($res);
				if ($row) {

					// Looking for localized version of that:
					if ($langEnabled && $lang) {

						// If the lang value is there, look for a localized version of record:
						$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery($cfg['alias_field'], $cfg['table'],
								$cfg['transOrigPointerField'] . '=' . intval($row['uid']) . '
								AND ' . $cfg['languageField'] . '=' . intval($lang) . '
								' . $cfg['addWhereClause']);
						$lrow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
						$GLOBALS['TYPO3_DB']->sql_free_result($res);
						if ($lrow) {
							$row = $lrow;
						}
					}

					$mLength = $cfg['maxLength'] ? $cfg['maxLength'] : $this->maxLookUpLgd;

					if ($cfg['useUniqueCache']) { // If cache is to be used, store the alias in the cache:
						$aliasBaseValue = $row[$cfg['alias_field']];
						return $this->lookUp_newAlias($cfg, substr($aliasBaseValue, 0, $mLength), $value, $lang);
					} else { // If no cache for alias, then just return whatever value is appropriate:
						if (strlen($row[$cfg['alias_field']]) <= $mLength) {
							return $row[$cfg['alias_field']];
						} else {
							return $value;
						}
					}
				}
			}
		}

		// In case no value was found in translation we return the incoming value. It may be argued that this is not a good idea but generally this can be avoided by using the "useUniqueCache" principle which will ensure unique translation both ways.
		return $value;
	}

	/**
	 * Looks up an ID value (integer) in lookup-table based on input alias value.
	 * (The lookup table for id<->alias is meant to contain UNIQUE alias strings for id integers)
	 * In the lookup table 'tx_realurl_uniqalias' the field "value_alias" should be unique (per combination of field_alias+field_id+tablename)! However the "value_id" field doesn't have to; that is a feature which allows more aliases to point to the same id. The alias selected for converting id to alias will be the first inserted at the moment. This might be more intelligent in the future, having an order column which can be controlled from the backend for instance!
	 *
	 * @param	array		Configuration array
	 * @param	string		Alias value to convert to ID
	 * @param	boolean		<code>true</code> if only non-expiring record should be looked up
	 * @return	integer		ID integer. If none is found: false
	 * @see lookUpTranslation(), lookUp_idToUniqAlias()
	 */
	protected function lookUp_uniqAliasToId($cfg, $aliasValue, $onlyNonExpired = FALSE) {

		// Look up the ID based on input alias value:
		list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('value_id', 'tx_realurl_uniqalias',
				'value_alias=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($aliasValue, 'tx_realurl_uniqalias') .
				' AND field_alias=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['alias_field'], 'tx_realurl_uniqalias') .
				' AND field_id=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['id_field'], 'tx_realurl_uniqalias') .
				' AND tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['table'], 'tx_realurl_uniqalias') .
				' AND ' . ($onlyNonExpired ? 'expire=0' : '(expire=0 OR expire>' . time() . ')'));
		return (is_array($row) ? $row['value_id'] : false);
	}

	/**
	 * Looks up a alias string in lookup-table based on input ID value (integer)
	 * (The lookup table for id<->alias is meant to contain UNIQUE alias strings for id integers)
	 *
	 * @param	array		Configuration array
	 * @param	string		ID value to convert to alias value
	 * @param	integer		sys_language_uid to use for lookup
	 * @param	string		Optional alias value to limit search to
	 * @return	string		Alias string. If none is found: false
	 * @see lookUpTranslation(), lookUp_uniqAliasToId()
	 */
	protected function lookUp_idToUniqAlias($cfg, $idValue, $lang, $aliasValue = '') {

		// Look for an alias based on ID:
		list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('value_alias', 'tx_realurl_uniqalias',
				'value_id=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($idValue, 'tx_realurl_uniqalias') .
				' AND field_alias=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['alias_field'], 'tx_realurl_uniqalias') .
				' AND field_id=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['id_field'], 'tx_realurl_uniqalias') .
				' AND tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['table'], 'tx_realurl_uniqalias') .
				' AND lang=' . intval($lang) .
				' AND expire=0' .
				($aliasValue ? ' AND value_alias=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($aliasValue, 'tx_realurl_uniqalias') : ''),
				'', '', '1');
		if (is_array($row)) {
			return $row['value_alias'];
		}
		return null;
	}

	/**
	 * Creates a new alias<->id relation in database lookup table.
	 *
	 * WARNING! This function is internal to RealURL. It is made public for
	 * backwards compatibility but its behavior and parameters may change as
	 * necessary for RealURL. No guaranties at all!
	 *
	 * @param	array		Configuration array of lookup table
	 * @param	string		Preferred new alias (final alias might be different if duplicates were found in the cache)
	 * @param	integer		ID associated with alias
	 * @param	integer		sys_language_uid to store with record
	 * @return	string		Final alias string
	 * @see lookUpTranslation()
	 * @internal
	 */
	protected function lookUp_newAlias($cfg, $newAliasValue, $idValue, $lang) {

		// Clean preferred alias
		$newAliasValue = $this->lookUp_cleanAlias($cfg, $newAliasValue);

		// If autoupdate is true we might be here even if an alias exists. Therefore we check if that alias is the $newAliasValue and if so, we return that instead of making a new, unique one.
		if ($cfg['autoUpdate'] && $this->lookUp_idToUniqAlias($cfg, $idValue, $lang, $newAliasValue)) {
			return $newAliasValue;
		}

		// Now, go create a unique alias:
		$uniqueAlias = '';
		$counter = 0;
		$maxTry = 100;
		$test_newAliasValue = $newAliasValue;
		while ($counter < $maxTry) {

			// If the test-alias did NOT exist, it must be unique and we break out:
			$foundId = $this->lookUp_uniqAliasToId($cfg, $test_newAliasValue, true);
			if (!$foundId || $foundId == $idValue) {
				$uniqueAlias = $test_newAliasValue;
				break;
			}
			// Otherwise, increment counter and test again...
			$counter++;
			$test_newAliasValue = $newAliasValue . '-' . $counter;
		}

		// if no unique alias was found in the process above, just suffix a hash string and assume that is unique...
		if (!$uniqueAlias) {
			$uniqueAlias = $newAliasValue .= '-' . t3lib_div::shortMD5(microtime());
		}

		// Insert the new id<->alias relation:
		$insertArray = array('tstamp' => time(), 'tablename' => $cfg['table'], 'field_alias' => $cfg['alias_field'], 'field_id' => $cfg['id_field'], 'value_alias' => $uniqueAlias, 'value_id' => $idValue, 'lang' => $lang);

		// Checking that this alias hasn't been stored since we looked last time:
		$returnAlias = $this->lookUp_idToUniqAlias($cfg, $idValue, $lang, $uniqueAlias);
		if ($returnAlias) {
			// If we are here it is because another process managed to create this alias in the time between we looked the first time and now when we want to put it in database.
			$uniqueAlias = $returnAlias;
		}
		else {
			// Expire all other aliases:
			// Look for an alias based on ID:
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_uniqalias', 'value_id=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($idValue, 'tx_realurl_uniqalias') . '
					AND field_alias=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['alias_field'], 'tx_realurl_uniqalias') . '
					AND field_id=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['id_field'], 'tx_realurl_uniqalias') . '
					AND tablename=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($cfg['table'], 'tx_realurl_uniqalias') . '
					AND lang=' . intval($lang) . '
					AND expire=0', array('expire' => time() + 24 * 3600 * ($cfg['expireDays'] ? $cfg['expireDays'] : 60)));

			// Store new alias:
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_uniqalias', $insertArray);
		}

		// Return new unique alias:
		return $uniqueAlias;
	}

	/**
	 * Clean up the alias
	 * (Almost the same function as encodeTitle() in class.tx_realurl_advanced.php)
	 *
	 * @param	array		Configuration array
	 * @param	string		Alias value to clean up
	 * @return	string		New alias value
	 * @see lookUpTranslation()
	 */
	public function lookUp_cleanAlias($cfg, $newAliasValue) {

		// Fetch character set:
		$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;
		$processedTitle = $newAliasValue;

		// Convert to lowercase:
		if ($cfg['useUniqueCache_conf']['strtolower']) {
			$processedTitle = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $processedTitle, 'toLower');
		}

		// Convert some special tokens to the space character:
		$space = $cfg['useUniqueCache_conf']['spaceCharacter'] ? substr($cfg['useUniqueCache_conf']['spaceCharacter'], 0, 1) : '_';
		$processedTitle = strtr($processedTitle, ' -+_', $space . $space . $space . $space); // convert spaces


		// Convert extended letters to ascii equivalents:
		$processedTitle = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($charset, $processedTitle);

		// Strip the rest
		if ($this->extConf['init']['enableAllUnicodeLetters']) {
			// Warning: slow!!!
			$processedTitle = preg_replace('/[^\p{L}0-9\\' . $space . ']/u', '', $processedTitle);
		}
		else {
			$processedTitle = preg_replace('/[^a-zA-Z0-9\\' . $space . ']/', '', $processedTitle);
		}
		$processedTitle = preg_replace('/\\' . $space . '{2,}/', $space, $processedTitle); // Convert multiple 'spaces' to a single one
		$processedTitle = trim($processedTitle, $space);

		if ($cfg['useUniqueCache_conf']['encodeTitle_userProc']) {
			$encodingConfiguration = array('strtolower' => $cfg['useUniqueCache_conf']['strtolower'], 'spaceCharacter' => $cfg['useUniqueCache_conf']['spaceCharacter']);
			$params = array('pObj' => &$this, 'title' => $newAliasValue, 'processedTitle' => $processedTitle, 'encodingConfiguration' => $encodingConfiguration);
			$processedTitle = t3lib_div::callUserFunction($cfg['useUniqueCache_conf']['encodeTitle_userProc'], $params, $this);
		}

		// Return value:
		return $processedTitle;
	}

	/*******************************
	 *
	 * General helper functions (both decode/encode)
	 *
	 ******************************/

	/**
	 * Sets configuration in $this->extConf, taking host domain into account
	 *
	 * @return	void
	 * @see encodeSpURL(), decodeSpURL()
	 */
	protected function setConfig() {

		// Finding host-name / IP, always in lowercase:
		$this->hostConfigured = $this->host = $this->getHost();

		$_realurl_conf = (array)@unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
		// Autoconfiguration
		if ($_realurl_conf['enableAutoConf'] && !isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']) && !@include_once (PATH_site . TX_REALURL_AUTOCONF_FILE) && !isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'])) {
			require_once (t3lib_extMgm::extPath('realurl', 'class.tx_realurl_autoconfgen.php'));
			$_realurl_gen = t3lib_div::makeInstance('tx_realurl_autoconfgen');
			$_realurl_gen->generateConfiguration();
			unset($_realurl_gen);
			@include_once(PATH_site . TX_REALURL_AUTOCONF_FILE);
		}

		$extConf = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];

		$this->multidomain = $this->isMultidomain();

		// First pass, finding configuration OR pointer string:
		if (isset($extConf[$this->host])) {
			$this->extConf = $extConf[$this->host];

			// If it turned out to be a string pointer, then look up the real config:
			while (!is_null($this->extConf) && is_string($this->extConf)) {
				$this->hostConfigured = $this->extConf;
				$this->extConf = $extConf[$this->extConf];
			}
			if (!is_array($this->extConf)) {
				$this->extConf = $extConf['_DEFAULT'];
				$this->hostConfigured = '_DEFAULT';
				if ($this->multidomain && isset($this->extConf['pagePath']['rootpage_id'])) {
					// This can't be right!
					unset($this->extConf['pagePath']['rootpage_id']);
				}
			}
		}
		else {
			if ($this->enableStrictMode && $this->multidomain) {
				$this->pObj->pageNotFoundAndExit('RealURL strict mode error: ' .
					'multidomain configuration detected and domain \'' . $this->host .
					'\' is not configured for RealURL. Please, fix your RealURL configuration!');
			}
			$this->extConf = (array)$extConf['_DEFAULT'];
			$this->hostConfigured = '_DEFAULT';
			if ($this->multidomain && isset($this->extConf['pagePath']['rootpage_id'])) {
				// This can't be right!
				unset($this->extConf['pagePath']['rootpage_id']);
			}
		}
	}

	/**
	 * Determines the current host. Sometimes it is not possible to determine
	 * that from the environment, so the hook is used to get the host from the
	 * third-party scripts.
	 *
	 * @return string
	 */
	protected function getHost() {
		$host = strtolower((string)t3lib_div::getIndpEnv('HTTP_HOST'));

		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['getHost'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['getHost'] as $userFunc) {
				$hookParams = array(
					'host' => $host,
				);
				$newHost = t3lib_div::callUserFunction($userFunc, $hookParams, $this);
				if (!empty($newHost) && is_string($newHost)) {
					$host = $newHost;
				}
			}
		}

		return $host;
	}

	/**
	 * Returns configuration for a postVarSet (default) based on input page id
	 *
	 * @param	integer		Page id
	 * @param	string		Main key in realurl configuration array. Default is "postVarSets" but could be "fixedPostVars"
	 * @return	array		Configuration array
	 * @see decodeSpURL_doDecode()
	 * @internal
	 */
	public function getPostVarSetConfig($page_id, $mainCat = 'postVarSets') {

		// If the page id is NOT an integer, it's an alias we have to look up:
		if (!t3lib_div::testInt($page_id)) {
			$page_id = $this->pageAliasToID($page_id);
		}

		// Checking if the value is not an array but a pointer to another key:
		if (isset($this->extConf[$mainCat][$page_id]) && !is_array($this->extConf[$mainCat][$page_id])) {
			$page_id = $this->extConf[$mainCat][$page_id];
		}

		$cfg = is_array($this->extConf[$mainCat][$page_id]) ? $this->extConf[$mainCat][$page_id] :
			(is_array($this->extConf[$mainCat]['_DEFAULT']) ? $this->extConf[$mainCat]['_DEFAULT'] : array());
		return $cfg;
	}

	/**
	 * Page alias-to-id translation including memory caching.
	 *
	 * @param	string		Page Alias string
	 * @return	integer		Page id, zero if none was found.
	 */
	protected function pageAliasToID($alias) {

		// Look in memory cache first, and if not there, look it up:
		if (!isset($GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE_aliases'][$alias])) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid', 'pages',
						'alias=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($alias, 'pages') .
						' AND pages.deleted=0');
			$pageRec = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			$GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE_aliases'][$alias] = intval($pageRec['uid']);
		}

		// Return ID:
		return $GLOBALS['TSFE']->applicationData['tx_realurl']['_CACHE_aliases'][$alias];
	}

	/**
	 * Rawurlencodes the input string; used for GET parameter names of variables that were NOT SpURL encoded. Offers the possibility of NOT encoding them...
	 *
	 * @param	string		Input string
	 * @return	string		Output string
	 * @see encodeSpURL()
	 */
	protected function rawurlencodeParam($str) {
		if (!$this->extConf['init']['doNotRawUrlEncodeParameterNames']) {
			return rawurlencode($str);
		} else
			return $str;
	}

	/**
	 * Checks condition for varSets
	 *
	 * @param	array		Configuration for condition
	 * @param	string		Previous value in sequence of GET vars. The value is the "system" value; In other words: The *real* id, not the alias for a value.
	 * @return	boolean		TRUE if proceed is ok, otherwise false.
	 * @see encodeSpURL_setSequence(), decodeSpURL_getSequence()
	 */
	protected function checkCondition($setup, $prevVal) {

		$return = TRUE;

		//	 Check previous value:
		if (isset($setup['prevValueInList'])) {
			if (!t3lib_div::inList($setup['prevValueInList'], $prevVal))
				$return = FALSE;
		}

		return $return;
	}

	/**
	 * Checks if BE user is logged in.
	 *
	 * @return	boolean		<code>true</code> if BE user is logged in
	 * @internal
	 */
	public function isBEUserLoggedIn() {
		return $this->pObj->beUserLogin;
	}

	/**
	 * Adjusts the configuration used for RealURL processing, depending on a specific domain disposal.
	 *
	 * @param	string		$type: Calling type of realurl (encode|decode)
	 * @param	array		$params: Parameters delivered to RealURL (e.g. from t3lib_TStemplate->linkData hook)
	 * @return	mixed		Information required for further processing or false if something went wrong
	 */
	protected function adjustConfigurationByHost($type, $params = null) {
		$result = false;

		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DOMAINS'])) {
			$configuration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DOMAINS'];

			if (is_array($configuration)) {
				if ($type == 'encode' && isset($configuration['encode'])) {
					$result = $this->adjustConfigurationByHostEncode($configuration['encode'], $params);
				} elseif ($type == 'decode' && isset($configuration['decode'])) {
					$result = $this->adjustConfigurationByHostDecode($configuration['decode']);
				}
			}
		}

		return $result;
	}

	/**
	 * Adjusts the configuration used for RealURL path encoding, depending on a specific domain disposal.
	 *
	 * @param	array		$configuration: Configuration required to determine hosts while path encoding
	 * @param	array		$params: Parameters delivered to RealURL by t3lib_TStemplate->linkData hook
	 * @return	mixed		Information required for further processing or false if something went wrong
	 */
	protected function adjustConfigurationByHostEncode($configuration, $params) {
		$this->ignoreGETvar = '';
		if (is_array($params) && isset($params['LD']['totalURL']) && is_array($configuration)) {
			$urlParts = parse_url($params['LD']['totalURL']);
			$urlParams = array();
			parse_str($urlParts['query'], $urlParams);

			foreach ($configuration as $disposal) {
				if (isset($disposal['GETvar']) && isset($disposal['value'])) {
					$GETvar = $disposal['GETvar'];
					$currentValue = t3lib_div::_GET($GETvar);
					$expectedValue = (isset($urlParams[$GETvar]) ? $urlParams[$GETvar] : false);
					if ($expectedValue !== false && $disposal['value'] == $expectedValue) {
						if (!isset($disposal['ifDifferentToCurrent']) || $disposal['value'] != $currentValue) {
							if (isset($disposal['useConfiguration'])) {
								$this->ignoreGETvar = $GETvar;
								$this->setConfigurationByReference($disposal['useConfiguration']);
							}
							return $disposal;
						}
						else {
							$this->ignoreGETvar = $GETvar;
							break;
						}
					}
				}
			}
		}

		return false;
	}

	/**
	 * Adjusts the configuration used for RealURL path decoding, depending on a specific domain disposal.
	 *
	 * @param	array		$configuration: Configuration required to determine hosts while path decoding
	 * @return	void
	 */
	protected function adjustConfigurationByHostDecode($configuration) {
		if (is_array($configuration)) {
			$host = strtolower(t3lib_div::getIndpEnv('TYPO3_HOST_ONLY'));
			$hostConfiguration = false;

			if (isset($configuration[$host])) {
				$hostConfiguration = $configuration[$host];
			} else {
				$keys = array_keys($configuration);
				foreach ($keys as $regexp) {
					if (preg_match('/^\/[^\/]+\/$/', $regexp) && preg_match($regexp, $host)) {
						$hostConfiguration = $configuration[$regexp];
						break;
					}
				}
			}

			if (is_array($hostConfiguration)) {
				if (isset($hostConfiguration['GETvars']) && is_array($hostConfiguration['GETvars'])) {
					foreach ($hostConfiguration['GETvars'] as $key => $value) {
						$_GET[$key] = $value;
					}
					if (isset($hostConfiguration['useConfiguration'])) {
						$this->setConfigurationByReference($hostConfiguration['useConfiguration']);
					}
				}
			}
		}
	}

	/**
	 * Sets the configuration to an existing configuration part by a reference.
	 *
	 * @param	string		$useConfiguration: Reference to another existing configuration part which shall be used
	 * @return	boolean		Whether the action was successful
	 */
	protected function setConfigurationByReference($useConfiguration) {
		$extConf = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		$resolved = array();
		$result = false;

		// Resolve configurations references and avoid endless loops:
		while (is_string($useConfiguration) && isset($extConf[$useConfiguration]) && !in_array($useConfiguration, $resolved)) {
			$resolved[] = $useConfiguration;
			$useConfiguration = $extConf[$useConfiguration];
		}
		// Adjust the configuration:
		if (is_array($useConfiguration)) {
			$this->extConf = $useConfiguration;
			$result = true;
		}

		return $result;
	}

	/**********************************
	 *
	 * External Hooks
	 *
	 **********************************/

	/**
	 * Hook function for clearing page cache
	 *
	 * @param	array		Params for hook
	 * @return	void
	 */
	public function clearPageCacheMgm($params) {
		$pageIdArray = $params['table'] == 'pages' ? array(intval($params['uid'])) : $params['pageIdArray'];
		if (is_array($pageIdArray) && count($pageIdArray) > 0) {
			$pageIdList = implode(',', $GLOBALS['TYPO3_DB']->cleanIntArray($pageIdArray));
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_urlencodecache', 'page_id IN (' . $pageIdList . ')');
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_urldecodecache', 'page_id IN (' . $pageIdList . ')');
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_pathcache', 'page_id IN (' . $pageIdList . ') AND expire>0 AND expire<=' . time());
		}
	}

	/**
	 * Checks for wrong boolean values (like <code>'1'</code> or </code>'true'</code> instead of <code>1</code> and <code>true</code>.
	 *
	 * @param	mixed		$str Parameter to check
	 * @param	string		$paramName Parameter name (for logging)
	 * @return	<code>true</code>		if string (and not bad boolean)
	 */
	protected function isString(&$str, $paramName) {
		if (!is_string($str)) {
			return false;
		}
		if (preg_match('/^(1|0|true|false)$/i', $str)) {
			$logMessage = sprintf('Wrong boolean value for parameter "%s": "%s". It is a string, not a boolean!', $paramName, $str);
			$this->devLog($logMessage);
			$GLOBALS['TT']->setTSlogMessage($logMessage, 2);
			if ($str == intval($str)) {
				$str = intval($str);
			} else {
				$str = (strtolower($str) == 'true');
			}
			return false;
		}
		return true;
	}

	/**
	 * Attempts to find root page ID for the current host. Processes redirectes as well.
	 *
	 * @return	mixed		Found root page UID or false if not found
	 * @internal
	 */
	public function findRootPageId() {
		$rootpage_id = false; $host = $this->host;

		if (!$this->enableStrictMode) {

			// Search by host
			do {
				$domain = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pid,redirectTo,domainName', 'sys_domain',
					'domainName=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($host, 'sys_domain') .
					' AND hidden=0');
				if (count($domain) > 0) {
					if (!$domain[0]['redirectTo']) {
						$rootpage_id = intval($domain[0]['pid']);
						$this->devLog('Found rootpage_id by domain lookup', array('domain' => $domain[0]['domainName'], 'rootpage_id' => $rootpage_id));
						break;
					}
					else {
						$parts = @parse_url($domain[0]['redirectTo']);
						$host = $parts['host'];
					}
				}
			} while (count($domain) > 0);

			// If root page id is not found, try other ways. We can do it only
			// and only if there are no multiple domains. Otherwise we would
			// get a lot of wrong page ids from old root pages, etc.
			if (!$rootpage_id && !$this->multidomain) {
				// Try by TS template
				$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pid',
							'sys_template', 'root=1 AND hidden=0 AND deleted=0');
				if (count($rows) == 1) {
					$rootpage_id = $rows[0]['pid'];
					$this->devLog('Found rootpage_id by searching sys_template', array('rootpage_id' => $rootpage_id));
				}
			}
		}
		return $rootpage_id;
	}

	/**
	 * Checks if TYPO3 runs in the multidomain environment with different page ids
	 *
	 * @return	boolean
	 */
	protected function isMultidomain() {
		static $multidomain = null;

		if ($multidomain === null) {
			list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('COUNT(distinct pid) AS t',
				'sys_domain', 'redirectTo=\'\' AND hidden=0');
			$multidomain = ($row['t'] > 1);
		}
		return $multidomain;
	}

	/**
	 * Checks if rootpage_id is set and if not, sets it
	 *
	 * @return	void
	 */
	protected function adjustRootPageId() {
		if (!$this->extConf['pagePath']['rootpage_id']) {

			if ($this->enableStrictMode) {
				$this->pObj->pageNotFoundAndExit('RealURL strict mode error: ' .
					'multidomain configuration without rootpage_id. ' .
					'Please, fix your RealURL configuration!');
			}

			$GLOBALS['TT']->setTSlogMessage('RealURL warning: rootpage_id was not configured!');

			$this->extConf['pagePath']['rootpage_id'] = $this->findRootPageId();

			if ($this->multidomain && !$this->extConf['pagePath']['rootpage_id']) {
				$this->pObj->pageNotFoundAndExit('RealURL error: ' .
					'unable to determine rootpage_id for the current domain.');
			}
		}
	}

	/**
	 * Cleans up empty path segments
	 *
	 * @param array $pathParts
	 * @return array
	 */
	protected function cleanUpPathParts(array $pathParts) {
		// Remove trailing empty segments
		for ($index = count($pathParts) - 1; $index >= 0 && $pathParts[$index] == ''; $index--) {
			unset($pathParts[$index]);
		}
		if (isset($this->extConf['init']['emptySegmentValue'])) {
			$emptyValue = rawurlencode($this->extConf['init']['emptySegmentValue']);
			// Set empty value
			for ($index = count($pathParts) - 1; $index >= 0; $index--) {
				if ($pathParts[$index] == '') {
					$pathParts[$index] = $emptyValue;
				}
			}
		}
		return $pathParts;
	}

	/**
	 * Creates a new URL and appends a file name to the url if necessary
	 *
	 * @param array $paramKeyValues
	 * @param array $pathParts
	 * @return string
	 */
	protected function createURLWithFileName(array &$paramKeyValues, array $pathParts) {
		$url = implode('/', $pathParts);
		$paramKeyValuesCopy = $paramKeyValues;
		$fileName = rawurlencode($this->encodeSpURL_fileName($paramKeyValues));

		if ($fileName{0} == '.') {
			// Only extension
			if ($url == '') {
				// Home page. We can't append just extension here. So we pass
				// parameters as is to the home page.
				$paramKeyValues = $paramKeyValuesCopy;
			}
			else {
				$url .= $fileName;
			}
		}
		elseif ($fileName) {
			// File name includes extension
			$url .= '/' . $fileName;
		}
		elseif ($url != '') {
			$suffix = $this->extConf['fileName']['defaultToHTMLsuffixOnPrev'];
			if ($suffix) {
				if (!$this->isString($suffix, 'defaultToHTMLsuffixOnPrev')) {
					$suffix = '.html';
				}
				$url .= $suffix;
			}
			else {
				$url .= '/';
			}
		}

		return $url;
	}

	/**
	 * Fixes empty URL
	 */
	protected function fixEmptyUrl($newUrl) {
		if (!strlen($newUrl)) {
			if (is_bool($this->extConf['init']['emptyUrlReturnValue']) && $this->extConf['init']['emptyUrlReturnValue']) {
				$newUrl = ($GLOBALS['TSFE']->config['config']['absRefPrefix'] ? $GLOBALS['TSFE']->config['config']['absRefPrefix'] : $GLOBALS['TSFE']->baseUrl);
			} else {
				$newUrl = '' . $this->extConf['init']['emptyUrlReturnValue'];
			}
		}
		return $newUrl;
	}

	/**
	 * Checks if system runs in non-live workspace
	 *
	 * @return boolean
	 */
	protected function isInWorkspace() {
		$result = false;
		if ($GLOBALS['TSFE']->beUserLogin) {
			$result = ($GLOBALS['BE_USER']->workspace != 0);
		}
		return $result;
	}

	/**
	 * Outputs a devLog message
	 *
	 * @param string $message
	 * @param int $severity
	 * @param mixed $dataVar
	 * @return void
	 * @internal
	 */
	public function devLog($message, $dataVar = false, $severity = 0) {
		if ($this->enableDevLog) {
			t3lib_div::devLog('[' . $this->devLogId . '] ' . $message, 'realurl', $severity, $dataVar);
		}
	}

	/**
	 * Sets encoding result to error
	 *
	 * @return void
	 * @internal
	 */
	public function setEncodeError() {
		$this->encodeError = true;
	}

	/**
	 * Obtains a copy of configuration
	 *
	 * @return array
	 * @internal
	 */
	public function getConfiguration() {
		return $this->extConf;
	}

	/**
	 * Appends the file part to path segments.
	 *
	 * @param array $segments
	 * @internal
	 */
	public function appendFilePart(array &$segments) {
		if ($this->filePart) {
			if ($this->filePart{0} == '.') {
				$segmentCount = count($segments);
				if ($segmentCount > 0) {
					$segments[$segmentCount - 1] .= urlencode($this->filePart);
				}
			}
			else {
				$segments[] = urlencode($this->filePart);
			}
		}
	}

	/**
	 * Determines if this page can be cached with RealURL encode or decode cache
	 *
	 * @param int $pageId
	 * @return boolean
	 */
	protected function canCachePageURL($pageId) {
		list($pageRecord) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('tx_realurl_nocache',
			'pages', 'uid=' . intval($pageId));
		return is_array($pageRecord) ? !$pageRecord['tx_realurl_nocache'] : false;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl.php']);
}

?>
