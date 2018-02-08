<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Dmitry Dulepov (dmitry.dulepov@gmail.com)
 *  All rights reserved
 *
 *  You may not remove or change the name of the author above. See:
 *  http://www.gnu.org/licenses/gpl-faq.html#IWantCredit
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
namespace DmitryDulepov\Realurl\Encoder;

use DmitryDulepov\Realurl\Cache\UrlCacheEntry;
use DmitryDulepov\Realurl\Configuration\ConfigurationReader;
use DmitryDulepov\Realurl\EncodeDecoderBase;
use DmitryDulepov\Realurl\Exceptions\InvalidLanguageParameterException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * This class contains encoder for the RealURL.
 *
 * @package DmitryDulepov\Realurl\Encoder
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class UrlEncoder extends EncodeDecoderBase {

	const MAX_ALIAS_LENGTH = 100;

	/** @var string */
	protected $encodedUrl = '';

	/** @var array */
	protected $encoderParameters;

	/**
	 * This is the URL with sorted GET parameters. It is used for cache
	 * manipulation.
	 *
	 * @var string
	 */
	protected $originalUrl;

	/** @var array */
	protected $originalUrlParameters = array();

	/** @var int */
	protected $sysLanguageUid;

	/** @var string */
	protected $urlToEncode;

	/** @var array */
	protected $urlParameters = array();

	/** @var string */
	protected $urlPrepend = '';

	/** @var array */
	static protected $urlPrependRegister = array();

	/** @var array */
	protected $usedAliases = array();

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * Fetches absRefPrefix. See https://github.com/dmitryd/typo3-realurl/issues/548
	 *
	 * @return string
	 */
	protected function getAbsRefPrefix() {
		$absRefPrefix = $this->tsfe->absRefPrefix ? $this->tsfe->absRefPrefix :
			(isset($this->tsfe->config['config']['absRefPrefix']) ? $this->tsfe->config['config']['absRefPrefix'] : '');

		if ($absRefPrefix === 'auto') {
			$absRefPrefix = GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
		}

		return $absRefPrefix;
	}

	/**
	 * Returns the configuration reader. This can be used in hooks.
	 *
	 * @return ConfigurationReader
	 */
	public function getConfiguration() {
		return $this->configuration;
	}

	/**
	 * Returns a coipy of original url parameters. This can be used in hooks.
	 *
	 * @return array
	 */
	public function getOriginalUrlParameters() {
		return $this->originalUrlParameters;
	}

	/**
	 * Returns $this->utility. This can be used in hooks.
	 *
	 * @return \DmitryDulepov\Realurl\Utility
	 */
	public function getUtility() {
		return $this->utility;
	}

	/**
	 * Entry point for the URL encoder.
	 *
	 * @param array $encoderParameters
	 * @return void
	 */
	public function encodeUrl(array &$encoderParameters) {
		$this->callEarlyHook($encoderParameters);
		$this->encoderParameters = $encoderParameters;
		$this->urlToEncode = $encoderParameters['LD']['totalURL'];
		if ($this->canEncoderExecute()) {
			try {
				$this->executeEncoder();
				$encoderParameters['LD']['totalURL'] = $this->encodedUrl .
					(isset($encoderParameters['LD']['sectionIndex']) ? $encoderParameters['LD']['sectionIndex'] : '');

				$this->logger->debug(
					sprintf(
						'Created speaking url "%s" from "%s"',
						$encoderParameters['LD']['totalURL'],
						$this->originalUrl
					),
					$encoderParameters
				);
			}
			catch (InvalidLanguageParameterException $exception) {
				// Pass through. We just return unencoded URL in such case.
			}
		}
		else {
			$this->logger->debug(
				sprintf(
					'URL "%s" cannot be encoded by realurl',
					$this->urlToEncode
				)
			);
		}
	}

	/**
	 * Post-processes the URL. If necessary prepends another domain to the URL.
	 *
	 * @param array $parameters
	 * @param ContentObjectRenderer $pObj
	 * @return void
	 */
	public function postProcessEncodedUrl(array &$parameters, ContentObjectRenderer $pObj) {
		if (isset($parameters['finalTagParts']['url'])) {

			// We must check for absolute URLs here because typolink can force
			// absolute URLs for pages with restricted access. It prepends
			// current host always. See http://bugs.typo3.org/view.php?id=18200
			$testUrl = $parameters['finalTagParts']['url'];
			if (preg_match('/^https?:\/\/[^\/]+\//', $testUrl)) {
				$testUrl = preg_replace('/^https?:\/\/[^\/]+\/(.*)$/', '\1', $testUrl);
			}

			list($testUrl, $section) = GeneralUtility::revExplode('#', $testUrl, 2);

			if (isset(self::$urlPrependRegister[$testUrl])) {
				$urlKey = $url = $testUrl;

				$url = self::$urlPrependRegister[$urlKey] . ($url{0} != '/' ? '/' : '') . $url;
				if ($section) {
					$url .= '#' . $section;
				}

				unset(self::$urlPrependRegister[$testUrl]);

				// Adjust the URL
				$parameters['finalTag'] = str_replace(
					'"' . htmlspecialchars($parameters['finalTagParts']['url']) . '"',
					'"' . htmlspecialchars($url) . '"',
					$parameters['finalTag']
				);
				$parameters['finalTagParts']['url'] = $url;
				$pObj->lastTypoLinkUrl = $url;

				$this->logger->debug(
					sprintf(
						'Post-processed encoded url "%s" to "%s"',
						$testUrl,
						$url
					)
				);
			}
		}
	}

	/**
	 * Adds remaining parameters to the generated URL. Note: parameters that
	 * are ignored by the 'cache/ignoredGetParametersRegExp' configuration option
	 * are not considered here!
	 *
	 * @return void
	 */
	protected function addRemainingUrlParameters() {
		$urlParameters = $this->urlParameters;
		unset($urlParameters['id']);
		if (count($urlParameters) == 1 && isset($urlParameters['cHash'])) {
			unset($urlParameters['cHash']);
		} elseif (count($urlParameters) > 0) {
			$this->encodedUrl .= '?' . trim(GeneralUtility::implodeArrayForUrl('', $urlParameters, '', false, true), '&');
		}
	}

	/**
	 * Adds an entry to the path cache.
	 *
	 * @param string $pagePath
	 * @return void
	 */
	protected function addToPathCache($pagePath) {
		$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
		/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $cacheEntry */
		$cacheEntry->setExpiration(0);
		$cacheEntry->setLanguageId($this->sysLanguageUid);
		$cacheEntry->setRootPageId($this->rootPageId);
		$cacheEntry->setMountPoint(isset($this->originalUrlParameters['MP']) ? $this->originalUrlParameters['MP'] : '');
		$cacheEntry->setPageId($this->urlParameters['id']);
		$cacheEntry->setPagePath($pagePath);
		$this->cache->putPathToCache($cacheEntry);
	}

	/**
	 * Appends a string to $this->encodedUrl properly handling slashes in between.
	 *
	 * @param string $stringToAppend
	 * @param bool $addSlash
	 * @return void
	 */
	protected function appendToEncodedUrl($stringToAppend, $addSlash = TRUE) {
		if ($stringToAppend !== '') {
			$this->encodedUrl = ($this->encodedUrl ? rtrim($this->encodedUrl, '/') . '/' : '') . $stringToAppend;
			if ($addSlash) {
				$this->encodedUrl .= '/';
			}
		}
	}

	/**
	 * Early hook for the encoder.
	 *
	 * @param array $encoderParameters
	 */
	protected function callEarlyHook(&$encoderParameters) {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['encodeSpURL_earlyHook'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['encodeSpURL_earlyHook'] as $userFunc) {
				$hookParams = array(
					'pObj' => $this,
					'params' => &$encoderParameters,
				);
				GeneralUtility::callUserFunction($userFunc, $hookParams, $this);
			}
		}
	}

	/**
	 * Calls user-defined hooks after encoding
	 */
	protected function callPostEncodeHooks() {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['encodeSpURL_postProc'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['encodeSpURL_postProc'] as $userFunc) {
				$hookParams = array(
					'pObj' => $this,
					'params' => $this->encoderParameters,
					'URL' => &$this->encodedUrl,
				);
				GeneralUtility::callUserFunction($userFunc, $hookParams, $this);
			}
		}
	}

	/**
	 * Checks if the URL can be cached. This function may prevent RealURL cache
	 * pollution with Solr or Indexed search URLs. Also some doktypes are ignored
	 * for the cache.
	 *
	 * @param string $url
	 * @return bool
	 */
	protected function canCacheUrl($url) {
		$bannedUrlsRegExp = $this->configuration->get('cache/banUrlsRegExp');

		$result = (!$bannedUrlsRegExp || !preg_match($bannedUrlsRegExp, $url));

		if ($result) {
			// Check page type: do not cache separators
			$pageRecord = $this->pageRepository->getPage($this->urlParameters['id']);
			if (is_array($pageRecord) && ($pageRecord['doktype'] == PageRepository::DOKTYPE_SPACER || $pageRecord['doktype'] == PageRepository::DOKTYPE_RECYCLER)) {
				$result = false;
			}
		}
		else {
			$this->logger->debug(
				sprintf(
					'URL "%s" will not be cached',
					$url
				)
			);
		}

		return $result;
	}

	/**
	 * Checks if RealURL can encode URLs.
	 *
	 * @return bool
	 */
	protected function canEncoderExecute() {
		return $this->isRealURLEnabled() &&
			!$this->isSimulateStaticEnabled() &&
			!$this->isInWorkspace() &&
			$this->isTypo3Url() &&
			$this->isProperTsfe()
		;
	}

	/**
	 * Checks if all segments are empty and makes the empty array in such case.
	 *
	 * @param array $segments
	 * @return void
	 */
	protected function checkForAllEmptySegments(&$segments) {
		// If all segments are empty, do not set them. No, array_filter() is not a better solution!
		if (count($segments) > 0) {
			$allSegmentsAreEmpty = TRUE;
			foreach ($segments as $segment) {
				if ($segment) {
					$allSegmentsAreEmpty = FALSE;
					break;
				}
			}
			if ($allSegmentsAreEmpty) {
				$segments = array();
			}
		}
	}

	/**
	 * Cleans up the alias
	 *
	 * @param array $configuration Configuration array
	 * @param string $newAliasValue Alias value to clean up
	 * @return string
	 */
	public function cleanUpAlias(array $configuration, $newAliasValue) {
		$processedTitle = $this->utility->convertToSafeString($newAliasValue, $this->separatorCharacter);

		if ($configuration['useUniqueCache_conf']['encodeTitle_userProc']) {
			$encodingConfiguration = array('strtolower' => $configuration['useUniqueCache_conf']['strtolower'], 'spaceCharacter' => $configuration['useUniqueCache_conf']['spaceCharacter']);
			$parameters = array(
				'pObj' => $this,
				'title' => $newAliasValue,
				'processedTitle' => $processedTitle,
				'encodingConfiguration' => $encodingConfiguration
			);
			$processedTitle = GeneralUtility::callUserFunction($configuration['useUniqueCache_conf']['encodeTitle_userProc'], $parameters, $this);
		}

		return $processedTitle;
	}

	/**
	 * Converts value to the alias
	 *
	 * @param string $getVarValue
	 * @param array $configuration 'lookUpTable' configuration
	 * @return string
	 */
	protected function createAliasForValue($getVarValue, array $configuration) {
		$result = $getVarValue;

		// Define the language for the alias
		$languageUrlParameter = $configuration['languageGetVar'] ?: 'L';
		$languageUid = isset($this->originalUrlParameters[$languageUrlParameter]) ? (int)$this->originalUrlParameters[$languageUrlParameter] : 0;
		if (GeneralUtility::inList($configuration['languageExceptionUids'], $languageUid)) {
			$languageUid = 0;
		}

		// First, test if there is an entry in cache for the id
		if (!$configuration['useUniqueCache'] || !($result = $this->getFromAliasCache($configuration, $getVarValue, $languageUid))) {
			$languageEnabled = FALSE;
			$fieldList = array();
			if ($configuration['table'] === 'pages') {
				$fieldList[] = 'uid';
				$languageEnabled = TRUE;
			}
			elseif ($configuration['transOrigPointerField'] && $configuration['languageField']) {
				$fieldList[] = 'uid';
				$fieldList[] = $configuration['transOrigPointerField'];
				$fieldList[] = $configuration['languageField'];
				$languageEnabled = TRUE;
			}

			$fieldList[] = $configuration['alias_field'];
			$row = $this->databaseConnection->exec_SELECTgetSingleRow(implode(',', $fieldList), $configuration['table'],
						$configuration['id_field'] . '=' . $this->databaseConnection->fullQuoteStr($getVarValue, $configuration['table']) .
						' ' . $configuration['addWhereClause']);
			if (is_array($row)) {
				// Looking for localized version
				if ($languageEnabled && $languageUid !== 0) {
					if ($configuration['table'] === 'pages') {
						// Note: can't use $this->pageRepository->getPageOverlay() here because 'alias_field' can be an expression
						$localizedRow = $this->databaseConnection->exec_SELECTgetSingleRow($configuration['alias_field'], 'pages_language_overlay',
							'pid=' . (int)$row['uid'] . ' AND sys_language_uid=' . $languageUid .
							(isset($configuration['addWhereClause']) ? $configuration['addWhereClause'] : '')
						);
					}
					else {
						// Note: can't use $this->pageRepository->getRecordOverlay() here because 'alias_field' can be an expression
						$localizedRow = $this->databaseConnection->exec_SELECTgetSingleRow($configuration['alias_field'], $configuration['table'],
							$configuration['transOrigPointerField'] . '=' . (int)$row['uid'] .
							' AND ' . $configuration['languageField'] . '=' . $languageUid .
							(isset($configuration['addWhereClause']) ? $configuration['addWhereClause'] : '')
						);
					}
					if (is_array($localizedRow)) {
						$row = $localizedRow;
					}
				}

				$maxAliasLengthLength = isset($configuration['maxLength']) ? (int)$configuration['maxLength'] : self::MAX_ALIAS_LENGTH;
				$aliasValue = $this->tsfe->csConvObj->substr('utf-8', $row[$configuration['alias_field']], 0, $maxAliasLengthLength);

				# Do not allow aliases to be empty (see issue #1)
				if (empty($aliasValue)) {
					$aliasValue = md5($configuration['table'] . '-' . $row[$configuration['id_field']] . '-' . $languageUid);
				}

				if ($configuration['useUniqueCache']) { // If cache is to be used, store the alias in the cache:
					$result = $this->storeInAliasCache($configuration, $aliasValue, $getVarValue, $languageUid);
				} else { // If no cache for alias, then just return whatever value is appropriate:
					$result = $aliasValue;
				}
			}
		}

		return is_null($result) ? $getVarValue : $result;
	}

	/**
	 * Creates a unique alias.
	 *
	 * @param array $configuration
	 * @param $newAliasValue
	 * @param $idValue
	 * @return string
	 */
	protected function createUniqueAlias(array $configuration, $newAliasValue, $idValue) {
		$uniqueAlias = '';
		$counter = 0;
		$maxTry = 100;
		$testNewAliasValue = $newAliasValue;
		while ($counter < $maxTry) {
			// If the test-alias did NOT exist, it must be unique and we break out
			$foundId = $this->getFromAliasCacheByAliasValue($configuration, $testNewAliasValue);
			if (!$foundId || $foundId == $idValue) {
				$uniqueAlias = $testNewAliasValue;
				break;
			}
			$counter++;
			$testNewAliasValue = $newAliasValue . '-' . $counter;
		}

		return $uniqueAlias;
	}

	/**
	 * Creates a path part of the URL.
	 *
	 * @return void
	 */
	protected function createPathComponent() {
		if (!$this->createPathComponentThroughOverride()) {
			$this->createPathComponentUsingRootline();
		}
	}

	/**
	 * Checks if tx_realurl_pathoverride is set and goes the easy way.
	 *
	 * @return bool
	 */
	protected function createPathComponentThroughOverride() {
		$result = false;

		// Can't use $this->pageRepository->getPage() here because it does
		// language overlay to TSFE's sys_language_uid automatically.
		// We do not want this because we may need to encode to a different language
		$page = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'pages',
			'uid=' . (int)$this->urlParameters['id']
		);
		$languageExceptionUids = (string)$this->configuration->get('pagePath/languageExceptionUids');
		if ($this->sysLanguageUid > 0 && (empty($languageExceptionUids) || !GeneralUtility::inList($languageExceptionUids, $this->sysLanguageUid))) {
			$overlay = $this->pageRepository->getPageOverlay($page, $this->sysLanguageUid);
			if (is_array($overlay)) {
				$page = $overlay;
				unset($overlay);
			}
		}
		if ($page['tx_realurl_pathoverride'] && $page['tx_realurl_pathsegment'] !== '') {
			$path = trim($page['tx_realurl_pathsegment'], '/');
			$pathSegments = explode('/', $path);
			array_walk($pathSegments, function(&$segment) {
				$segment = rawurlencode($this->utility->convertToSafeString($segment, $this->separatorCharacter));
			});
			$path = implode('/', $pathSegments);
			unset($pathSegments);
			$this->appendToEncodedUrl($path);
			// Mount points do not work with path override. Having them will
			// create duplicate path entries but we have to live with this to
			// avoid further cache management complications. If we ignore
			// mount point information here, we will have to do something
			// about it in encodePathComponents() when we fetch from the cache.
			// It is easier to have duplicate entries here (one with MP and
			// another without it). It does not really matter.
			if ($page['doktype'] != PageRepository::DOKTYPE_SPACER && $page['doktype'] != PageRepository::DOKTYPE_RECYCLER) {
				$this->addToPathCache($path);
			}
			$result = true;

			$this->logger->debug(
				sprintf(
					'Created page path "%s" through override for page %d',
					$path,
					(int)$this->urlParameters['id']
				)
			);
		}

		return $result;
	}

	/**
	 * Creates a path part of the URL.
	 *
	 * @return void
	 */
	protected function createPathComponentUsingRootline() {
		$this->logger->debug('Starting path generation');

		$mountPointParameter = '';
		if (isset($this->urlParameters['MP'])) {
			$mountPointParameter = $this->urlParameters['MP'];
			unset($this->urlParameters['MP']);
		}
		$rootLineUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Utility\\RootlineUtility',
			$this->urlParameters['id'], $mountPointParameter, $this->pageRepository
		);
		/** @var \TYPO3\CMS\Core\Utility\RootlineUtility $rootLineUtility */
		$rootLine = $rootLineUtility->get();

		// Skip from the root of the tree to the first level of pages
		while (count($rootLine) !== 0) {
			$page = array_pop($rootLine);
			if ($page['uid'] == $this->rootPageId) {
				break;
			}
		}

		$languageExceptionUids = (string)$this->configuration->get('pagePath/languageExceptionUids');
		$enableLanguageOverlay = ((int)$this->originalUrlParameters['L'] > 0) && (empty($languageExceptionUids) || !GeneralUtility::inList($languageExceptionUids, $this->sysLanguageUid));

		$components = array();
		$reversedRootLine = array_reverse($rootLine);
		$rootLineMax = count($reversedRootLine) - 1;
		for ($current = 0; $current <= $rootLineMax; $current++) {
			$page = $reversedRootLine[$current];
			// Skip if this page is excluded
			if ($page['tx_realurl_exclude'] && $current !== $rootLineMax) {
				$this->logger->debug(
					sprintf(
						'Page %d is excluded from realurl',
						(int)$page['uid']
					)
				);
				continue;
			}
			if ($enableLanguageOverlay) {
				$overlay = $this->pageRepository->getPageOverlay($page, (int)$this->originalUrlParameters['L']);
				if (is_array($overlay)) {
					$page = $overlay;
					unset($overlay);
				}
			}

			// if path override is set, use path segment also for all subpages to shorten the url and throw away all segments found so far
			if ($page['tx_realurl_pathoverride'] && $page['tx_realurl_pathsegment'] !== '') {
				$this->logger->debug(
					sprintf(
						'Path override detected for page %d',
						(int)$page['uid']
					)
				);
				$segment = trim($page['tx_realurl_pathsegment'], '/');
				$segments = explode('/', $segment);
				array_walk($segments, function(&$segment) {
					$segment = rawurlencode($this->utility->convertToSafeString($segment, $this->separatorCharacter));
				});
				// Technically we could do with `$components = $segments` but it fills better to have overriden string here
				$segment = implode('/', $segments);
				unset($segments);
				$components = array($segment);
				continue;
			}

			foreach (self::$pageTitleFields as $field) {
				if (isset($page[$field]) && $page[$field] !== '') {
					$segment = $this->utility->convertToSafeString($page[$field], $this->separatorCharacter);
					if ($segment === '') {
						$segment = $this->emptySegmentValue;
					}
					$segment = rawurlencode($segment);
					$components[] = $segment;
					$this->logger->debug(
						sprintf(
							'Found path segment "%s" using field "%s"',
							$segment,
							$field
						)
					);
					continue 2;
				}
			}
		}

		if (count($components) > 0) {
			$generatedPath = implode('/', $components);

			foreach ($components as $segment) {
				$this->appendToEncodedUrl($segment);
			}
			if ($reversedRootLine[$rootLineMax]['doktype'] != PageRepository::DOKTYPE_SPACER && $reversedRootLine[$rootLineMax]['doktype'] != PageRepository::DOKTYPE_RECYCLER) {
				$this->addToPathCache($generatedPath);
			}

			$this->logger->debug(
				sprintf(
					'Generated path: "%s"',
					$generatedPath
				)
			);
		}
		$this->logger->debug('Finished path generation');
	}

	/**
	 * Encodes fixed postVars.
	 *
	 * @return void
	 */
	protected function encodeFixedPostVars() {
		$configuration = (array)$this->configuration->get('fixedPostVars');
		$postVarSetConfiguration = $this->getConfigurationForPostVars($configuration, $this->urlParameters['id']);

		if (count($postVarSetConfiguration) > 0) {
			$segments = $this->encodeUrlParameterBlock($postVarSetConfiguration);
			if (count($segments) > 0) {
				$this->appendToEncodedUrl(implode('/', $segments));
			}
		}
	}

	/**
	 * Encodes the path to the page.
	 *
	 * @return void
	 */
	protected function encodePathComponents() {
		$this->fixPageId();
		$cacheEntry = $this->cache->getPathFromCacheByPageId($this->rootPageId,
			$this->sysLanguageUid,
			$this->urlParameters['id'],
			isset($this->urlParameters['MP']) ? $this->urlParameters['MP'] : ''
		);
		if ($cacheEntry) {
			$this->appendToEncodedUrl($cacheEntry->getPagePath());
			if (isset($this->urlParameters['MP']) && $cacheEntry->getMountPoint() === $this->urlParameters['MP']) {
				unset($this->urlParameters['MP']);
			}
		} else {
			$this->createPathComponent();
		}
	}

	/**
	 * Encodes 'preVars' into URL segments.
	 *
	 * @return void
	 */
	protected function encodePreVars() {
		$preVars = (array)$this->configuration->get('preVars');
		if (count($preVars) > 0) {
			$segments = $this->encodeUrlParameterBlock($preVars);
			if (count($segments) > 0) {
				$this->appendToEncodedUrl(implode('/', $segments));
			}
		}
	}

	/**
	 * Encodes 'postVarSets' into URL segments.
	 *
	 * @return void
	 */
	protected function encodePostVarSets() {
		// There is at least an 'id' parameter
		if (count($this->urlParameters) > 1) {
			$configuration = (array)$this->configuration->get('postVarSets');
			$postVarSetConfigurations = $this->getConfigurationForPostVars($configuration, $this->urlParameters['id']);

			foreach ($postVarSetConfigurations as $postVar => $postVarSetConfiguration) {
				if (is_array($postVarSetConfiguration)) {
					// Technically it can be a string (for decoding purposes) but makes no sense for encoding
					// And decoder does not support it too (see UrlDecoder::decodePostVarSets)
					$segments = $this->encodeUrlParameterBlock($postVarSetConfiguration);
					if (count($segments) > 0) {
						array_unshift($segments, $postVar);
						$this->appendToEncodedUrl(implode('/', $segments));
					}
				}
			}
		}
	}

	/**
	 * Encodes a single variable for xxxVars.
	 *
	 * @param array $configuration
	 * @param string $previousValue
	 * @param array $segments
	 */
	protected function encodeSingleVariable(array $configuration, &$previousValue, array &$segments) {
		static $varProcessingFunctions = array(
			'encodeUrlParameterBlockUsingValueMap',
			'encodeUrlParameterBlockUsingNoMatch',
			'encodeUrlParameterBlockUsingUserFunc',
			'encodeUrlParameterBlockUsingLookupTable',
			'encodeUrlParameterBlockUsingValueDefault',
			// Always the last one!
			'encodeUrlParameterBlockUseAsIs',
		);

		if (isset($configuration['GETvar'])) {
			$getVarName = $configuration['GETvar'];
			$getVarValue = isset($this->urlParameters[$getVarName]) ? $this->urlParameters[$getVarName] : '';

			if (!isset($configuration['cond']) || $this->checkLegacyCondition($configuration['cond'], $previousValue)) {

				// TODO Possible hook here before any other function? Pass name, value, segments and config

				foreach ($varProcessingFunctions as $varProcessingFunction) {
					if ($this->$varProcessingFunction($getVarName, $getVarValue, $configuration, $segments, $previousValue)) {
						// Unset to prevent further processing
						unset($this->urlParameters[$getVarName]);
						break;
					}
				}
			}
		}
		else {
			// TODO Log an error here: configuration is bad!
		}
	}

	/**
	 * Encodes pre- or postVars according to the given configuration.
	 *
	 * @param array $configurationArray
	 * @return array
	 */
	protected function encodeUrlParameterBlock(array $configurationArray) {
		$segments = array();

		if ($this->hasUrlParameters($configurationArray)) {
			$previousValue = '';
			foreach ($configurationArray as $configuration) {
				if (is_array($configuration)) {
					$this->encodeSingleVariable($configuration, $previousValue, $segments);
				}
			}
		}

		$this->checkForAllEmptySegments($segments);
		$this->fixEmptySegments($segments);

		return $segments;
	}

	/**
	 * Just sets the value to the segment as is.
	 *
	 * @param string $getVarName
	 * @param string $getVarValue
	 * @param array $configuration
	 * @param array $segments
	 * @param string $previousValue
	 * @return bool
	 */
	protected function encodeUrlParameterBlockUseAsIs(/** @noinspection PhpUnusedParameterInspection */ $getVarName, $getVarValue, array $configuration, array &$segments, &$previousValue) {
		$previousValue = $getVarValue;
		$segments[] = rawurlencode($getVarValue);
		return TRUE;
	}

	/**
	 * Uses lookUpMap to set the segment.
	 *
	 * @param string $getVarName
	 * @param string $getVarValue
	 * @param array $configuration
	 * @param array $segments
	 * @param string $previousValue
	 * @return bool
	 */
	protected function encodeUrlParameterBlockUsingLookupTable(/** @noinspection PhpUnusedParameterInspection */ $getVarName, $getVarValue, array $configuration, array &$segments, &$previousValue) {
		$result = FALSE;

		if (isset($configuration['lookUpTable'])) {
			$previousValue = $getVarValue;
			$segments[] = rawurlencode($this->createAliasForValue($getVarValue, $configuration['lookUpTable']));
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Uses 'noMatch' options to set the segment.
	 *
	 * @param string $getVarName
	 * @param string $getVarValue
	 * @param array $configuration
	 * @param array $segments
	 * @param string $previousValue
	 * @return bool
	 */
	protected function encodeUrlParameterBlockUsingNoMatch(/** @noinspection PhpUnusedParameterInspection */ $getVarName, $getVarValue, array $configuration, array &$segments, &$previousValue) {
		$result = FALSE;

		if (isset($configuration['noMatch'])) {
			if ($configuration['noMatch'] === 'bypass') {
				$result = TRUE;
			} elseif ($configuration['noMatch'] === 'null') {
				$previousValue = '';
				$segments[] = '';
				$result = TRUE;
			}
		}

		return $result;
	}

	/**
	 * Calls the userFunc for the value to get the segment.
	 *
	 * @param string $getVarName
	 * @param string $getVarValue
	 * @param array $configuration
	 * @param array $segments
	 * @param string $previousValue
	 * @return bool
	 */
	protected function encodeUrlParameterBlockUsingUserFunc(/** @noinspection PhpUnusedParameterInspection */ $getVarName, $getVarValue, array $configuration, array &$segments, &$previousValue) {
		$result = FALSE;

		if (isset($configuration['userFunc'])) {
			$previousValue = $getVarValue;
			$userFuncParameters = array(
				'pObj' => $this,
				'value' => $getVarValue,
				'decodeAlias' => false,
				'pathParts' => &$segments,
				'setup' => $configuration,
				'sysLanguageUid' => $this->sysLanguageUid,
			);
			$getVarValue = GeneralUtility::callUserFunction($configuration['userFunc'], $userFuncParameters, $this);
			if (is_numeric($getVarValue) || is_string($getVarValue)) {
				$segments[] = rawurlencode($getVarValue);
				$result = TRUE;
			}
		}

		return $result;
	}

	/**
	 * Just sets the default value to the segment.
	 *
	 * @param string $getVarName
	 * @param string $getVarValue
	 * @param array $configuration
	 * @param array $segments
	 * @param string $previousValue
	 * @return bool
	 */
	protected function encodeUrlParameterBlockUsingValueDefault(/** @noinspection PhpUnusedParameterInspection */ $getVarName, $getVarValue, array $configuration, array &$segments, &$previousValue) {
		$result = FALSE;

		if (isset($configuration['valueDefault'])) {
			$previousValue = (string)$configuration['valueDefault'];
			$segments[] = rawurlencode((string)$configuration['valueDefault']);
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Uses the value map to determine the segment value.
	 *
	 * @param string $getVarName
	 * @param string $getVarValue
	 * @param array $configuration
	 * @param array $segments
	 * @param string $previousValue
	 * @return bool
	 */
	protected function encodeUrlParameterBlockUsingValueMap(/** @noinspection PhpUnusedParameterInspection */ $getVarName, $getVarValue, array $configuration, array &$segments, &$previousValue) {
		$result = FALSE;

		if (isset($configuration['valueMap']) && is_array($configuration['valueMap'])) {
			$segmentValue = array_search($getVarValue, $configuration['valueMap']);
			if ($segmentValue !== FALSE) {
				$previousValue = $getVarValue;
				$segments[] = rawurlencode((string)$segmentValue);
				$result = TRUE;
			}
		}

		return $result;
	}

	/**
	 * Encodes the URL.
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function executeEncoder() {
		$this->parseUrlParameters();

		// Initialize needs parsed URL parameters!
		$this->initialize();

		$this->setLanguage();
		$this->removeIgnoredUrlParameters();
		$this->initializeUrlPrepend();
		if (!$this->fetchFromUrlCache()) {
			$this->encodePreVars();
			try {
				$this->encodePathComponents();
			}
			catch (\Exception $exception) {
				if ($exception->getCode() === 1343589451) {
					// Rootline failure: "Could not fetch page data for uid X"
					// Reset and quit. See https://github.com/dmitryd/typo3-realurl/issues/200
					$this->encodedUrl = $this->urlToEncode;
					return;
				}
				throw $exception;
			}
			$this->encodeFixedPostVars();
			$this->encodePostVarSets();
			$this->handleFileName();

			$this->trimMultipleSlashes();
			$this->addRemainingUrlParameters();

			if ($this->encodedUrl === '') {
				$emptyUrlReturnValue = $this->configuration->get('init/emptyUrlReturnValue') ?: '/';
				$this->encodedUrl = $emptyUrlReturnValue;
			}
			$this->storeInUrlCache();
		}
		$this->reapplyAbsRefPrefix();
		$this->callPostEncodeHooks();
		$this->encodedUrl = $this->restoreIgnoredUrlParametersInURL($this->encodedUrl);
		$this->prepareUrlPrepend();
	}

	/**
	 * Attempts to fetch the speaking URL from the url cache.
	 *
	 * @return bool
	 */
	protected function fetchFromUrlCache() {
		$result = FALSE;

		$cacheEntry = $this->cache->getUrlFromCacheByOriginalUrl($this->rootPageId, $this->originalUrl);
		if ($cacheEntry && $cacheEntry->getExpiration() === 0) {
			$this->encodedUrl = $cacheEntry->getSpeakingUrl();
			$result = TRUE;

			$this->logger->debug(
				sprintf(
					'Found speaking url "%s" for original url "%s" and root page id %d in cache',
					$this->encodedUrl,
					$this->originalUrl,
					$this->rootPageId
				)
			);
		}

		return $result;
	}

	/**
	 * Changes empty segments to the value of $this->emptySegmentValue.
	 *
	 * @param array $segments
	 * @retun void
	 */
	protected function fixEmptySegments(array &$segments) {
		if ($this->emptySegmentValue !== '') {
			foreach ($segments as $key => $segment) {
				if ($segment === '') {
					$segments[$key] = $this->emptySegmentValue;
				}
			}
		}
	}

	/**
	 * Fixes page id if it is not a direct numeric page id.
	 */
	protected function fixPageId() {
		if (!MathUtility::canBeInterpretedAsInteger($this->urlParameters['id'])) {
			// Seems to be an alias
			$alias = $this->urlParameters['id'];
			$this->urlParameters['id'] = $this->pageRepository->getPageIdFromAlias($alias);
			if ($this->urlParameters['id'] === 0) {
				throw new \Exception(sprintf('Page with alias "%s" does not exist.', $alias), 1457183797);
			}
		}
	}

	/**
	 * Obtains the value from the alias cache. If a specific alias is requested,
	 * this function may un-expire the alias if it is marked as expired.
	 *
	 * @param array $configuration
	 * @param string $getVarValue
	 * @param int $languageUid
	 * @param string $onlyThisAlias
	 * @return string|null
	 */
	protected function getFromAliasCache(array $configuration, $getVarValue, $languageUid, $onlyThisAlias = '') {
		$result = NULL;
		// We use 'expire=0' condition only if no specific alias is requested. If a specific alias
		// is requested, we also can fetched expired aliases and un-expire them. This prevents
		// multiple identical expired alias records.
		$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_uniqalias',
				'value_id=' . $this->databaseConnection->fullQuoteStr($getVarValue, 'tx_realurl_uniqalias') .
				' AND field_alias=' . $this->databaseConnection->fullQuoteStr($configuration['alias_field'], 'tx_realurl_uniqalias') .
				' AND field_id=' . $this->databaseConnection->fullQuoteStr($configuration['id_field'], 'tx_realurl_uniqalias') .
				' AND tablename=' . $this->databaseConnection->fullQuoteStr($configuration['table'], 'tx_realurl_uniqalias') .
				' AND lang=' . intval($languageUid) .
				($onlyThisAlias ? ' AND value_alias=' . $this->databaseConnection->fullQuoteStr($onlyThisAlias, 'tx_realurl_uniqalias') : ' AND expire=0'),
			'', 'expire'
		);
		if (is_array($row)) {
			$this->usedAliases[] = $row['uid'];
			$result = $row['value_alias'];

			if ($onlyThisAlias && $row['expire'] > 0) {
				// We use this alias and need to un-expire it
				$this->databaseConnection->exec_UPDATEquery('tx_realurl_uniqalias', 'uid=' . $row['uid'], array('expire' => 0));
			}
		}

		return $result;
	}

	/**
	 * Appends file name and suffix if necessary.
	 *
	 * @return void
	 */
	protected function handleFileName() {
		if (!$this->handleFileNameUsingGetVars()) {
			$this->handleFileNameSetDefaultSuffix();
		}
	}

	/**
	 * Sets the default suffix to the URL if configured so.
	 *
	 * @return void
	 */
	protected function handleFileNameSetDefaultSuffix() {
		if ($this->encodedUrl) {
			$suffixValue = $this->configuration->get('fileName/defaultToHTMLsuffixOnPrev');
			if ($suffixValue) {
				if (!is_string($suffixValue) || strpos($suffixValue, '.') === FALSE) {
					$suffixValue = '.html';
				}
				if ($this->encodedUrl !== '') {
					$this->encodedUrl = rtrim($this->encodedUrl, '/');
				}
				$this->encodedUrl .= $suffixValue;
			}
		}
	}

	/**
	 * Checks if the file name like 'rss.xml' should be produced according to _GET vars.
	 *
	 * @return bool
	 */
	protected function handleFileNameUsingGetVars() {
		$result = FALSE;
		$fileNameConfigurations = (array)$this->configuration->get('fileName/index');
		foreach ($fileNameConfigurations as $fileName => $fileNameConfiguration) {
			if (strpos($fileName, '.') !== FALSE && is_array($fileNameConfiguration) && is_array($fileNameConfiguration['keyValues'])) {
				$useThisConfiguration = TRUE;
				$variablesToRemove = array();
				foreach ($fileNameConfiguration['keyValues'] as $getVarName => $getVarValue) {
					if (!isset($this->urlParameters[$getVarName]) || (string)$this->urlParameters[$getVarName] !== (string)$getVarValue) {
						$useThisConfiguration = FALSE;
						break;
					}
					$variablesToRemove[$getVarName] = '';
				}

				if ($useThisConfiguration) {

					if ($fileName{0} === '.') {
						if ($this->encodedUrl === '') {
							$this->encodedUrl = 'index';
						}
						else {
							$this->encodedUrl = rtrim($this->encodedUrl, '/');
						}
					}
					$this->encodedUrl .= $fileName;
					$this->urlParameters = array_diff_key($this->urlParameters, $variablesToRemove);
					$result = TRUE;
					break;
				}
			}
		}

		return $result;
	}

	/**
	 * Checks if any GETvar from the parameter block is present in $this->urlParameters.
	 *
	 * @param array $configurationArray
	 * @return bool
	 */
	protected function hasUrlParameters(array $configurationArray) {
		$result = FALSE;

		foreach ($configurationArray as $configuration) {
			if (is_array($configuration) && isset($configuration['GETvar']) && isset($this->urlParameters[$configuration['GETvar']])) {
				$result = TRUE;
				break;
			}
		}

		return $result;
	}

	/**
	 * Initializes configuration reader.
	 */
	protected function initializeConfiguration() {
		$this->configuration = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Configuration\\ConfigurationReader', ConfigurationReader::MODE_ENCODE, $this->urlParameters);
		$this->configuration->validate();
	}

	/**
	 * Checks if we are linking across domains. We check if $this->rootPageId is
	 * in $this->tsfe->rootLine. If root page id is not in TSFE's rootline, we
	 * are encoding to another domain.
	 *
	 * @return bool
	 */
	protected function isLinkingAcrossDomains() {
		$result = true;

		foreach (array_reverse($this->tsfe->rootLine) as $page) {
			if ($page['uid'] == $this->rootPageId) {
				$result = false;
				break;
			}
			if ($page['php_tree_stop'] || $page['is_siteroot']) {
				// Pages beyond this one cannot be root pages (we do not support nested domains!)
				break;
			}
		}

		return $result;
	}

	/**
	 * Checks if TSFE is initialized correctly.
	 *
	 * @return bool
	 */
	protected function isProperTsfe() {
		return ($this->tsfe instanceof TypoScriptFrontendController) &&
			($this->tsfe->id > 0) &&
			(is_array($this->tsfe->config['config']))
		;
	}

	/**
	 * Checks if RealURL is enabled.
	 *
	 * @return bool
	 */
	protected function isRealURLEnabled() {
		return (bool)$this->tsfe->config['config']['tx_realurl_enable'] && (
			!isset($this->tsfe->register['tx_realurl_enable']) ||
			(bool)$this->tsfe->register['tx_realurl_enable']
		);
	}

	/**
	 * Checks if simulatestatic is enabled.
	 *
	 * @return bool
	 */
	protected function isSimulateStaticEnabled() {
		return isset($this->tsfe->config['config']['simulateStaticDocuments']) && (bool)$this->tsfe->config['config']['simulateStaticDocuments'] ||
			isset($GLOBALS['TYPO3_CONF_VARS']['FE']['simulateStaticDocuments']) && (bool)$GLOBALS['TYPO3_CONF_VARS']['FE']['simulateStaticDocuments']
		;
	}

	/**
	 * Checks if a TYPO3 URL is going to be encoded.
	 *
	 * @return bool
	 */
	protected function isTypo3Url() {
		// cannot use getAbsRefPrefix here because a TYPO3 generated URL
		// always uses the absRefPrefix in TSFE whereas for the URL generation itself
		// a proper absRefPrefix is always required by using getAbsRefPrefix
		$prefix = $this->tsfe->absRefPrefix . 'index.php';
		return substr($this->urlToEncode, 0, strlen($prefix)) === $prefix;
	}

	/**
	 * Parses query string to a set of key/value inside $this->urlParameters.
	 *
	 * @return void
	 */
	protected function parseUrlParameters() {
		$urlParts = parse_url($this->urlToEncode);
		$this->urlParameters = array();
		if ($urlParts['query']) {
			// Cannot use parse_str() here because we do not need deep arrays here.
			$parts = GeneralUtility::trimExplode('&', $urlParts['query']);
			foreach ($parts as $part) {
				list($parameter, $value) = explode('=', $part);
				// Remember: urldecode(), not rawurldecode()!
				$this->urlParameters[urldecode($parameter)] = urldecode($value);
			}
		}
		$this->originalUrlParameters = $this->urlParameters;

		$sortedUrlParameters = $this->urlParameters;
		$this->sortArrayDeep($sortedUrlParameters);

		if (isset($sortedUrlParameters['cHash'])) {
			$cacheHashCalculator = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\CacheHashCalculator');
			$cHashParameters = $cacheHashCalculator->getRelevantParameters(GeneralUtility::implodeArrayForUrl('', $sortedUrlParameters));
			if (count($cHashParameters) === 0) {
				unset($sortedUrlParameters['cHash']);
				unset($this->urlParameters['cHash']);
				unset($this->originalUrlParameters['cHash']);

				$this->logger->warning(
					sprintf(
						'URL "%s" contains cHash but there are no relevant parameters for cHash (did you use addQueryString?). This will cause error during decoding. cHash was removed.',
						$this->urlToEncode
					)
				);
			}
		}

		$this->originalUrl = $this->createQueryStringFromParameters($sortedUrlParameters);
	}

	/**
	 * Prepares the URL to use with _DOMAINS configuration.
	 *
	 * @return void
	 */
	protected function prepareUrlPrepend() {
		if ($this->urlPrepend !== '') {
			self::$urlPrependRegister[$this->encodedUrl] = $this->urlPrepend;
		}
	}

	/**
	 * Reapplies absRefPrefix if necessary.
	 *
	 * If we have urlPrepend, we skip absRefPrefix.
	 * Also it should not be applied if we are linking across domains.
	 *
	 * @return void
	 */
	protected function reapplyAbsRefPrefix() {
		$absRefPrefix = $this->getAbsRefPrefix();
		if ($absRefPrefix && $this->urlPrepend === '' && !$this->isLinkingAcrossDomains()) {
			$reapplyAbsRefPrefix = $this->configuration->get('init/reapplyAbsRefPrefix');
			if ($reapplyAbsRefPrefix === '' || $reapplyAbsRefPrefix) {
				// Prevent // in case of absRefPrefix ending with / and emptyUrlReturnValue=/
				if (substr($absRefPrefix, -1, 1) == '/' && substr($this->encodedUrl, 0, 1) == '/') {
					$this->encodedUrl = (string)substr($this->encodedUrl, 1);
				}
				$this->encodedUrl = $absRefPrefix . $this->encodedUrl;
			}
		}
		if (empty($absRefPrefix)) {
			$this->logger->warning('config.absRefPrefix is not set! Please, check your TypoScript configuration!');
		}
	}

	/**
	 * Removes ignored parameters from various members.
	 */
	protected function removeIgnoredUrlParameters() {
		$this->urlParameters = $this->removeIgnoredUrlParametersFromArray($this->urlParameters);
		$this->originalUrl = $this->removeIgnoredParametersFromQueryString($this->originalUrl);
	}

	/**
	 * Removes ignored URL parameters from the parameter list.
	 *
	 * @param array $urlParameters
	 * @return array
	 */
	protected function removeIgnoredUrlParametersFromArray(array $urlParameters) {
		$ignoredParametersRegExp = $this->configuration->get('cache/ignoredGetParametersRegExp');
		if ($ignoredParametersRegExp) {
			foreach ($urlParameters as $parameterName => $parameterValue) {
				if (preg_match($ignoredParametersRegExp, $parameterName)) {
					unset($urlParameters[$parameterName]);
				}
			}
		}

		return $urlParameters;
	}

	/**
	 * Checks if we should prpend URL according to _DOMAINS configuration.
	 *
	 * @return void
	 */
	protected function initializeUrlPrepend() {
		$configuration = $this->configuration->get('domains');
		if (is_array($configuration)) {
			if (isset($configuration['GETvar'])) {
				$getVarName = $configuration['GETvar'];
				if (isset($configuration['urlPrepend']) && $configuration['urlPrepend']) {
					$this->urlPrepend = $configuration['urlPrepend'];

					// Note: version 1.x unsets the var if 'useConfiguration' is set
					// However it makes more sense to unset the var if 'urlPrepend' is set
					// because 'urlPrepend' is typically used for language-based domains.
					// But 'useConfiguration' can be used to localize postVarSet segment
					// values. So we change the behavior here comparing to 1.x.
					unset($this->urlParameters[$getVarName]);
				}
			}
		}
	}

	/**
	 * Sets language for the encoder either from the URl or from the TSFE.
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function setLanguage() {
		if (isset($this->urlParameters['L'])) {
			$this->validateLanguageParameter($this->urlParameters['L']);
			$this->sysLanguageUid = (int)$this->urlParameters['L'];
		} else {
			$this->sysLanguageUid = (int)$this->tsfe->sys_language_uid;
		}
	}

	/**
	 * Stores mapping between used aliases and url cache id. This information is
	 * used in the DataHandle hook to clear URl cache when record are renamed
	 * (= aliases change).
	 *
	 * @param string $urlCacheId
	 * @return void
	 */
	protected function storeAliasToUrlCacheMapping($urlCacheId) {
		foreach ($this->usedAliases as $aliasId) {
			$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_uniqalias_cache_map',
				'alias_uid=' . (int)$aliasId . ' AND url_cache_id=' . $this->databaseConnection->fullQuoteStr($urlCacheId, 'tx_realurl_uniqalias_cache_map')
			);
			if (!is_array($row)) {
				$data = array(
					'alias_uid' => $aliasId,
					'url_cache_id' => $urlCacheId
				);
				$this->databaseConnection->exec_INSERTquery('tx_realurl_uniqalias_cache_map', $data);
			}
		}
	}

	/**
	 * Adds the value to the alias cache.
	 *
	 * @param array $configuration
	 * @param string $newAliasValue
	 * @param string $idValue
	 * @param int $languageUid
	 * @return string
	 */
	protected function storeInAliasCache(array $configuration, $newAliasValue, $idValue, $languageUid) {
		$newAliasValue = $this->cleanUpAlias($configuration, $newAliasValue);

		if ($this->getFromAliasCache($configuration, $idValue, $languageUid, $newAliasValue)) {
			return $newAliasValue;
		}

		$uniqueAlias = $this->createUniqueAlias($configuration, $newAliasValue, $idValue);

		// if no unique alias was found in the process above, just suffix a hash string and assume that is unique...
		if (!$uniqueAlias) {
			$newAliasValue .= '-' . GeneralUtility::shortMD5(microtime());
			$uniqueAlias = $newAliasValue;
		}

		// Checking that this alias hasn't been stored since we looked last time
		$returnAlias = $this->getFromAliasCache($configuration, $idValue, $languageUid, $uniqueAlias);
		if ($returnAlias) {
			// If we are here it is because another process managed to create this alias in the time between we looked the first time and now when we want to put it in database.
			$uniqueAlias = $returnAlias;
		} else {
			// Expire all other aliases
			// Look for an alias based on ID
			$this->databaseConnection->exec_UPDATEquery('tx_realurl_uniqalias', 'value_id=' . $this->databaseConnection->fullQuoteStr($idValue, 'tx_realurl_uniqalias') . '
					AND field_alias=' . $this->databaseConnection->fullQuoteStr($configuration['alias_field'], 'tx_realurl_uniqalias') . '
					AND field_id=' . $this->databaseConnection->fullQuoteStr($configuration['id_field'], 'tx_realurl_uniqalias') . '
					AND tablename=' . $this->databaseConnection->fullQuoteStr($configuration['table'], 'tx_realurl_uniqalias') . '
					AND lang=' . intval($languageUid) . '
					AND expire=0', array('expire' => time() + 24 * 3600 * ($configuration['expireDays'] ? $configuration['expireDays'] : 60)));

			// Store new alias
			$insertArray = array(
				'tablename' => $configuration['table'],
				'field_alias' => $configuration['alias_field'],
				'field_id' => $configuration['id_field'],
				'value_alias' => $uniqueAlias,
				'value_id' => $idValue,
				'lang' => $languageUid
			);
			$this->databaseConnection->exec_INSERTquery('tx_realurl_uniqalias', $insertArray);
			$aliasRecordId = $this->databaseConnection->sql_insert_id();

			$this->usedAliases[] = $aliasRecordId;
		}

		return $uniqueAlias;
	}

	/**
	 * Stores data in the URL cache.
	 *
	 * @return void
	 */
	protected function storeInUrlCache() {
		if ($this->canCacheUrl($this->originalUrl)) {
			$cacheEntry = $this->cache->getUrlFromCacheByOriginalUrl($this->rootPageId, $this->originalUrl);
			/** @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry */
			if ($cacheEntry && $cacheEntry->getExpiration() !== 0 && $cacheEntry->getSpeakingUrl() === $this->encodedUrl) {
				// Unexpire
				$cacheEntry->setExpiration(0);
			}
			elseif (!$cacheEntry || $cacheEntry->getSpeakingUrl() !== $this->encodedUrl) {
				$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\UrlCacheEntry');
				$cacheEntry->setPageId($this->urlParameters['id']); // $this->originalUrlParameters['id'] can be an alias, we need a number here!
				$cacheEntry->setRequestVariables($this->removeIgnoredUrlParametersFromArray($this->originalUrlParameters));
				$cacheEntry->setRootPageId($this->rootPageId);
				$cacheEntry->setOriginalUrl($this->originalUrl);
				$cacheEntry->setSpeakingUrl($this->encodedUrl);
			}
			$this->storeInUrlCacheHooks($cacheEntry);
			$this->cache->putUrlToCache($cacheEntry);

			$cacheId = $cacheEntry->getCacheId();
			if (!empty($cacheId)) {
				$this->storeAliasToUrlCacheMapping($cacheId);
			}
		}
	}

	/**
	 * Calls user-defined hooks before adding the entry to the cache. The hook function
	 * may not unset the entry!
	 *
	 * @param UrlCacheEntry $cacheEntry
	 * @return void
	 */
	protected function storeInUrlCacheHooks(UrlCacheEntry $cacheEntry) {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['storeInUrlCache'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['storeInUrlCache'] as $userFunc) {
				$hookParams = array(
					'pObj' => $this,
					'cacheEntry' => $cacheEntry,
				);
				GeneralUtility::callUserFunction($userFunc, $hookParams, $this);
			}
		}
	}

	/**
	 * Removes multiple slashes at the end of the encoded URL.
	 */
	protected function trimMultipleSlashes() {
		$regExp = '~(/{2,})$~';
		if (preg_match($regExp, $this->encodedUrl)) {
			$this->encodedUrl = preg_replace($regExp, '/', $this->encodedUrl);
		}
	}

	/**
	 * Checks if the language is available.
	 *
	 * @param int|string $sysLanguageUid
	 * @throws \Exception
	 */
	protected function validateLanguageParameter($sysLanguageUid) {
		static $sysLanguages = null;

		if (trim($sysLanguageUid) === '') {
			// Allow this case because some people use "L=" for the default language.
			// We convert this to 0 in the setLanguage().
			$isValidLanguageUid = true;
		}
		elseif (!MathUtility::canBeInterpretedAsInteger($sysLanguageUid)) {
			$isValidLanguageUid = false;
		}
		elseif ($sysLanguageUid != 0) {
			if ($sysLanguages === null) {
				$sysLanguages = array();
				$rows = $this->databaseConnection->exec_SELECTgetRows('*', 'sys_language', '1=1' . $this->pageRepository->enableFields('sys_language'));
				foreach ($rows as $row) {
					$sysLanguages[(int)$row['uid']] = (int)$row['uid'];
				}
			}
			$isValidLanguageUid = isset($sysLanguages[(int)$sysLanguageUid]);
		}
		else {
			// It is zero
			$isValidLanguageUid = true;
		}

		if (!$isValidLanguageUid) {
			$message = sprintf(
				'Bad "L" parameter ("%s") was detected by realurl. ' .
				'Page caching is disabled to prevent spreading of wrong "L" value.',
				addslashes($sysLanguageUid)
			);
			$this->tsfe->set_no_cache($message);
			$this->logger->error($message);
			if (version_compare(TYPO3_version, '7.6.0', '>=')) {
				$this->logger->debug($message, debug_backtrace());
			}

			throw new InvalidLanguageParameterException($sysLanguageUid);
		}
	}
}
