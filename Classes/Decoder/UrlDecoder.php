<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2015 Dmitry Dulepov (dmitry.dulepov@gmail.com)
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
namespace DmitryDulepov\Realurl\Decoder;

use DmitryDulepov\Realurl\Cache\PathCacheEntry;
use DmitryDulepov\Realurl\Cache\UrlCacheEntry;
use DmitryDulepov\Realurl\EncodeDecoderBase;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * This class contains URL decoder for the RealURL.
 *
 * @package DmitryDulepov\Realurl\Decoder
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class UrlDecoder extends EncodeDecoderBase {

	/** @var bool */
	protected $appendedSlash = FALSE;

	/** @var TypoScriptFrontendController */
	protected $caller;

	/**
	 * Holds information about expired path for the SEO redirect.
	 *
	 * @var string
	 */
	protected $expiredPath = '';

	/** @var string */
	protected $mimeType = '';

	/** @var PageRepository */
	protected $pageRepository = NULL;

	/** @var string */
	protected $siteScript;

	/** @var string */
	protected $speakingUri;

	/** @var int */
	protected $sysLanguageUid = 0;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		parent::__construct();
		$this->siteScript = GeneralUtility::getIndpEnv('TYPO3_SITE_SCRIPT');
	}

	/**
	 * Decodes the URL. This function is called from \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::checkAlternativeIdMethods()
	 *
	 * @param array $params
	 * @return void
	 */
	public function decodeUrl(array $params) {
		$this->caller = $params['pObj'];

		if ($this->isSpeakingUrl()) {
			$this->setSpeakingUriFromSiteScript();
			$this->checkMissingSlash();
			if ($this->speakingUri) {
				$this->setLanguageFromQueryString();
				$this->runDecoding();
			}
		}
	}

	/**
	 * Calculates and adds cHash to the entry. This function is only called
	 * if we had to decode the entry, which was not in the cache. Even if we
	 * had cHash in the URL, we force to recalculate it because we could have
	 * decoded parameters differently than the original URL had (for example,
	 * skipped some noMatch parameters).
	 *
	 * @param UrlCacheEntry $cacheEntry
	 * @return void
	 */
	protected function calculateChash(UrlCacheEntry $cacheEntry) {
		$requestVariables = $cacheEntry->getRequestVariables();

		$cacheHashCalculator = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\CacheHashCalculator');
		/* @var \TYPO3\CMS\Frontend\Page\CacheHashCalculator $cacheHashCalculator */
		$cHashParameters = $cacheHashCalculator->getRelevantParameters(GeneralUtility::implodeArrayForUrl('', $requestVariables));

		if (count($cHashParameters) > 0) {
			$requestVariables['cHash'] = $cacheHashCalculator->calculateCacheHash($cHashParameters);
			$cacheEntry->setRequestVariables($requestVariables);
		}
	}

	/**
	 * Checks if the missing slash should be corrected.
	 *
	 * @return void
	 */
	protected function checkMissingSlash() {
		$this->speakingUri = rtrim($this->speakingUri, '?');

		$regexp = '~^([^\?]*[^/])(\?.*)?$~';
		if (preg_match($regexp, $this->speakingUri)) { // Only process if a slash is missing:
			$options = GeneralUtility::trimExplode(',', $this->configuration->get('init/appendMissingSlash'), true);
			if (in_array('ifNotFile', $options)) {
				if (!preg_match('/\/[^\/\?]+\.[^\/]+(\?.*)?$/', '/' . $this->speakingUri)) {
					$this->speakingUri = preg_replace($regexp, '\1/\2', $this->speakingUri);
					$this->appendedSlash = true;
				}
			}
			else {
				$this->speakingUri = preg_replace($regexp, '\1/\2', $this->speakingUri);
				$this->appendedSlash = true;
			}
			if ($this->appendedSlash && count($options) > 0) {
				foreach ($options as $option) {
					$matches = array();
					if (preg_match('/^redirect(\[(30[1237])\])?$/', $option, $matches)) {
						$code = count($matches) > 1 ? $matches[2] : 301;
						$status = 'HTTP/1.1 ' . $code . ' TYPO3 RealURL redirect for missing slash';

						// Check path segment to be relative for the current site.
						// parse_url() does not work with relative URLs, so we use it to test
						if (!@parse_url($this->speakingUri, PHP_URL_HOST)) {
							@ob_end_clean();
							header($status);
							header('Location: ' . GeneralUtility::locationHeaderUrl($this->speakingUri));
							exit;
						}
					}
				}
			}
		}
	}

	/**
	 * Converts alias to id.
	 *
	 * @param array $configuration
	 * @param string $value
	 * @return int|string
	 */
	protected function convertAliasToId(array $configuration, $value) {
		$result = (string)$value;

		// Assemble list of fields to look up. This includes localization related fields
		$translationEnabled = FALSE;
		$fieldList = array();
		if ($configuration['languageGetVar'] && $configuration['transOrigPointerField'] && $configuration['languageField']) {
			$fieldList[] = 'uid';
			$fieldList[] = $configuration['transOrigPointerField'];
			$fieldList[] = $configuration['languageField'];
			$translationEnabled = TRUE;
		}

		// First, test if there is an entry in cache for the alias
		if ($configuration['useUniqueCache']) {
			$cachedId = $this->getFromAliasCacheByAliasValue($configuration, $value, FALSE);
			if (MathUtility::canBeInterpretedAsInteger($cachedId)) {
				$result = (int)$cachedId;
			}
		}

		if (!is_int($result)) {
			// If no cached entry, look it up directly in the table. Note: this will
			// most likely fail. When encoding we convert alias field to a nice
			// looking URL segment, which usually looks differently from the field.
			// But this is the only thing we can do without fetching each record and
			// re-encoding the field to find the match.
			$fieldList[] = $configuration['id_field'];
			$row = $this->databaseConnection->exec_SELECTgetSingleRow(implode(',', $fieldList),
				$configuration['table'],
				$configuration['alias_field'] . '=' . $this->databaseConnection->fullQuoteStr($value, $configuration['table']) .
				' ' . $configuration['addWhereClause']);
			if (is_array($row)) {
				$result = (int)$row[$configuration['id_field']];

				// If localization is enabled, check if this record is a localized version and if so, find uid of the original version.
				if ($translationEnabled && $row[$configuration['languageField']] > 0) {
					$result = (int)$row[$configuration['transOrigPointerField']];
				}
			}
		}

		return $result;
	}

	/**
	 * Creates a page repository object if it does not exist yet.
	 *
	 * @return void
	 */
	protected function createPageRepository() {
		if (is_null($this->pageRepository)) {
			$this->pageRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
			$this->pageRepository->init(FALSE);
		}
	}

	/**
	 * Creates query string from passed variables.
	 *
	 * @param array|null $getVars
	 * @return string
	 */
	protected function createQueryString($getVars) {
		if (!is_array($getVars) || count($getVars) == 0) {
			return $_SERVER['QUERY_STRING'];
		}

		$parameters = array();
		foreach ($getVars as $var => $value) {
			$parameters = array_merge($parameters, $this->createQueryStringParameter($value, $var));
		}

		$queryString = GeneralUtility::getIndpEnv('QUERY_STRING');
		if ($queryString) {
			array_push($parameters, $queryString);
		}

		return implode('&', $parameters);
	}


	/**
	 * Generates a parameter string from an array recursively
	 *
	 * @param array $parameters Array to generate strings from
	 * @param string $prependString path to prepend to every parameter
	 * @return array
	 */
	protected function createQueryStringParameter($parameters, $prependString = '') {
		if (!is_array($parameters)) {
			return array($prependString . '=' . $parameters);
		}

		if (count($parameters) == 0) {
			return array();
		}

		$paramList = array();
		foreach ($parameters as $var => $value) {
			$paramList = array_merge($paramList, $this->createQueryStringParameter($value, $prependString . '[' . $var . ']'));
		}

		return $paramList;
	}

	/**
	 * Decodes fixedPostVars into request variables.
	 *
	 * @param int $pageId
	 * @param array $pathSegments
	 * @return array
	 */
	protected function decodeFixedPostVars($pageId, array &$pathSegments) {
		$requestVariables = array();

		$allPostVars = array_filter((array)$this->configuration->get('fixedPostVars'));
		$postVars = $this->getConfigirationForPostVars($allPostVars, $pageId);

		$previousValue = '';
		foreach ($postVars as $postVarConfiguration) {
			$this->decodeSingleVariable($postVarConfiguration, $pathSegments, $requestVariables, $previousValue);
			if (count($pathSegments) == 0) {
				// TODO Is it correct to break here? fixedPostVars should all present!
				break;
			}
		}

		return $requestVariables;
	}

	/**
	 * Decodes the path.
	 *
	 * @param array $pathSegments
	 * @return int
	 */
	protected function decodePath(array &$pathSegments) {
		$remainingPathSegments = $pathSegments;
		$result = $this->searchPathInCache($remainingPathSegments);

		if (is_null($result) || count($remainingPathSegments) > 0) {
			// Here we are if one of the following is true:
			// - nothing is in the cache
			// - there is an entry in the cache for the partial path
			// We see what it is:
			// - if a postVar exists for the next segment, it is a full path
			// - if no path segments left, we found the path
			// - otherwise we have to search

			reset($pathSegments);
			if (!$this->isPostVar(current($pathSegments))) {
				$this->createPageRepository();

				if ($result) {
					$processedPathSegments = array_diff($pathSegments, $remainingPathSegments);
					$currentPid = $result->getPageId();
				} else {
					$processedPathSegments = array();
					$currentPid = $this->rootPageId;
				}
				while ($currentPid !== 0 && count($remainingPathSegments) > 0) {
					$segment = array_shift($remainingPathSegments);
					$nextResult = $this->searchPages($currentPid, $segment);
					if ($nextResult) {
						$result = $nextResult;
						$currentPid = $result->getPageId();
						$result->setPagePath(implode('/', $processedPathSegments));
						$processedPathSegments[] = $segment;
						// Path is valid so far, so we cache it
						$this->putToPathCache($result);
					}
					else {
						$currentPid = 0;
						array_unshift($remainingPathSegments, $segment);
					}
				}
			}
		}
		if ($this->expiredPath) {
			$startPosition = (int)strpos($this->speakingUri, $this->expiredPath);
			if ($startPosition !== FALSE) {
				$newUrl = substr($this->speakingUri, 0, $startPosition) .
					$result->getPagePath() .
					substr($this->speakingUri, $startPosition + strlen($this->expiredPath));
				@ob_end_clean();
				header('HTTP/1.1 302 TYPO3 RealURL redirect for expired page path');
				header('Location: ' . GeneralUtility::locationHeaderUrl($newUrl));
				die;
			}
		}
		if ($result) {
			$pathSegments = $remainingPathSegments;
		}

		return $result;
	}

	/**
	 * Decodes preVars into request variables.
	 *
	 * @param array $pathSegments
	 * @return array
	 */
	protected function decodePreVars(array &$pathSegments) {
		$requestVariables = array();

		$preVarsList = array_filter((array)$this->configuration->get('preVars'));

		$previousValue = '';
		foreach ($preVarsList as $preVarConfiguration) {
			$this->decodeSingleVariable($preVarConfiguration, $pathSegments, $requestVariables, $previousValue);
		}

		return $requestVariables;
	}

	/**
	 * Decodes postVarSets into request variables.
	 *
	 * @param int $pageId
	 * @param array $pathSegments
	 * @return array
	 */
	protected function decodePostVarSets($pageId, array &$pathSegments) {
		$requestVariables = array();

		$allPostVarSets = array_filter((array)$this->configuration->get('postVarSets'));
		$postVarSets = $this->getConfigirationForPostVars($allPostVarSets, $pageId);

		$previousValue = '';
		foreach ($postVarSets as $postVar => $postVarSetConfiguration) {
			reset($pathSegments);
			if (current($pathSegments) == $postVar) {
				array_shift($pathSegments);
				foreach ($postVarSetConfiguration as $postVarConfiguration) {
					$this->decodeSingleVariable($postVarConfiguration, $pathSegments, $requestVariables, $previousValue);
				}
			}
			if (count($pathSegments) == 0) {
				break;
			}
		}

		return $requestVariables;
	}

	/**
	 * Decodes a single variable and adds it to the list of request variables.
	 *
	 * @param array $varConfiguration
	 * @param array $pathSegments
	 * @param array $requestVariables
	 * @param $previousValue
	 * @return void
	 */
	protected function decodeSingleVariable(array $varConfiguration, array &$pathSegments, array &$requestVariables, &$previousValue) {
		static $varProcessingFunctions = array(
			'decodeUrlParameterBlockUsingValueMap',
			'decodeUrlParameterBlockUsingNoMatch',
			'decodeUrlParameterBlockUsingUserFunc',
			'decodeUrlParameterBlockUsingLookupTable',
			'decodeUrlParameterBlockUsingValueDefault',
			// Allways the last one!
			'decodeUrlParameterBlockUseAsIs',
		);

		$getVarValue = count($pathSegments) > 0 ? array_shift($pathSegments) : '';

		// TODO Check conditions here

		// TODO Possible hook here before any other function? Pass name, value, segments and config

		$handled = FALSE;
		if (!isset($varConfiguration['cond']) || $this->checkLegacyCondition($varConfiguration['cond'], $previousValue)) {
			foreach ($varProcessingFunctions as $varProcessingFunction) {
				if ($this->$varProcessingFunction($varConfiguration, $getVarValue, $requestVariables, $pathSegments)) {
					$previousValue = (string)end($requestVariables);
					$handled = TRUE;
					break;
				}
			}
		}
		if (!$handled) {
			array_unshift($pathSegments, $getVarValue);
		}
	}

	/**
	 * Sets segment value as is to the request variables
	 *
	 * @param array $configuration
	 * @param $getVarValue
	 * @param array $requestVariables
	 * @return bool
	 */
	protected function decodeUrlParameterBlockUseAsIs(array $configuration, $getVarValue, array &$requestVariables) {
		// TODO Possible conditions: if int, if notEmpty, etc
		$requestVariables[$configuration['GETvar']] = $getVarValue;

		return TRUE;
	}

	/**
	 * Sets segment value as is to the request variables
	 *
	 * @param array $configuration
	 * @param $getVarValue
	 * @param array $requestVariables
	 * @return bool
	 */
	protected function decodeUrlParameterBlockUsingLookupTable(array $configuration, $getVarValue, array &$requestVariables) {
		$result = FALSE;

		if (isset($configuration['lookUpTable'])) {
			$value = $this->convertAliasToId($configuration['lookUpTable'], $getVarValue);
			if (!MathUtility::canBeInterpretedAsInteger($value) && $value === $getVarValue) {
				if ($configuration['lookUpTable']['enable404forInvalidAlias']) {
					$this->tsfe->pageNotFoundAndExit('Could not map alias "' . $value . '" to an id.');
				}
			}
			else {
				$requestVariables[$configuration['GETvar']] = $value;
				$result = TRUE;
			}
		}

		return $result;
	}

	/**
	 * Sets segment value as is to the request variables
	 *
	 * @param array $configuration
	 * @param $getVarValue
	 * @param array $requestVariables
	 * @param array $pathSegments
	 * @return bool
	 */
	protected function decodeUrlParameterBlockUsingNoMatch(array $configuration, $getVarValue, /** @noinspection PhpUnusedParameterInspection */ array &$requestVariables, array &$pathSegments) {
		$result = FALSE;
		if ($configuration['noMatch'] == 'bypass') {
			// If no match and "bypass" is set, then return the value to $pathSegments and break
			array_unshift($pathSegments, $getVarValue);
			$result = TRUE;
		}
		elseif ($configuration['noMatch'] == 'null') {
			// If no match and "null" is set, then break (without setting any value!)
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Sets segment value as is to the request variables
	 *
	 * @param array $configuration
	 * @param $getVarValue
	 * @param array $requestVariables
	 * @param array $pathSegments
	 * @return bool
	 */
	protected function decodeUrlParameterBlockUsingUserFunc(array $configuration, $getVarValue, array &$requestVariables, array &$pathSegments) {
		$result = FALSE;

		if (isset($configuration['userFunc'])) {
			$parameters = array(
				'decodeAlias' => true,
				'origValue' => $getVarValue,
				'pathParts' => &$pathSegments,
				'pObj' => &$this,
				'value' => $getVarValue,
				'setup' => $configuration
			);
			$value = GeneralUtility::callUserFunction($configuration['userFunc'], $parameters, $this);
			if (!is_numeric($value) || is_string($value)) {
				$requestVariables[$configuration['GETvar']] = $value;
				$result = TRUE;
			}
		}

		return $result;
	}

	/**
	 * Sets segment value as is to the request variables
	 *
	 * @param array $configuration
	 * @param $getVarValue
	 * @param array $requestVariables
	 * @return bool
	 */
	protected function decodeUrlParameterBlockUsingValueDefault(array $configuration, /** @noinspection PhpUnusedParameterInspection */ $getVarValue, array &$requestVariables) {
		$result = FALSE;
		if (isset($configuration['valueDefault'])) {
			$defaultValue = $configuration['valueDefault'];
			$requestVariables[$configuration['GETvar']] = isset($configuration['valueMap'][$defaultValue]) ? $configuration['valueMap'][$defaultValue] : $defaultValue;
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Sets segment value as is to the request variables
	 *
	 * @param array $configuration
	 * @param $getVarValue
	 * @param array $requestVariables
	 * @return bool
	 */
	protected function decodeUrlParameterBlockUsingValueMap(array $configuration, $getVarValue, array &$requestVariables) {
		$result = FALSE;
		if (isset($configuration['valueMap'][$getVarValue])) {
			$requestVariables[$configuration['GETvar']] = $configuration['valueMap'][$getVarValue];
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Decodes the URL. This function is called only if the URL is not in the
	 * URL cache.
	 *
	 * @param string $path
	 * @return UrlCacheEntry with only pageId and requestVariables filled in
	 */
	protected function doDecoding($path) {
		$pathSegments = explode('/', trim($path, '/'));
		array_walk($pathSegments, 'urldecode');

		$requestVariables = array();

		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->handleFileName($pathSegments));
		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->decodePreVars($pathSegments));
		$pageId = $this->decodePath($pathSegments);
		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->decodeFixedPostVars($pageId, $pathSegments));
		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->decodePostVarSets($pageId, $pathSegments));

		if (count($pathSegments) > 0) {
			reset($pathSegments);
			$this->throw404('"' . current($pathSegments) . '" could not be decoded from path.');
		}

		$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\UrlCacheEntry');
		/** @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry */
		$cacheEntry->setPageId($pageId);
		$cacheEntry->setRequestVariables($requestVariables);

		$this->calculateChash($cacheEntry);

		return $cacheEntry;
	}

	/**
	 * Fixes magic_quotes_gpc if somebody still has them turned on.
	 *
	 * @param array $array
	 */
	protected function fixMagicQuotesGpc(&$array) {
		if (get_magic_quotes_gpc() && is_array($array)) {
			GeneralUtility::stripSlashesOnArray($array);
		}
	}

	/**
	 * Fixes a problem with parse_str that returns `a[b[c]` instead of `a[b[c]]` when parsing `a%5Bb%5Bc%5D%5D`
	 *
	 * @param array $array
	 * @return	void
	 */
	protected function fixBracketsAfterParseStr(array &$array) {
		$badKeys = array();
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$this->fixBracketsAfterParseStr($array[$key]);
			} else {
				if (strchr($key, '[') && !strchr($key, ']')) {
					$badKeys[] = $key;
				}
			}
		}
		if (count($badKeys) > 0) {
			foreach ($badKeys as $key) {
				$arr[$key . ']'] = $array[$key];
				unset($array[$key]);
			}
		}
	}

	/**
	 * Gets the entry from cache.
	 *
	 * @param string $speakingUrl
	 * @return UrlCacheEntry|null
	 */
	protected function getFromUrlCache($speakingUrl) {
		return $this->cache->getUrlFromCacheBySpeakingUrl($this->rootPageId, $speakingUrl);
	}

	/**
	 * Parses the URL and validates the result.
	 *
	 * @return array
	 */
	protected function getUrlParts() {
		$uParts = @parse_url($this->speakingUri);
		if (!is_array($uParts)) {
			$this->throw404('Current URL is invalid');
		}

		return $uParts;
	}

	/**
	 * Processes the file name component. There can be several scenarios:
	 * 1. File name is mapped to a _GET var. We set a _GET var and discard the segment.
	 * 2. File name is a segment with suffix appended. We discard the suffix.
	 *
	 * @param array $urlParts
	 * @return array
	 */
	protected function handleFileName(array &$urlParts) {
		$getVars = array();
		if (count($urlParts) > 0) {
			$putBack = TRUE;
			$fileNameSegment = array_pop($urlParts);
			if ($fileNameSegment && strpos($fileNameSegment, '.') !== FALSE) {
				if (!$this->handleFileNameMappingToGetVar($fileNameSegment, $getVars)) {
					$validExtensions = array();

					foreach (array('acceptHTMLsuffix', 'defaultToHTMLsuffixOnPrev') as $option) {
						$acceptSuffix = $this->configuration->get('fileName/' . $option);
						if (is_string($acceptSuffix) && strpos($acceptSuffix, '.') !== FALSE) {
							$validExtensions[] = $acceptSuffix;
						} elseif ($acceptSuffix) {
							$validExtensions[] = '.html';
						}
					}

					$extension = '.' . pathinfo($fileNameSegment, PATHINFO_EXTENSION);
					if (in_array($extension, $validExtensions)) {
						$fileNameSegment = pathinfo($fileNameSegment, PATHINFO_FILENAME);
					}
					// If no match, we leave it as is => 404.
				}
				else {
					$putBack = FALSE;
				}
			}
			if ($putBack) {
				$urlParts[] = $fileNameSegment;
			}
		}

		return $getVars;
	}

	/**
	 * Handles mapping of file names to GET vars (like 'print.html' => 'type=98')
	 *
	 * @param string $fileNameSegment
	 * @param array $getVars
	 * @return bool
	 */
	protected function handleFileNameMappingToGetVar($fileNameSegment, array &$getVars) {
		$result = FALSE;
		if ($fileNameSegment) {
			$fileNameConfiguration = $this->configuration->get('fileName/index/' . $fileNameSegment);
			if (is_array($fileNameConfiguration)) {
				$result = TRUE;
				if (isset($fileNameConfiguration['keyValues'])) {
					$getVars = $fileNameConfiguration['keyValues'];
				}
			}
		}

		return $result;
	}

	/**
	 * Checks if the given segment is a name of the postVar.
	 *
	 * @param string $segment
	 * @return bool
	 */
	protected function isPostVar($segment) {
		$postVarNames = array_filter(array_keys((array)$this->configuration->get('postVarSets')));
		return in_array($segment, $postVarNames);
	}


	/**
	 * Checks if the current URL is a speaking URL.
	 *
	 * @return bool
	 */
	protected function isSpeakingUrl() {
		return $this->siteScript && substr($this->siteScript, 0, 9) !== 'index.php' && substr($this->siteScript, 0, 1) !== '?';
	}

	/**
	 * Converts array('tx_ext[var1]' => 1, 'tx_ext[var2]' => 2) to array('tx_ext' => array('var1' => 1, 'var2' => 2)).
	 *
	 * @param array $requestVariables
	 * @return array
	 */
	protected function makeRealPhpArrayFromRequestVars(array $requestVariables) {
		$result = array();

		parse_str(trim(GeneralUtility::implodeArrayForUrl('', $requestVariables), '&'), $result);
		$this->fixMagicQuotesGpc($result);
		$this->fixBracketsAfterParseStr($result);

		return $result;
	}

	/**
	 * Adds data to the path cache. Cache ntry should have page path, language id and page id set.
	 *
	 * @param PathCacheEntry $newCacheEntry
	 * @return void
	 */
	protected function putToPathCache(PathCacheEntry $newCacheEntry) {
		$pagePath = $newCacheEntry->getPagePath();
		$cacheEntry = $this->cache->getPathFromCacheByPagePath($this->rootPageId, '', $pagePath);
		if (!$cacheEntry || $cacheEntry->getExpiration() !== 0 || $cacheEntry->getPagePath() !== $pagePath) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $cacheEntry */
			$cacheEntry->setExpiration(0);
			$cacheEntry->setMountPoint('');
			$cacheEntry->setRootPageId($this->rootPageId);

			$this->cache->putPathToCache($cacheEntry);
		}
	}

	/**
	 * Adds data to the url cache. This must run after $this->setRequestVariables().
	 *
	 * @param UrlCacheEntry $cacheEntry
	 * @return void
	 */
	protected function putToUrlCache(UrlCacheEntry $cacheEntry) {
		$requestVariables = $cacheEntry->getRequestVariables();
		$requestVariables['id'] = $cacheEntry->getPageId();
		$this->sortArrayDeep($requestVariables);

		$originalUrl = trim(GeneralUtility::implodeArrayForUrl('', $requestVariables), '&');

		if ($this->canCacheUrl($originalUrl)) {
			$cacheEntry->setOriginalUrl($originalUrl);
			$cacheEntry->setRequestVariables($requestVariables);
			$cacheEntry->setRootPageId($this->rootPageId);
			$cacheEntry->setSpeakingUrl($this->speakingUri);

			$this->cache->putUrlToCache($cacheEntry);
		}
	}

	/**
	 * Contains the actual decoding logic after $this->speakingUri is set.
	 *
	 * @return void
	 */
	protected function runDecoding() {
		$urlParts = $this->getUrlParts();

		$cacheEntry = $this->getFromUrlCache($this->speakingUri);
		if (!$cacheEntry) {
			$cacheEntry = $this->doDecoding($urlParts['path']);
		}
		$this->setRequestVariables($cacheEntry);

		// If it is still not there (could have been added by other process!), than update
		if (!$this->getFromUrlCache($this->speakingUri)) {
			$this->putToUrlCache($cacheEntry);
		}
	}

	/**
	 * Searches pages for the match to the segment
	 *
	 * @param int $currentPid
	 * @param string $segment
	 * @return PathCacheEntry
	 */
	protected function searchPages($currentPid, $segment) {
		$result = NULL;

		$pagesEnableFields = $this->pageRepository->enableFields('pages');
		$pages = $this->databaseConnection->exec_SELECTgetRows('*', 'pages', 'pid=' . (int)$currentPid . ' AND doktype IN (1,2,4)' . $pagesEnableFields);
		foreach ($pages as $page) {
			foreach (self::$pageTitleFields as $field) {
				if ($this->utility->convertToSafeString($page[$field]) == $segment) {
					$result = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
					/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $cacheEntry */
					$result->setPageId((int)$page['uid']);
				}
			}
		}

		return $result;
	}

	/**
	 * Fetches the entry from the RealURL path cache. This would start stripping
	 * segments if the entry is not found until none is left. Effectively it is
	 * a search for the largest caching path for those segments.
	 *
	 * @param array $pathSegments
	 * @return PathCacheEntry|null
	 */
	protected function searchPathInCache(array &$pathSegments) {
		$result = NULL;
		$removedSegments = array();

		while (is_null($result) && count($pathSegments) > 0) {
			$path = implode('/', $pathSegments);
			$cacheEntry = $this->cache->getPathFromCacheByPagePath($this->rootPageId, '', $path);
			if ($cacheEntry) {
				if ((int)$cacheEntry->getExpiration() !== 0) {
					$nonExpiredCacheEntry = $this->cache->getPathFromCacheByPageId($cacheEntry->getRootPageId(), $cacheEntry->getLanguageId(), $cacheEntry->getPageId());
					if ($nonExpiredCacheEntry) {
						$this->expiredPath = $cacheEntry->getPagePath();
						$cacheEntry = $nonExpiredCacheEntry;
					}
				}
				$result = $cacheEntry;
			}
			else {
				array_unshift($removedSegments, array_pop($pathSegments));
			}
		}
		$pathSegments = $removedSegments;

		return $result;
	}

	/**
	 * Sets current language from the query string variable ('L').
	 *
	 * @return void
	 */
	protected function setLanguageFromQueryString() {
		$this->sysLanguageUid = (int)GeneralUtility::_GP('L');
	}

	/**
	 * Sets variables after the decoding.
	 *
	 * @param UrlCacheEntry $cacheEntry
	 */
	protected function setRequestVariables(UrlCacheEntry $cacheEntry) {
		if ($cacheEntry) {
			$requestVariables = $cacheEntry->getRequestVariables();
			$requestVariables['id'] = $cacheEntry->getPageId();
			$_SERVER['QUERY_STRING'] = $this->createQueryString($requestVariables);

			// Setting info in TSFE
			$this->caller->mergingWithGetVars($this->makeRealPhpArrayFromRequestVars($requestVariables));
			$this->caller->id = $cacheEntry->getPageId();

			if ($this->mimeType) {
				header('Content-type: ' . $this->mimeType);
				$this->mimeType = null;
			}
		}
	}

	/**
	 * Obtains speaking URI from the site script.
	 *
	 * @return void
	 */
	protected function setSpeakingUriFromSiteScript() {
		$this->speakingUri = ltrim($this->siteScript, '/');

		// Call hooks
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'] as $userFunc) {
				$hookParams = array(
					'pObj' => $this,
					'params' => array(),
					'URL' => &$this->speakingUri,
				);
				GeneralUtility::callUserFunction($userFunc, $hookParams, $this);
			}
		}
	}

	/**
	 * Throws a 404 error with the corresponding message.
	 *
	 * @param string $errorMessage
	 * @return void
	 */
	protected function throw404($errorMessage) {
		// TODO Write to our own error log here
		$this->caller->pageNotFoundAndExit($errorMessage);
	}
}
