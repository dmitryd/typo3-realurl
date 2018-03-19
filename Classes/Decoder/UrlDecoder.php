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
namespace DmitryDulepov\Realurl\Decoder;

use DmitryDulepov\Realurl\Cache\PathCacheEntry;
use DmitryDulepov\Realurl\Cache\UrlCacheEntry;
use DmitryDulepov\Realurl\Configuration\ConfigurationReader;
use DmitryDulepov\Realurl\EncodeDecoderBase;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * This class contains URL decoder for the RealURL. It is singleton because the
 * same instance must run in two different hooks.
 *
 * @package DmitryDulepov\Realurl\Decoder
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class UrlDecoder extends EncodeDecoderBase implements SingletonInterface {

	const REDIRECT_STATUS_HEADER = 'HTTP/1.0 301 TYPO3 RealURL Redirect';
	const REDIRECT_INFO_HEADER = 'X-TYPO3-RealURL-Info';

	/** @var bool */
	protected $appendedSlash = FALSE;

	/** @var TypoScriptFrontendController */
	protected $caller;

	/**
	 * This attribute keeps a detected language id for the speaking URL. Firsts,
	 * if _DOMAINS configuration has L parameter, it's value will be set to
	 * $_GET['L']. Than this attribute will be set from $_GET['L'] (if set).
	 * Finally preVar handling code will check for L after decoding and set
	 * this attribute either to the decoded value or to zero. This value can
	 * be null until preVars are decoded. After that it is either zero or
	 * the decoded language uid.
	 *
	 * @var int|null
	 */
	protected $detectedLanguageId = null;

	/** @var string */
	protected $disallowedDoktypes;

	/**
	 * Indicates that the path is expired but we could not redirect because
	 * non-expired path is missing from the path cache. In such case we do not
	 * cache the entry in the URL cache to force resolving of the path when
	 * the current URL is fetched.
	 *
	 * @var bool
	 */
	protected $isExpiredPath = FALSE;

	/**
	 * Holds information about expired path for the SEO redirect.
	 *
	 * @var string
	 */
	protected $expiredPath = '';

	/** @var string */
	protected $mimeType = '';

	/**
	 * Contains a mount point starting pid for the current branch. Zero means
	 * "no mount point in the path". This variable will direct the decoder to
	 * continue page look up from this branch of tree.
	 *
	 * @var int
	 */
	protected $mountPointStartPid = 0;

	/**
	 * Contains a generated $_GET['MP'] for the currently decoded branch.
	 *
	 * @var string
	 */
	protected $mountPointVariable = '';

	/**
	 * This variable is set to the speaking path only if he decoding has to run.
	 *
	 * @var string
	 */
	protected $originalPath;

	/** @var string */
	protected $savedErrorHandler = '';

	/** @var string */
	protected $siteScript;

	/** @var string */
	protected $speakingUri;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		parent::__construct();
		$this->siteScript = GeneralUtility::getIndpEnv('TYPO3_SITE_SCRIPT');
	}
	
	/**
	 * Returns $this->rootPageId. This can be used in hooks.
	 *
	 * @return int
	 */
	public function getRootPageId() {
		return $this->rootPageId;
	}

	/**
	 * Decodes the URL. This function is called from \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController::checkAlternativeIdMethods()
	 *
	 * @param array $params
	 * @return void
	 */
	public function decodeUrl(array $params) {
		if ($this->canDecoderExecute()) {
			$this->caller = $params['pObj'];

			$this->initialize();
			$this->mergeGetVarsFromDomainsConfiguration();

			if ($this->isSpeakingUrl()) {
				$this->configuration->validate();
				$this->setSpeakingUriFromSiteScript();
				$this->callPreDecodeHooks($params);
				$this->checkMissingSlash();
				if ($this->speakingUri) {
					$this->setLanguageFromQueryString();
					$this->runDecoding();
				}
			}
		}
	}

	/**
	 * Calls user-defined hooks.
	 *
	 * @param array $params
	 */
	protected function callPreDecodeHooks(array $params) {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'])) {
			foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['decodeSpURL_preProc'] as $userFunc) {
				$hookParams = array(
					'pObj' => &$this,
					'params' => $params,
					'URL' => &$this->speakingUri,
				);
				GeneralUtility::callUserFunction($userFunc, $hookParams, $this);
			}
		}
	}

	/**
	 * Checks if the decoder can execute.
	 *
	 * @return bool
	 */
	protected function canDecoderExecute() {
		return $this->isProperTsfe() && !$this->isInWorkspace();
	}

	/**
	 * Checks if the entry is expired and redirects to a non-expired entry.
	 *
	 * @param UrlCacheEntry $cacheEntry
	 */
	protected function checkExpiration(UrlCacheEntry $cacheEntry) {
		if ($cacheEntry->getExpiration() > 0) {
			$newerCacheEntry = $this->cache->getUrlFromCacheByOriginalUrl($cacheEntry->getRootPageId(), $cacheEntry->getOriginalUrl());
			if ($newerCacheEntry->getExpiration() === 0) {
				// Note: the above check will fail the first time the page is visited
				// because there will be no cache entry yet. However if the visited
				// page has a URL to itself, then the entry will be detected and
				// redirection happen starting from the second visit to the
				// expired url.
				if ($cacheEntry->getSpeakingUrl() !== $newerCacheEntry->getSpeakingUrl()) {
					$this->logger->notice(
						sprintf(
							'RealURL redirects expired URL "%s" to a newer URL "%s"',
							$cacheEntry->getSpeakingUrl(),
							$newerCacheEntry->getSpeakingUrl()
						)
					);
					@ob_end_clean();
					header(self::REDIRECT_STATUS_HEADER);
					header(self::REDIRECT_INFO_HEADER . ': redirecting expired URL to a fresh one');
					header('Content-length: 0');
					header('Connection: close');
					header('Location: ' . GeneralUtility::locationHeaderUrl($newerCacheEntry->getSpeakingUrl()));
					die;
				}
				else {
					// Got expired and non-expired entry for the same speaking url. Remove expired one.
					$this->cache->clearUrlCacheById($cacheEntry->getCacheId());
				}
			}
		}
	}

	/**
	 * Checks if the missing slash should be corrected.
	 *
	 * @return void
	 */
	protected function checkMissingSlash() {
		$originalUri = $this->speakingUri = rtrim($this->speakingUri, '?');

		$regexp = '~^([^\?]*[^/])(\?.*)?$~';
		if (preg_match($regexp, $this->speakingUri)) { // Only process if a slash is missing:
			$options = GeneralUtility::trimExplode(',', $this->configuration->get('init/appendMissingSlash'), true);
			if (in_array('ifNotFile', $options)) {
				if (!preg_match('/\/[^\/\?]+\.[^\/]+(\?.*)?$/', '/' . $this->speakingUri)) {
					$this->speakingUri = preg_replace($regexp, '\1/\2', $this->speakingUri);
					$this->appendedSlash = true;
				}
			} else {
				$this->speakingUri = preg_replace($regexp, '\1/\2', $this->speakingUri);
				$this->appendedSlash = true;
			}
			if ($this->appendedSlash && count($options) > 0) {
				foreach ($options as $option) {
					$matches = array();
					if (preg_match('/^redirect(\[(30[1237])\])?$/', $option, $matches)) {
						$code = count($matches) > 1 ? $matches[2] : 301;
						$status = 'HTTP/1.1 ' . $code . ' TYPO3 RealURL redirect';

						// Check path segment to be relative for the current site.
						// parse_url() does not work with relative URLs, so we use it to test
						if (!@parse_url($this->speakingUri, PHP_URL_HOST)) {
							$this->logger->notice(
								sprintf(
									'RealURL redirects from "%s" to "%s" due to missing slash',
									$originalUri,
									$this->speakingUri
								)
							);

							@ob_end_clean();
							header($status);
							header(self::REDIRECT_INFO_HEADER . ': redirect for missing slash');
							header('Content-length: 0');
							header('Connection: close');
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

		// First, test if there is an entry in cache for the alias
		if ($configuration['useUniqueCache']) {
			$cachedId = $this->getFromAliasCacheByAliasValue($configuration, $value);
			if (MathUtility::canBeInterpretedAsInteger($cachedId)) {
				$result = (int)$cachedId;
			}
		}

		if (!is_int($result) && $configuration['table'] !== 'pages') {
			// If no cached entry, look it up directly in the table. Note: this will
			// most likely fail. When encoding we convert alias field to a nice
			// looking URL segment, which usually looks differently from the field.
			// But this is the only thing we can do without fetching each record and
			// re-encoding the field to find the match.

			// Assemble list of fields to look up. This includes localization related fields
			$translationEnabled = FALSE;
			$fieldList = array();
			if ($configuration['languageGetVar'] && $configuration['transOrigPointerField'] && $configuration['languageField']) {
				$fieldList[] = 'uid';
				if ($configuration['table'] !== 'pages') {
					$fieldList[] = $configuration['transOrigPointerField'];
					$fieldList[] = $configuration['languageField'];
				}
				$translationEnabled = TRUE;
			}

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
	 * Find a page entry for the current segment and returns a PathCacheEntry for it.
	 *
	 * @param string $segment
	 * @param array $pages
	 * @param array $shortcutPages
	 * @return \DmitryDulepov\Realurl\Cache\PathCacheEntry | NULL
	 */
	protected function createPathCacheEntry($segment, array $pages, array &$shortcutPages) {
		$result = NULL;
		foreach ($pages as $page) {
			$originalMountPointPid = 0;
			if ($page['doktype'] == PageRepository::DOKTYPE_SHORTCUT) {
				// Value is not relevant, key is!
				$shortcutPages[$page['uid']] = true;
			}
			while ($page['doktype'] == PageRepository::DOKTYPE_MOUNTPOINT && $page['mount_pid_ol'] == 1) {
				$originalMountPointPid = $page['uid'];
				$page = $this->pageRepository->getPage($page['mount_pid']);
				if (!is_array($page)) {
					$this->tsfe->pageNotFoundAndExit('[realurl] Broken mount point at page with uid=' . $originalMountPointPid);
				}
			}
			$languageExceptionUids = (string)$this->configuration->get('pagePath/languageExceptionUids');
			if ($this->detectedLanguageId > 0 && !isset($page['_PAGES_OVERLAY']) && (empty($languageExceptionUids) || !GeneralUtility::inList($languageExceptionUids, $this->detectedLanguageId))) {
				$page = $this->pageRepository->getPageOverlay($page, (int)$this->detectedLanguageId);
			}
			foreach (self::$pageTitleFields as $field) {
				if (isset($page[$field]) && $page[$field] !== '' && $this->utility->convertToSafeString($page[$field], $this->separatorCharacter) === $segment) {
					$result = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
					/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $result */
					$result->setPageId((int)$page['uid']);
					if ($this->mountPointVariable !== '') {
						$result->setMountPoint($this->mountPointVariable);
					}
					if ($originalMountPointPid !== 0) {
						// Mount point with mount_pid_ol==1
						$this->mountPointVariable = $page['uid'] . '-' . $originalMountPointPid;
						// No $this->mountPointStartPid here because this is a substituted page
					}
					elseif ((int)$page['doktype'] === PageRepository::DOKTYPE_MOUNTPOINT) {
						$this->mountPointVariable = $page['mount_pid'] . '-' . $page['uid'];
						$this->mountPointStartPid = (int)$page['mount_pid'];
					}
					break 2;
				}
			}
		}

		return $result;
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

		if (count($pathSegments) > 0) {
			$allPostVars = array_filter((array)$this->configuration->get('fixedPostVars'));
			$postVars = $this->getConfigurationForPostVars($allPostVars, $pageId);

			$previousValue = '';
			foreach ($postVars as $postVarConfiguration) {
				if (!is_array($postVarConfiguration)) {
					continue;
				}
				$this->decodeSingleVariable($postVarConfiguration, $pathSegments, $requestVariables, $previousValue);
				if (empty($postVars['requireFullEvaluation']) && count($pathSegments) === 0) {
					break;
				}
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
		$savedRemainingPathSegments = array();
		$currentPid = 0;
		$remainingPathSegments = $pathSegments;

		$savedResult = NULL;
		$result = $this->searchPathInCache($remainingPathSegments);

		$allPathsAreExpired = $this->isExpiredPath && $result && !$this->expiredPath;
		if ($allPathsAreExpired) {
			// Special case: all paths are expired. We will try to unexpire the actual entry.
			$savedRemainingPathSegments = $remainingPathSegments;
			$remainingPathSegments = $pathSegments;

			$savedResult = $result;
			$result = NULL;
		}

		if (is_null($result)) {
			$result = $this->decodePathByOverride($remainingPathSegments);
			if (!is_null($result)) {
				$currentPid = $result->getPageId();
			}
		}

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
				if ($result) {
					$processedPathSegments = array_diff($pathSegments, $remainingPathSegments);
					$currentPid = $result->getPageId();
				} else {
					$processedPathSegments = array();
					$currentPid = $this->rootPageId;
				}
				$currentMountPointPid = 0;
				while ($currentPid !== 0 && count($remainingPathSegments) > 0) {
					$segment = array_shift($remainingPathSegments);
					if ($segment === '') {
						array_unshift($remainingPathSegments, $segment);
						break;
					}
					$saveToCache = true;
					$nextResult = $this->searchPages($currentPid, $segment, $saveToCache);
					if ($nextResult) {
						$result = $nextResult;
						if ($this->mountPointStartPid !== $currentMountPointPid) {
							$currentPid = $currentMountPointPid = $this->mountPointStartPid;
						}
						else {
							$currentPid = $result->getPageId();
						}
						$processedPathSegments[] = $segment;
						$result->setPagePath(implode('/', $processedPathSegments));
						if ($saveToCache) {
							// Path is valid so far, so we cache it
							$this->putToPathCache($result);
						}
					}
					elseif ($this->isPostVar($segment, $currentPid)) {
						// Not decoded, looks like a postVarSet. Put it back.
						array_unshift($remainingPathSegments, $segment);
						break;
					}
					else {
						// Not decoded, not a postVarSet, could be a fixedPostVar. Still put back and hope for the best!
						array_unshift($remainingPathSegments, $segment);
						break;
					}
				}
			}
			elseif ($currentPid === 0) {
				// Found a postVar on the rootPage
				$currentPid = $this->rootPageId;
			}
		}
		if ($allPathsAreExpired && !$result) {
			// We could not resolve the new path, use the expired one :(
			$result = $savedResult;
			$remainingPathSegments = $savedRemainingPathSegments;
		}
		if ($result && $this->expiredPath) {
			$startPosition = (int)strpos($this->speakingUri, $this->expiredPath);
			if ($startPosition !== FALSE) {
				$newUrl = substr($this->speakingUri, 0, $startPosition) .
					$result->getPagePath() .
					substr($this->speakingUri, $startPosition + strlen($this->expiredPath));
				$this->logger->debug(
					sprintf(
						'RealURL is redirecting from "%s" to "%s" because the former is expired',
						$this->speakingUri,
						$newUrl
					)
				);
				@ob_end_clean();
				header(self::REDIRECT_STATUS_HEADER);
				header(self::REDIRECT_INFO_HEADER . ': redirect for expired page path');
				header('Content-length: 0');
				header('Connection: close');
				header('Location: ' . GeneralUtility::locationHeaderUrl($newUrl));
				die;
			}
		}
		if ($result || (int)$currentPid === (int)$this->rootPageId) {
			$pathSegments = $remainingPathSegments;
		} else {
			$this->logger->error(
				sprintf(
					'Decoder was not able to decode "%s" and will throw a 404 now',
					implode('/', $pathSegments)
				)
			);
			$this->throw404('Cannot decode "' . implode('/', $pathSegments) . '"');
		}

		$pageId = 0;
		if ($result) {
			if ($result->getMountPoint()) {
				$this->mountPointVariable = $result->getMountPoint();
			}

			$pageId = $result->getPageId();
		} elseif ((int)$currentPid === (int)$this->rootPageId) {
			$pageId = $currentPid;
		}

		return $pageId;
	}

	/**
	 * Tries to decode the path by path override when the whole path is overriden.
	 *
	 * @param array $pathSegments
	 * @return PathCacheEntry
	 */
	protected function decodePathByOverride(array &$pathSegments) {
		$result = null;

		$possibleSegments = array();
		foreach ($pathSegments as $segment) {
			if ($this->isPostVar($segment)) {
				break;
			}
			$possibleSegments[] = $segment;
		}

		while (!empty($possibleSegments) && !$result) {
			$result = $this->searchPagesByPathOverride($possibleSegments);
			if (!$result) {
				array_pop($possibleSegments);
			}
		}
		if ($result) {
			$pathSegments = array_slice($pathSegments, count($possibleSegments));
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

		if (count($pathSegments) > 0) {
			$preVarsList = array_filter((array)$this->configuration->get('preVars'));

			$previousValue = '';
			foreach ($preVarsList as $preVarConfiguration) {
				$this->decodeSingleVariable($preVarConfiguration, $pathSegments, $requestVariables, $previousValue);
				if (count($pathSegments) == 0) {
					break;
				}
			}

			if (isset($requestVariables['L'])) {
				$this->detectedLanguageId = (int)$requestVariables['L'];
			}
		}
		if (is_null($this->detectedLanguageId)) {
			$this->detectedLanguageId = (int)$this->configuration->get('init/defaultLanguageUid');
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

		if (count($pathSegments) > 0) {
			$allPostVarSets = array_filter((array)$this->configuration->get('postVarSets'));
			$postVarSets = $this->getConfigurationForPostVars($allPostVarSets, $pageId);

			$previousValue = '';

			while (count($pathSegments) > 0) {
				$postVarSetKey = array_shift($pathSegments);
				if (!isset($postVarSets[$postVarSetKey]) || !is_array($postVarSets[$postVarSetKey])) {
					$this->handleNonExistingPostVarSet($pageId, $postVarSetKey, $pathSegments);
				} else {
					$postVarSetConfiguration = $postVarSets[$postVarSetKey];
					// Note: we do not support aliases for postVarSets!
					if (is_array($postVarSetConfiguration)) {
						foreach ($postVarSetConfiguration as $postVarConfiguration) {
							$this->decodeSingleVariable($postVarConfiguration, $pathSegments, $requestVariables, $previousValue);
						}
					}
				}
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
			// Always the last one!
			'decodeUrlParameterBlockUseAsIs',
		);

		if (count($pathSegments) > 0) {
			$getVarValue = count($pathSegments) > 0 ? array_shift($pathSegments) : '';
			if ($this->emptySegmentValue !== '' && $getVarValue === $this->emptySegmentValue) {
				$getVarValue = '';
			}
			$isFakeValue = false;
		}
		else {
			$getVarValue = '';
			$isFakeValue = true;
		}

		// TODO Possible hook here before any other function? Pass name, value, segments and config

		$handled = FALSE;
		if (!isset($varConfiguration['cond']) || $this->checkLegacyCondition($varConfiguration['cond'], $previousValue)) {
			foreach ($varProcessingFunctions as $varProcessingFunction) {
				if (isset($varConfiguration['GETvar'])) {
					if ($this->$varProcessingFunction($varConfiguration, $getVarValue, $requestVariables, $pathSegments, $isFakeValue)) {
						$previousValue = (string)end($requestVariables);
						$handled = TRUE;
						break;
					}
				}
				else {
					// TODO Log about bad configuration
				}
			}
		}
		if (!$handled && !$isFakeValue) {
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
					$this->throw404('Could not map alias "' . $value . '" to an id.');
				}
			} else {
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
	 * @param bool $isFakeValue
	 * @return bool
	 */
	protected function decodeUrlParameterBlockUsingNoMatch(array $configuration, $getVarValue, /** @noinspection PhpUnusedParameterInspection */ array &$requestVariables, array &$pathSegments, $isFakeValue) {
		$result = FALSE;
		if ($configuration['noMatch'] == 'bypass') {
			// If no match and "bypass" is set, then return the value to $pathSegments and break
			if (!$isFakeValue) {
				array_unshift($pathSegments, $getVarValue);
			}
			$result = TRUE;
		} elseif ($configuration['noMatch'] == 'null') {
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
	 * @param bool $isFakeValue
	 * @return bool
	 */
	protected function decodeUrlParameterBlockUsingUserFunc(array $configuration, $getVarValue, array &$requestVariables, array &$pathSegments, $isFakeValue) {
		$result = FALSE;

		if (isset($configuration['userFunc'])) {
			$parameters = array(
				'decodeAlias' => true,
				'isFakeValue' => $isFakeValue,
				'origValue' => $getVarValue,
				'pathParts' => &$pathSegments,
				'pObj' => $this,
				'sysLanguageUid' => $this->detectedLanguageId,
				'value' => $getVarValue,
				'setup' => $configuration
			);
			$value = GeneralUtility::callUserFunction($configuration['userFunc'], $parameters, $this);
			if (is_numeric($value) || is_string($value)) {
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
		$path = trim($path, '/');
		$pathSegments = $path ? explode('/', $path) : array();
		// Remember: urldecode(), not rawurldecode()!
		foreach($pathSegments as $id => $value) {
			$pathSegments[$id] = urldecode($value);
		}

		$requestVariables = array();

		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->handleFileName($pathSegments));
		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->getVarsFromDomainConfiguration());
		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->decodePreVars($pathSegments));
		$pageId = $this->decodePath($pathSegments);
		if ($this->mountPointVariable !== '') {
			$requestVariables['MP'] = $this->mountPointVariable;
		}
		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->decodeFixedPostVars($pageId, $pathSegments));
		ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $this->decodePostVarSets($pageId, $pathSegments));

		$this->mergeWithExistingGetVars($requestVariables);

		$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\UrlCacheEntry');
		/** @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry */
		$cacheEntry->setPageId($pageId);
		$cacheEntry->setRequestVariables($requestVariables);

		return $cacheEntry;
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
		return $this->cache->getUrlFromCacheBySpeakingUrl($this->rootPageId, $speakingUrl, $this->detectedLanguageId);
	}

	/**
	 * Searches a page below excluded pages and returns the PathCacheEntry if something was found.
	 *
	 * @param string $segment
	 * @param array $pages
	 * @param string $pagesEnableFields
	 * @param array $shortcutPages
	 * @return \DmitryDulepov\Realurl\Cache\PathCacheEntry | NULL
	 */
	protected function getPathCacheEntryAfterExcludedPages($segment, array $pages, $pagesEnableFields, array &$shortcutPages) {
		$ids = array();
		$result = null;
		$newPages = $pages;

		while ($newPages) {
			foreach ($newPages as $page) {
				while ($page['doktype'] == PageRepository::DOKTYPE_MOUNTPOINT && $page['mount_pid_ol'] == 1) {
					$originalUid = $page['uid'];
					$page = $this->pageRepository->getPage($page['mount_pid']);
					if (!is_array($page)) {
						$this->tsfe->pageNotFoundAndExit('[realurl] Broken mount point at page with uid=' . $originalUid);
					}
				}
				if ($page['tx_realurl_exclude']) {
					$ids[] = $page['uid'];
				}
			}
			if ($ids) {
				// No sorting here because pages can be on any level
				$children = $this->databaseConnection->exec_SELECTgetRows(
					'*', 'pages', 'pid IN (' . implode(',', $ids) . ')' .
					' AND doktype NOT IN (' . $this->disallowedDoktypes . ')' . $pagesEnableFields
				);
				$languageExceptionUids = (string)$this->configuration->get('pagePath/languageExceptionUids');
				if ($this->detectedLanguageId > 0 && (empty($languageExceptionUids) || !GeneralUtility::inList($languageExceptionUids, $this->detectedLanguageId))) {
					foreach ($children as $key => $child) {
						$children[$key] = $this->pageRepository->getPageOverlay($child, (int)$this->detectedLanguageId);
					}
				}

				$result = $this->createPathCacheEntry($segment, $children, $shortcutPages);
				if ($result) {
					break;
				}
				$newPages = $children;
				$ids = array();
			} else {
				break;
			}
		}
		return $result;
	}

	/**
	 * Parses the URL and validates the result. This function will strip possible
	 * query string from speaking URL (we only need to decode the speaking URL!)
	 *
	 * @return array
	 */
	protected function getUrlPath() {
		$urlPath = @parse_url('/' . ltrim($this->speakingUri, '/'), PHP_URL_PATH);
		if (!is_string($urlPath)) {
			$this->throw404('Current URL is invalid');
		}

		return ltrim($urlPath, '/');
	}

	/**
	 * Obtains variables from the domain confuguration.
	 *
	 * @return array
	 */
	protected function getVarsFromDomainConfiguration() {
		$requestVariables = array();

		$domainConfuguration = $this->configuration->get('domains');
		if (is_array($domainConfuguration) && isset($domainConfuguration['GETvars'])) {
			reset($domainConfuguration['GETvars']);
			$getVarName = key($domainConfuguration['GETvars']);
			$getVarValue = $domainConfuguration['GETvars'][$getVarName];
			$requestVariables[$getVarName] = $getVarValue;
			if ($getVarName === 'L') {
				$this->detectedLanguageId = (int)$getVarValue;
			}
		}

		return $requestVariables;
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
				if (!$this->handleFileNameMappingToGetVar($fileNameSegment, $getVars, $putBack)) {
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
					if ($putBack && count($urlParts) === 0 && $fileNameSegment === 'index') {
						$putBack = false;
					}
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
	 * @param bool $putBack
	 * @return bool
	 */
	protected function handleFileNameMappingToGetVar(&$fileNameSegment, array &$getVars, &$putBack) {
		$result = false;
		if ($fileNameSegment) {
			$fileNameConfiguration = $this->configuration->get('fileName/index/' . $fileNameSegment);
			if (is_array($fileNameConfiguration)) {
				$result = true;
				$putBack = false;
				if (isset($fileNameConfiguration['keyValues'])) {
					$getVars = $fileNameConfiguration['keyValues'];
				}
			}
			else {
				list($fileName, $extension) = GeneralUtility::revExplode('.', $fileNameSegment, 2);
				$fileNameConfiguration = $this->configuration->get('fileName/index/.' . $extension);
				if (is_array($fileNameConfiguration)) {
					$result = true;
					$putBack = true;
					$fileNameSegment = $fileName;
					if (isset($fileNameConfiguration['keyValues'])) {
						$getVars = $fileNameConfiguration['keyValues'];
					}
				}
			}
		}

		return $result;
	}

	/**
	 * Handles non-existing postVarSet according to configuration.
	 *
	 * @param int $pageId
	 * @param string $postVarSetKey
	 * @param array $pathSegments
	 */
	protected function handleNonExistingPostVarSet($pageId, $postVarSetKey, array &$pathSegments) {
		$failureMode = $this->configuration->get('init/postVarSet_failureMode');
		if ($failureMode == 'redirect_goodUpperDir') {
			$nonProcessedArray = array($postVarSetKey) + $pathSegments;
			$badPathPart = implode('/', $nonProcessedArray);
			$badPathPartLength = strlen($badPathPart);
			if (strpos($badPathPart, '/') !== FALSE || $badPathPartLength === 0) {
				// There are two or more adjacent slashes in the URL, e.g. "good/good//index.html" or "good/good//bad///index.html"
				$goodPath = $this->originalPath;
				// Remove multiple slashes
				do {
					$goodPath = str_replace('//', '/', $goodPath, $replaced);
				} while ($replaced > 0);
			} else {
				// There is a unrecognized postVarSetKey
				$badPathPartPos = strrpos($this->originalPath, $badPathPart);
				if ($badPathPartPos > 0) {
					// We also want to get rid of one slash
					$badPathPartPos--;
					$badPathPartLength++;
				}
				$goodPath = substr($this->originalPath, 0, $badPathPartPos) . substr($this->originalPath, $badPathPartPos + $badPathPartLength);
			}
			@ob_end_clean();
			header(self::REDIRECT_STATUS_HEADER);
			header(self::REDIRECT_INFO_HEADER  . ': postVarSet_failureMode redirect for ' . $postVarSetKey);
			header('Content-length: 0');
			header('Connection: close');
			header('Location: ' . GeneralUtility::locationHeaderUrl($goodPath));
			exit;
		} elseif ($failureMode == 'ignore') {
			$pathSegments = array();
		} else {
			$this->throw404('Segment "' . $postVarSetKey . '" was not a keyword for a postVarSet as expected on page with id=' . $pageId . '.');
		}
	}

	/**
	 * Initializes the decoder.
	 *
	 * @throws \Exception
	 */
	protected function initialize() {
		parent::initialize();

		$this->disallowedDoktypes = PageRepository::DOKTYPE_RECYCLER;
	}

	/**
	 * Initializes configuration reader.
	 */
	protected function initializeConfiguration() {
		$this->configuration = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Configuration\\ConfigurationReader', ConfigurationReader::MODE_DECODE);
	}

	/**
	 * Checks if the current root page is inside the rootline
	 * of the given page
	 *
	 * @param int $pageUid
	 * @return boolean
	 */
	protected function isPageInRootlineOfRootPage($pageUid) {
		$result = false;

		$rootLineUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Utility\\RootlineUtility', $pageUid);
		/** @var \TYPO3\CMS\Core\Utility\RootlineUtility $rootLineUtility */
		$rootLine = $rootLineUtility->get();

		foreach ((array)$rootLine as $page) {
			if ($page['uid'] == $this->rootPageId) {
				$result = true;
				break;
			}
		}

		return $result;
	}

	/**
	 * Checks if the given segment is a name of the postVar.
	 *
	 * @param string $segment
	 * @param int $pageId
	 * @return bool
	 */
	protected function isPostVar($segment, $pageId = 0) {
		$result = false;

		$postVarSets = null;
		if ($pageId > 0) {
			$postVarSets = $this->configuration->get('postVarSets/' . $pageId);
		}
		if (!is_array($postVarSets)) {
			$postVarSets = $this->configuration->get('postVarSets/_DEFAULT');
		}
		if (is_array($postVarSets)) {
			$result = isset($postVarSets[$segment]);
		}

		return $result;
	}

	/**
	 * Checks if TSFE is initialized correctly.
	 *
	 * @return bool
	 */
	protected function isProperTsfe() {
		return ($this->tsfe instanceof TypoScriptFrontendController);
	}

	/**
	 * Checks if the current URL is a speaking URL.
	 *
	 * @return bool
	 */
	protected function isSpeakingUrl() {
		return $this->siteScript &&
			substr($this->siteScript, 0, 9) !== 'index.php' &&
			substr($this->siteScript, 0, 1) !== '?' &&
			$this->siteScript !== 'favicon.ico' &&
			(!$this->configuration->get('init/respectSimulateStaticURLs') || !preg_match('/^[a-z0-9\-]+\.([a-z0-9_\-]+)(\.\d+)?\.html/i', $this->siteScript))
		;
	}

	/**
	 * Converts array('tx_ext[var1]' => 1, 'tx_ext[var2]' => 2) to array('tx_ext' => array('var1' => 1, 'var2' => 2)).
	 *
	 * @param array $requestVariables
	 * @return array
	 */
	protected function makeRealPhpArrayFromRequestVars(array $requestVariables) {
		$result = array();

		parse_str($this->createQueryStringFromParameters($requestVariables), $result);
		$this->fixBracketsAfterParseStr($result);

		return $result;
	}

	/**
	 * Merges $_GET from domains configuration.
	 *
	 * @return void
	 */
	protected function mergeGetVarsFromDomainsConfiguration() {
		// Convert the configuration into an $_GET-"friendly" format
		$getVarsToSet = $this->makeRealPhpArrayFromRequestVars($this->configuration->getGetVarsToSet());
		if (count($getVarsToSet) > 0) {
			// Overwrite with $_GET-params that $_GET-parmas have a "higher" priority
			$getVars = GeneralUtility::_GET();
			if (!is_array($getVars)) {
				$getVars = array();
			}
			ArrayUtility::mergeRecursiveWithOverrule($getVars, $getVarsToSet, true, true, false);

			// Store the "new" $_GET-params back
			GeneralUtility::_GETset($getVars);
		}
	}

	/**
	 * Merges generated request variables with existing $_GET variables. Those in
	 * $_GET override generated.
	 *
	 * @param array $requestVariables
	 * @return void
	 */
	protected function mergeWithExistingGetVars(array &$requestVariables) {
		if (count($_GET) > 0) {
			$flatGetArray = $this->parseQueryStringParameters($this->createQueryStringFromParameters(GeneralUtility::_GET()));
			ArrayUtility::mergeRecursiveWithOverrule($requestVariables, $flatGetArray);
		}
	}

	/**
	 * Parses query string to a set of key/value.
	 *
	 * @param string $queryString
	 * @return array
	 */
	protected function parseQueryStringParameters($queryString) {
		$urlParameters = array();

		$parts = GeneralUtility::trimExplode('&', $queryString, true);
		foreach ($parts as $part) {
			list($parameter, $value) = explode('=', $part);
			// Remember: urldecode(), not rawurldecode()!
			$urlParameters[urldecode($parameter)] = urldecode($value);
		}

		return $urlParameters;
	}

	/**
	 * Adds data to the path cache. Cache ntry should have page path, language id and page id set.
	 *
	 * @param PathCacheEntry $newCacheEntry
	 * @return void
	 */
	protected function putToPathCache(PathCacheEntry $newCacheEntry) {
		$pagePath = $newCacheEntry->getPagePath();
		$cacheEntry = $this->cache->getPathFromCacheByPagePath($this->rootPageId, $this->detectedLanguageId, $newCacheEntry->getMountPoint(), $pagePath);
		if (!$cacheEntry) {
			$cacheEntry = $newCacheEntry;
			$cacheEntry->setRootPageId($this->rootPageId);
			$cacheEntry->setLanguageId($this->detectedLanguageId);
		}
		if ($cacheEntry->getExpiration() !== 0) {
			$cacheEntry->setExpiration(0);
		}

		// https://github.com/dmitryd/typo3-realurl/issues/578
		$pathSegments = explode('/', $pagePath);
		array_walk($pathSegments, function(&$segment) {
			$segment = rawurlencode($this->utility->convertToSafeString($segment, $this->separatorCharacter));
		});
		$pagePath = implode('/', $pathSegments);

		$cacheEntry->setPagePath($pagePath);
		$this->cache->putPathToCache($cacheEntry);
	}

	/**
	 * Contains the actual decoding logic after $this->speakingUri is set.
	 *
	 * @return void
	 */
	protected function runDecoding() {
		$urlPath = $this->getUrlPath();

		$cacheEntry = $this->getFromUrlCache($this->speakingUri);
		if (!$cacheEntry) {
			$this->originalPath = $urlPath;
			$cacheEntry = $this->doDecoding($urlPath);
			// Note the newly created cache entry is not saved because it is unsafe!
			// The user can supply any number of free form parameters and those
			// can get to the cache. On the other hand we cannot store URLs
			// without parameters because those can be fully legal and entries
			// without parameters will be useless.
			$this->logger->notice(
				sprintf(
					'URL "%s" was not found in RealURL cache when decoding.',
					$urlPath
				)
			);
		}
		$this->checkExpiration($cacheEntry);
		$this->setRequestVariables($cacheEntry);
	}

	/**
	 * Searches the given path in pages table.
	 *
	 * @param string $path
	 * @return PathCacheEntry|null
	 */
	protected function searchForPathOverrideInPages($path) {
		$result = null;

		$rows = $this->databaseConnection->exec_SELECTgetRows('uid', 'pages',
			'tx_realurl_pathoverride=1 AND ' .
				'tx_realurl_pathsegment=' . $this->databaseConnection->fullQuoteStr($path, 'pages') .
				$this->pageRepository->enableFields('pages', 1, array('fe_group' => true)),
				'', 'sorting'
		);
		foreach ($rows as $row) {
			if ($this->isPageInRootlineOfRootPage((int)$row['uid'])) {
				// Found it!
				$result = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
				/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $result */
				$result->setPageId((int)$row['uid']);
				$result->setPagePath($path);
				$result->setRootPageId($this->rootPageId);
				break;
			}
		}

		return $result;
	}

	/**
	 * Searches the given path in page language overlays.
	 *
	 * @param string $path
	 * @return PathCacheEntry|null
	 */
	protected function searchForPathOverrideInPagesLanguageOverlay($path) {
		$result = null;

		$rows = $this->databaseConnection->exec_SELECTgetRows('pages.uid AS uid',
			'pages_language_overlay, pages',
			'pages_language_overlay.pid=pages.uid AND ' .
				'pages_language_overlay.sys_language_uid=' . (int)$this->detectedLanguageId . ' AND ' .
				'pages.tx_realurl_pathoverride=1 AND ' .
				'pages_language_overlay.tx_realurl_pathsegment=' . $this->databaseConnection->fullQuoteStr($path, 'pages_language_overlay') .
				$this->pageRepository->enableFields('pages_language_overlay', 1, array('fe_group' => true)) .
				$this->pageRepository->enableFields('pages', 1, array('fe_group' => true))
		);
		foreach ($rows as $row) {
			if ($this->isPageInRootlineOfRootPage((int)$row['uid'])) {
				// Found it!
				$result = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
				/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $result */
				$result->setLanguageId($this->detectedLanguageId);
				$result->setPageId((int)$row['uid']);
				$result->setPagePath($path);
				$result->setRootPageId($this->rootPageId);
				break;
			}
		}

		return $result;
	}

	/**
	 * Searches pages for the match to the segment
	 *
	 * @param int $currentPid
	 * @param string $segment
	 * @param bool $saveToCache
	 * @return PathCacheEntry
	 */
	protected function searchPages($currentPid, $segment, &$saveToCache) {
		$result = null;
		$resultForCache = null;

		$shortcutPages = array();
		$pagesEnableFields = $this->pageRepository->enableFields('pages', 1, array('fe_group' => true));
		$pages = $this->databaseConnection->exec_SELECTgetRows('*', 'pages', 'pid=' . (int)$currentPid .
			' AND doktype NOT IN (' . $this->disallowedDoktypes . ')' . $pagesEnableFields,
			'', 'sorting'
		);
		$result = $this->createPathCacheEntry($segment, $pages, $shortcutPages);
		if (!$result) {
			$result = $this->getPathCacheEntryAfterExcludedPages($segment, $pages, $pagesEnableFields, $shortcutPages);
		}

		if ($result && isset($shortcutPages[$result->getPageId()])) {
			$saveToCache = false;
		}

		return $result;
	}

	/**
	 * Searches for a match in tx_realurl_pathsegment with override option.
	 *
	 * @param array $possibleSegments
	 * @return PathCacheEntry|null
	 */
	protected function searchPagesByPathOverride(array $possibleSegments) {
		$result = null;

		$path = implode('/', $possibleSegments);
		$languageExceptionUids = (string)$this->configuration->get('pagePath/languageExceptionUids');
		if ($this->detectedLanguageId > 0 && (empty($languageExceptionUids) || !GeneralUtility::inList($languageExceptionUids, $this->detectedLanguageId))) {
			$result = $this->searchForPathOverrideInPagesLanguageOverlay($path);
		}
		if (!$result) {
			$result = $this->searchForPathOverrideInPages($path);
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
		/** @var PathCacheEntry $result */
		$removedSegments = array();

		do {
			// https://github.com/dmitryd/typo3-realurl/issues/578
			$pathSegmentsCopy = $pathSegments;
			array_walk($pathSegmentsCopy, function(&$segment) {
				$segment = rawurlencode($this->utility->convertToSafeString($segment, $this->separatorCharacter));
			});
			$path = implode('/', $pathSegmentsCopy);

			// Since we know nothing about mount point at this stage, we exclude it from search by passing null as the second argument
			$cacheEntry = $this->cache->getPathFromCacheByPagePath($this->rootPageId, $this->detectedLanguageId, null, $path);
			if ($cacheEntry) {
				if ((int)$cacheEntry->getExpiration() !== 0) {
					$this->isExpiredPath = TRUE;
					$nonExpiredCacheEntry = $this->cache->getPathFromCacheByPageId($cacheEntry->getRootPageId(), $cacheEntry->getLanguageId(), $cacheEntry->getPageId(), $cacheEntry->getMountPoint());
					if ($nonExpiredCacheEntry) {
						$this->expiredPath = $cacheEntry->getPagePath();
						$cacheEntry = $nonExpiredCacheEntry;
					}
				}
				$result = $cacheEntry;
			} else {
				if (count($pathSegments) > 0) {
					array_unshift($removedSegments, array_pop($pathSegments));
				}
			}
		} while (is_null($result) && count($pathSegments) > 0);
		$pathSegments = $removedSegments;

		return $result;
	}

	/**
	 * Sets current language from the query string variable ('L').
	 *
	 * @return void
	 */
	protected function setLanguageFromQueryString() {
		$language = GeneralUtility::_GP('L');
		if (MathUtility::canBeInterpretedAsInteger($language)) {
			$this->detectedLanguageId = (int)$language;
		}
	}

	/**
	 * Sets variables after the decoding.
	 *
	 * @param UrlCacheEntry $cacheEntry
	 */
	protected function setRequestVariables(UrlCacheEntry $cacheEntry) {
		if ($cacheEntry) {
			$requestVariables = $cacheEntry->getRequestVariables();
			$this->restoreIgnoredUrlParameters($requestVariables);
			$requestVariables['id'] = $cacheEntry->getPageId();
			$_SERVER['QUERY_STRING'] = $this->createQueryStringFromParameters($requestVariables);

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
		$this->speakingUri = $this->removeIgnoredParametersFromURL(ltrim($this->siteScript, '/'));
	}

	/**
	 * Throws a 404 error with the corresponding message.
	 *
	 * @param string $errorMessage
	 * @return void
	 */
	protected function throw404($errorMessage) {
		// TODO Write to our own error log here

		// Set language to allow localized error pages
		if (MathUtility::canBeInterpretedAsInteger($this->detectedLanguageId)) {
			$_GET['L'] = $this->detectedLanguageId;
		}

		$this->caller->pageNotFoundAndExit($errorMessage);
	}
}
