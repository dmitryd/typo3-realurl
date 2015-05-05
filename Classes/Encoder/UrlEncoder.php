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

use DmitryDulepov\Realurl\EncodeDecoderBase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
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

	/**
	 * This is the URL with sorted GET parameters. It is used for cache
	 * manipulation.
	 *
	 * @var string
	 */
	protected $originalUrl;

	/** @var array */
	protected $originalUrlParameters = array();

	/** @var PageRepository */
	protected $pageRepository;

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

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		parent::__construct();
		$this->pageRepository = $this->tsfe->sys_page;
	}

	/**
	 * Entry point for the URL encoder.
	 *
	 * @param array $encoderParameters
	 * @return void
	 */
	public function encodeUrl(array &$encoderParameters) {
		$this->urlToEncode = $encoderParameters['LD']['totalURL'];
		if ($this->canEncoderExecute()) {
			$this->executeEncoder();
			$encoderParameters['LD']['totalURL'] = $this->encodedUrl;
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
				$testUrl = preg_replace('/https?:\/\/[^\/]+\/(.+)$/', '\1', $testUrl);
			}

			// Remove absRefPrefix if necessary
			$absRefPrefixLength = strlen($this->tsfe->absRefPrefix);
			if ($absRefPrefixLength !== 0 && substr($testUrl, 0, $absRefPrefixLength) === $this->tsfe->absRefPrefix) {
				$testUrl = substr($testUrl, $absRefPrefixLength);
			}

			if (isset(self::$urlPrependRegister[$testUrl])) {
				$urlKey = $url = $testUrl;


				$url = self::$urlPrependRegister[$urlKey] . ($url{0} != '/' ? '/' : '') . $url;

				unset(self::$urlPrependRegister[$testUrl]);

				// Adjust the URL
				$parameters['finalTag'] = str_replace(
					'"' . htmlspecialchars($parameters['finalTagParts']['url']) . '"',
					'"' . htmlspecialchars($url) . '"',
					$parameters['finalTag']
				);
				$parameters['finalTagParts']['url'] = $url;
				$pObj->lastTypoLinkUrl = $url;
			}
		}
	}

	/**
	 * Adds remaining parameters to the generated URL.
	 *
	 * @return void
	 */
	protected function addRemainingUrlParameters() {
		$urlParameters = $this->urlParameters;
		unset($urlParameters['id']);
		if (count($urlParameters) == 1 && isset($urlParameters['cHash'])) {
			unset($urlParameters['cHash']);
		}
		else
		if (count($urlParameters) > 0) {
			$this->encodedUrl .= '?' . trim(GeneralUtility::implodeArrayForUrl('', $urlParameters), '&');
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
		$cacheEntry->setMountPoint('');
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
		if ($stringToAppend) {
			$this->encodedUrl = ($this->encodedUrl ? rtrim($this->encodedUrl, '/') . '/' : '') . $stringToAppend;
			if ($addSlash) {
				$this->encodedUrl .= '/';
			}
		}
	}

	/**
	 * Checks if RealURL can encode URLs.
	 *
	 * @return bool
	 */
	protected function canEncoderExecute() {
		return $this->isRealURLEnabled() && !$this->isBackendMode() && !$this->isInWorkspace() && $this->isTypo3Url();
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
		$processedTitle = $this->utility->convertToSafeString($newAliasValue);

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

		$languageEnabled = FALSE;
		$fieldList = array();
		if ($configuration['transOrigPointerField'] && $configuration['languageField']) {
			$fieldList[] = 'uid';
			$fieldList[] = $configuration['transOrigPointerField'];
			$fieldList[] = $configuration['languageField'];
			$languageEnabled = TRUE;
		}

		// Define the language for the alias
		$languageUrlParameter = $configuration['languageGetVar'] ?: 'L';
		$languageUid = isset($this->originalUrlParameters[$languageUrlParameter]) ? (int)$this->originalUrlParameters[$languageUrlParameter] : 0;
		if (GeneralUtility::inList($configuration['languageExceptionUids'], $languageUid)) {
			$languageUid = 0;
		}

		// First, test if there is an entry in cache for the id
		if (!$configuration['useUniqueCache'] || $configuration['autoUpdate'] || !($result = $this->getFromAliasCache($configuration, $getVarValue, $languageUid))) {
			$fieldList[] = $configuration['alias_field'];
			$row = $this->databaseConnection->exec_SELECTgetSingleRow(implode(',', $fieldList), $configuration['table'],
						$configuration['id_field'] . '=' . $this->databaseConnection->fullQuoteStr($getVarValue, $configuration['table']) .
						' ' . $configuration['addWhereClause']);
			if (is_array($row)) {
				// Looking for localized version
				if ($languageEnabled && $languageUid !== 0) {
					/** @noinspection PhpUndefinedMethodInspection */
					$localizedRow = $this->databaseConnection->exec_SELECTgetSingleRow($configuration['alias_field'], $configuration['table'],
							$configuration['transOrigPointerField'] . '=' . (int)$row['uid'] . '
							AND ' . $configuration['languageField'] . '=' . $languageUid . '
							' . (isset($configuration['addWhereClause']) ? $configuration['addWhereClause'] : ''));
					if (is_array($localizedRow)) {
						$row = $localizedRow;
					}
				}

				$maxAliasLengthLength = isset($configuration['maxLength']) ? (int)$configuration['maxLength'] : self::MAX_ALIAS_LENGTH;
				$aliasValue = substr($row[$configuration['alias_field']], 0, $maxAliasLengthLength);

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

		return $result;
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
			$foundId = $this->getFromAliasCacheByAliasValue($configuration, $testNewAliasValue, TRUE);
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
		$rooLineUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Utility\\RootlineUtility', $this->urlParameters['id']);
		$rootLine = $rooLineUtility->get();

		array_pop($rootLine);

		if ((int)$this->originalUrlParameters['L'] > 0) {
			$enableLanguageOverlay = TRUE;
			$fieldList = self::$pageOverlayTitleFields;
		}
		else {
			$enableLanguageOverlay = FALSE;
			$fieldList = self::$pageTitleFields;
		}

		$components = array();
		foreach (array_reverse($rootLine) as $page) {
			if ($enableLanguageOverlay) {
				$overlay = $this->pageRepository->getPageOverlay($page, (int)$this->originalUrlParameters['L']);
				if (is_array($overlay)) {
					$page = $overlay;
					unset($overlay);
				}
			}
			foreach ($fieldList as $field) {
				if ($page[$field]) {
					$segment = $this->utility->convertToSafeString($page[$field]);
					if ($segment) {
						$components[] = $segment;
						$this->appendToEncodedUrl($segment);
						continue 2;
					}
				}
			}
		}

		if (count($components) > 0) {
			$this->addToPathCache(implode('/', $components));
		}
	}

	/**
	 * Encodes fixed postVars.
	 *
	 * @return void
	 */
	protected function encodeFixedPostVars() {
		$configuration = (array)$this->configuration->get('postVarSets');
		$postVarSetConfiguration = $this->getConfigirationForPostVars($configuration, $this->urlParameters['id']);

		$segments = $this->encodeUrlParameterBlock($postVarSetConfiguration);
		if (count($segments) > 0) {
			$this->appendToEncodedUrl(implode('/', $segments));
		}
	}

	/**
	 * Encodes the path to the page.
	 *
	 * @return void
	 */
	protected function encodePathComponents() {
		$cacheEntry = $this->cache->getPathFromCacheByPageId($this->rootPageId, $this->sysLanguageUid, $this->urlParameters['id']);
		if ($cacheEntry) {
			$this->appendToEncodedUrl($cacheEntry->getPagePath());
		}
		else {
			$this->createPathComponent();
		}
	}

	/**
	 * Encodes 'preVars' into URL segments.
	 *
	 * @return void
	 */
	protected function encodePreVars() {
		$segments = $this->encodeUrlParameterBlock((array)$this->configuration->get('preVars'));
		if (count($segments) > 0) {
			$this->appendToEncodedUrl(implode('/', $segments));
		}
	}

	/**
	 * Encodes 'postVarSets' into URL segments.
	 *
	 * @return void
	 */
	protected function encodePostVarSets() {
		$configuration = (array)$this->configuration->get('postVarSets');
		$postVarSetConfigurations = $this->getConfigirationForPostVars($configuration, $this->urlParameters['id']);

		foreach ($postVarSetConfigurations as $postVar => $postVarSetConfiguration) {
			$segments = $this->encodeUrlParameterBlock($postVarSetConfiguration);
			if (count($segments) > 0) {
				array_unshift($segments, $postVar);
				$this->appendToEncodedUrl(implode('/', $segments));
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
			// Allways the last one!
			'encodeUrlParameterBlockUseAsIs',
		);

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

	/**
	 * Encodes pre- or postVars according to the given configuration.
	 *
	 * @param array $configurationArray
	 * @return string
	 */
	protected function encodeUrlParameterBlock(array $configurationArray) {
		$segments = array();

		if ($this->hasUrlParameters($configurationArray)) {
			$previousValue = '';
			foreach ($configurationArray as $configuration) {
				$this->encodeSingleVariable($configuration, $previousValue, $segments);
			}
		}

		$this->checkForAllEmptySegments($segments);

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
				'pObj' => &$this,
				'value' => $getVarValue,
				'decodeAlias' => false,
				'pathParts' => &$segments,
				'setup' => $configuration,
			);
			$getVarValue = GeneralUtility::callUserFunction($configuration['userFunc'], $userFuncParameters, $this);
			$segments[] = rawurlencode($getVarValue);
			$result = TRUE;
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
	 */
	protected function executeEncoder() {
		$this->parseUrlParameters();

		$this->setLanguage();
		$this->initializeUrlPrepend();
		if (!$this->fetchFromtUrlCache()) {
			$this->encodePreVars();
			$this->encodePathComponents();
			$this->encodeFixedPostVars();
			$this->encodePostVarSets();
			$this->handleFileName();

			$this->addRemainingUrlParameters();

			if ($this->encodedUrl === '') {
				$emptyUrlReturnValue = $this->configuration->get('init/emptyUrlReturnValue') ?: '/';
				$this->encodedUrl = $emptyUrlReturnValue;
			}
			$this->storeInUrlCache();
			$this->reapplyAbsRefPrefix();
		}
		$this->prepareUrlPrepend();
	}

	/**
	 * Attempts to fetch the speaking URL from the url cache.
	 *
	 * @return bool
	 */
	protected function fetchFromtUrlCache() {
		$result = FALSE;

		$cacheEntry = $this->cache->getUrlFromCacheByOriginalUrl($this->rootPageId, $this->originalUrl);
		if ($cacheEntry) {
			$this->encodedUrl = $cacheEntry->getSpeakingUrl();
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Obtains the value from the alias cache.
	 *
	 * @param array $configuration
	 * @param string $getVarValue
	 * @param int $languageUid
	 * @param string $onlyThisAlias
	 * @return string|null
	 */
	protected function getFromAliasCache(array $configuration, $getVarValue, $languageUid, $onlyThisAlias = '') {
		$row = $this->databaseConnection->exec_SELECTgetSingleRow('value_alias', 'tx_realurl_uniqalias',
				'value_id=' . $this->databaseConnection->fullQuoteStr($getVarValue, 'tx_realurl_uniqalias') .
				' AND field_alias=' . $this->databaseConnection->fullQuoteStr($configuration['alias_field'], 'tx_realurl_uniqalias') .
				' AND field_id=' . $this->databaseConnection->fullQuoteStr($configuration['id_field'], 'tx_realurl_uniqalias') .
				' AND tablename=' . $this->databaseConnection->fullQuoteStr($configuration['table'], 'tx_realurl_uniqalias') .
				' AND lang=' . intval($languageUid) .
				($onlyThisAlias ? ' AND value_alias=' . $this->databaseConnection->fullQuoteStr($onlyThisAlias, 'tx_realurl_uniqalias') : '') .
				' AND expire=0'
		);
		return is_array($row) ? $row['value_alias'] : NULL;
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
					$this->appendToEncodedUrl($fileName, FALSE);
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
			if (isset($this->urlParameters[$configuration['GETvar']])) {
				$result = TRUE;
				break;
			}
		}

		return $result;
	}

	/**
	 * Checks if system runs in non-live workspace
	 *
	 * @return boolean
	 */
	protected function isBackendMode() {
		return (TYPO3_MODE == 'BE');
	}

	/**
	 * Checks if system runs in non-live workspace
	 *
	 * @return boolean
	 */
	protected function isInWorkspace() {
		$result = false;
		if ($this->tsfe->beUserLogin) {
			$result = ($GLOBALS['BE_USER']->workspace !== 0);
		}
		return $result;
	}

	/**
	 * Checks if RealURl is enabled.
	 *
	 * @return bool
	 */
	protected function isRealURLEnabled() {
		return (bool)$this->tsfe->config['config']['tx_realurl_enable'];
	}

	/**
	 * Checks if a TYPO3 URL is going to be encoded.
	 *
	 * @return bool
	 */
	protected function isTypo3Url() {
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
		$this->originalUrl = trim(GeneralUtility::implodeArrayForUrl('', $sortedUrlParameters), '&');
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
	 * @return void
	 */
	protected function reapplyAbsRefPrefix() {
		$reapplyAbsRefPrefix = (bool)$this->configuration->get('init/reapplyAbsRefPrefix');
		if (!isset($reapplyAbsRefPrefix) || $reapplyAbsRefPrefix && $this->tsfe->absRefPrefix) {
			// Prevent // in case of absRefPrefix ending with / and emptyUrlReturnValue=/
			if (substr($this->tsfe->absRefPrefix, -1, 1) == '/' && substr($this->encodedUrl, 0, 1) == '/') {
				$this->encodedUrl = substr($this->encodedUrl, 1);
			}
			$this->encodedUrl = $this->tsfe->absRefPrefix . $this->encodedUrl;
		}
	}

	/**
	 * Checks if we should prpend URL according to _DOMAINS configuration.
	 *
	 * @return void
	 */
	protected function initializeUrlPrepend() {
		$domainsConfiguration = $this->configuration->get('domains/encode');
		if (is_array($domainsConfiguration)) {
			foreach ($domainsConfiguration as $configuration) {
				$getVarName = $configuration['GETvar'];
				// Note: non-strict comparison here is required!
				if ($this->urlParameters[$getVarName] == $configuration['value']) {
					$this->urlPrepend = $configuration['urlPrepend'];
					unset($this->urlParameters[$getVarName]);
				}
			}
		}
	}

	/**
	 * Sets language for the encoder either from the URl or from the TSFE.
	 *
	 * @return void
	 */
	protected function setLanguage() {
		if (isset($this->urlParameters['L']) && MathUtility::canBeInterpretedAsInteger($this->urlParameters['L'])) {
			$this->sysLanguageUid = (int)$this->urlParameters['L'];
		}
		else {
			$this->sysLanguageUid = (int)$this->tsfe->sys_language_uid;
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

		if ($configuration['autoUpdate'] && $this->getFromAliasCache($configuration, $idValue, $languageUid, $newAliasValue)) {
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
		}
		else {
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
				'tstamp' => time(),
				'tablename' => $configuration['table'],
				'field_alias' => $configuration['alias_field'],
				'field_id' => $configuration['id_field'],
				'value_alias' => $uniqueAlias,
				'value_id' => $idValue,
				'lang' => $languageUid
			);
			$this->databaseConnection->exec_INSERTquery('tx_realurl_uniqalias', $insertArray);
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
			if (!$cacheEntry || $cacheEntry->getSpeakingUrl() !== $this->encodedUrl) {
				$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\UrlCacheEntry');
				/** @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry */
				$cacheEntry->setPageId($this->originalUrlParameters['id']);
				$cacheEntry->setRequestVariables($this->originalUrlParameters);
				$cacheEntry->setRootPageId($this->rootPageId);
				$cacheEntry->setOriginalUrl($this->originalUrl);
				$cacheEntry->setSpeakingUrl($this->encodedUrl);
				$this->cache->putUrlToCache($cacheEntry);
			}
		}
	}
}
