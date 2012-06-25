<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2004 Martin Poelstra (martin@beryllium.net)
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
 * Class for translating page ids to/from path strings (Speaking URLs)
 *
 * $Id$
 *
 * @author	Martin Poelstra <martin@beryllium.net>
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   75: class tx_realurl_advanced
 *  105:     function main(&$params, $ref)
 *
 *              SECTION: "path" ID-to-URL methods
 *  146:     function IDtoPagePath(&$paramKeyValues, &$pathParts)
 *  242:     function updateURLCache($id, $mpvar, $lang, $cached_pagepath = '')
 *  289:     function IDtoPagePathSegments($id, $mpvar, $langID)
 *  347:     function rootLineToPath($rl, $lang)
 *
 *              SECTION: URL-to-ID methods
 *  416:     function pagePathtoID(&$pathParts)
 *  546:     function findIDByURL(&$urlParts)
 *  581:     function searchTitle($pid, $mpvar, &$urlParts, $currentIdMp = '')
 *  635:     function searchTitle_searchPid($searchPid, $title)
 *
 *              SECTION: Helper functions
 *  740:     function encodeTitle($title)
 *  775:     function makeExpirationTime($offsetFromNow = 0)
 *  790:     function getLanguageVar()
 *
 * TOTAL FUNCTIONS: 12
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * Class for translating page ids to/from path strings (Speaking URLs)
 *
 * @author	Martin Poelstra <martin@beryllium.net>
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 * @package realurl
 * @subpackage tx_realurl
 */
class tx_realurl_advanced {

	/**
	 * t3lib_page object for finding rootline on the fly
	 *
	 * @var	t3lib_pageSelect
	 */
	protected $sysPage;

	/**
	 * Reference to parent object
	 *
	 * @var	tx_realurl
	 */
	protected $pObj;

	/**
	 * Class configuration
	 *
	 * @var array $conf
	 */
	protected $conf;

	/**
	 * Configuration for the current domain
	 *
	 * @var array
	 */
	protected $extConf;

	/**
	 * Main function, called for both encoding and deconding of URLs.
	 * Based on the "mode" key in the $params array it branches out to either decode or encode functions.
	 *
	 * @param	array		Parameters passed from parent object, "tx_realurl". Some values are passed by reference! (paramKeyValues, pathParts and pObj)
	 * @param	tx_realurl		Copy of parent object. Not used.
	 * @return	mixed		Depends on branching.
	 */
	public function main(array $params, tx_realurl $parent) {
		/* @var $ref tx_realurl */

		// Setting internal variables:
		$this->pObj = $parent;
		$this->conf = $params['conf'];
		$this->extConf = $this->pObj->getConfiguration();

		// Branching out based on type:
		$result = false;
		switch ((string)$params['mode']) {
			case 'encode':
				$result = $this->IDtoPagePath($params['paramKeyValues'], $params['pathParts']);
				break;
			case 'decode':
				$result = $this->pagePathtoID($params['pathParts']);
				break;
		}
		return $result;
	}

	/*******************************
	 *
	 * "path" ID-to-URL methods
	 *
	 ******************************/

	/**
	 * Retrieve the page path for the given page-id.
	 * If the page is a shortcut to another page, it returns the page path to the shortcutted page.
	 * MP get variables are also encoded with the page id.
	 *
	 * @param	array		GETvar parameters containing eg. "id" key with the page id/alias (passed by reference)
	 * @param	array		Path parts array (passed by reference)
	 * @return	void
	 * @see encodeSpURL_pathFromId()
	 */
	protected function IDtoPagePath(array &$paramKeyValues, &$pathParts) {

		$pageId = $paramKeyValues['id'];
		unset($paramKeyValues['id']);

		$mpvar = $paramKeyValues['MP'];
		unset($paramKeyValues['MP']);

		// Convert a page-alias to a page-id if needed
		$pageId = $this->resolveAlias($pageId);
		$pageId = $this->resolveShortcuts($pageId, $mpvar);
		if ($pageId) {
			// Set error if applicable.
			if ($this->isExcludedPage($pageId)) {
				$this->pObj->setEncodeError();
			}
			else {
				$lang = $this->getLanguageVar($paramKeyValues);
				$cachedPagePath = $this->getPagePathFromCache($pageId, $lang, $mpvar);

				if ($cachedPagePath !== false) {
					$pagePath = $cachedPagePath;
				}
				else {
					$pagePath = $this->createPagePathAndUpdateURLCache($pageId,
						$mpvar, $lang, $cachedPagePath);
				}

				// Set error if applicable.
				if ($pagePath === '__ERROR') {
					$this->pObj->setEncodeError();
				}
				else {
					$this->mergeWithPathParts($pathParts, $pagePath);
				}
			}
		}
	}

	/**
	 * If page id is not numeric, try to resolve it from alias.
	 *
	 * @param mixed pageId
	 * @param int
	 */
	private function resolveAlias($pageId) {
		if (!is_numeric($pageId)) {
			$pageId = $GLOBALS['TSFE']->sys_page->getPageIdFromAlias($pageId);
		}
		return $pageId;
	}

	/**
	 * Checks if the page should be excluded from processing.
	 *
	 * @param int $pageId
	 * @return boolean
	 */
	protected function isExcludedPage($pageId) {
		return $this->conf['excludePageIds'] && t3lib_div::inList($this->conf['excludePageIds'], $pageId);
	}

	/**
	 * Merges the path with existing path parts and creates an array of path
	 * segments.
	 *
	 * @param array $pathParts
	 * @param string $pagePath
	 * @return void
	 */
	protected function mergeWithPathParts(array &$pathParts, $pagePath) {
		if (strlen($pagePath)) {
			$pagePathParts = explode('/', $pagePath);
			$pathParts = array_merge($pathParts, $pagePathParts);
		}
	}

	/**
	 * Resolves shortcuts if necessary and returns the final destination page id.

	 * @param int pageId
	 * @return mixed false if not found or int
	 */
	protected function resolveShortcuts($pageId, &$mpvar) {
		$disableGroupAccessCheck = ($GLOBALS['TSFE']->config['config']['typolinkLinkAccessRestrictedPages'] ? true : false);
		$loopCount = 20; // Max 20 shortcuts, to prevent an endless loop
		while ($pageId > 0 && $loopCount > 0) {
			$loopCount--;

			$page = $GLOBALS['TSFE']->sys_page->getPage($pageId, $disableGroupAccessCheck);
			if (!$page) {
				$pageId = false;
				break;
			}

			if (!$this->conf['dontResolveShortcuts'] && $page['doktype'] == 4) {
				// Shortcut
				$pageId = $this->resolveShortcut($page, $disableGroupAccessCheck, array(), $mpvar);
			}
			else {
				$pageId = $page['uid'];
				break;
			}
		}
		return $pageId;
	}

	/**
	 * Retireves page path from cache.
	 *
	 * @return mixed Page path (string) or false if not found
	 */
	private function getPagePathFromCache($pageid, $lang, $mpvar) {
		$result = false;
		if (!$this->conf['disablePathCache']) {
			list($cachedPagePath) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pagepath', 'tx_realurl_pathcache',
				'page_id=' . intval($pageid) .
				' AND language_id=' . intval($lang) .
				' AND rootpage_id=' . intval($this->conf['rootpage_id']) .
				' AND mpvar=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($mpvar, 'tx_realurl_pathcache') .
				' AND expire=0', '', '', 1);
			if (is_array($cachedPagePath)) {
				$result = $cachedPagePath['pagepath'];
			}
		}
		return $result;
	}

	/**
	 * Creates the path and inserts into the path cache (if enabled).
	 *
	 * @param	integer		Page id
	 * @param	string		MP variable string
	 * @param	integer		Language uid
	 * @param	string		If set, then a new entry will be inserted ONLY if it is different from $cachedPagePath
	 * @return	string		The page path
	 */
	protected function createPagePathAndUpdateURLCache($id, $mpvar, $lang, $cachedPagePath = '') {

		$pagePathRec = $this->getPagePathRec($id, $mpvar, $lang);
		if (!$pagePathRec) {
			return '__ERROR';
		}

		$this->updateURLCache($id, $cachedPagePath, $pagePathRec['pagepath'],
			$pagePathRec['langID'], $pagePathRec['rootpage_id'], $mpvar);

		return $pagePathRec['pagepath'];
	}

	/**
	 * Adds a new entry to the path cache.
	 *
	 * @param int $pageId
	 * @param int $cachedPagePath
	 * @param int $pagePath
	 * @param int $langId
	 * @param int $rootPageId
	 * @param string $mpvar
	 * @return void
	 */
	private function updateURLCache($pageId, $cachedPagePath, $pagePath, $langId, $rootPageId, $mpvar) {
		$canCachePaths = !$this->conf['disablePathCache'] && !$this->pObj->isBEUserLoggedIn();
		$newPathDiffers = ((string)$pagePath !== (string)$cachedPagePath);
		if ($canCachePaths && $newPathDiffers) {
			$cacheCondition = 'page_id=' . intval($pageId) .
				' AND language_id=' . intval($langId) .
				' AND rootpage_id=' . intval($rootPageId) .
				' AND mpvar=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($mpvar, 'tx_realurl_pathcache');

			$GLOBALS['TYPO3_DB']->sql_query('START TRANSACTION');

			$this->removeExpiredPathCacheEntries();
			$this->setExpirationOnOldPathCacheEntries($pagePath, $cacheCondition);
			$this->addNewPagePathEntry($pagePath, $cacheCondition, $pageId, $mpvar, $langId, $rootPageId);

			$GLOBALS['TYPO3_DB']->sql_query('COMMIT');
		}
	}


	/**
	 * Obtains a page path record.
	 *
	 * @param int $id
	 * @param string $mpvar
	 * @param int $lang
	 * @return mixed array(pagepath,langID,rootpage_id) if successful, false otherwise
	 */
	protected function getPagePathRec($id, $mpvar, $lang) {
		static $IDtoPagePathCache = array();

		$cacheKey = $id . '.' . $mpvar . '.' . $lang;
		if (isset($IDtoPagePathCache[$cacheKey])) {
			$pagePathRec = $IDtoPagePathCache[$cacheKey];
		}
		else {
			$pagePathRec = $this->IDtoPagePathThroughOverride($id, $mpvar, $lang);
			if (!$pagePathRec) {
				// Build the new page path, in the correct language
				$pagePathRec = $this->IDtoPagePathSegments($id, $mpvar, $lang);
			}
			$IDtoPagePathCache[$cacheKey] = $pagePathRec;
		}

		return $pagePathRec;
	}


	/**
	 * Checks if the page has a path to override.
	 *
	 * @param int $id
	 * @param string $mpvar
	 * @param int $lang
	 * @return array
	 */
	protected function IDtoPagePathThroughOverride($id, $mpvar, $lang) {
		$result = false;
		$page = $this->getPage($id, $lang);
		if ($page['tx_realurl_pathoverride']) {
			if ($page['tx_realurl_pathsegment']) {
				$result = array(
					'pagepath' => trim($page['tx_realurl_pathsegment'], '/'),
					'langID' => intval($lang),
					// TODO Might be better to fetch root line here to process mount
					// points and inner subdomains correctly.
					'rootpage_id' => intval($this->conf['rootpage_id'])
				);
			}
			else {
				$message = sprintf('Path override is set for page=%d (language=%d) but no segment defined!',
					$id, $lang);
				t3lib_div::sysLog($message, 'realurl', 3);
				$this->pObj->devLog($message, false, 2);
			}
		}
		return $result;
	}

	/**
	 * Obtains a page and its translation (if necessary). The reason to use this
	 * function instead of $GLOBALS['TSFE']->sys_page->getPage() is that
	 * $GLOBALS['TSFE']->sys_page->getPage() always applies a language overlay
	 * (even if we have a different language id).
	 *
	 * @param int $pageId
	 * @param int $languageId
	 * @return mixed Page row or false if not found
	 */
	protected function getPage($pageId, $languageId) {
		$condition = 'uid=' . intval($pageId) . $GLOBALS['TSFE']->sys_page->where_hid_del;
		$disableGroupAccessCheck = ($GLOBALS['TSFE']->config['config']['typolinkLinkAccessRestrictedPages'] ? true : false);
		if (!$disableGroupAccessCheck) {
			$condition .= $GLOBALS['TSFE']->sys_page->where_groupAccess;
		}
		list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('*', 'pages',
			$condition);
		if (is_array($row) && $languageId > 0) {
			$row = $GLOBALS['TSFE']->sys_page->getPageOverlay($row, $languageId);
		}
		return $row;
	}

	/**
	 * Adds a new entry to the path cache
	 *
	 * @param string $currentPagePath
	 * @param string $pathCacheCondition
	 * @param int $pageId
	 * @param string $mpvar
	 * @param int $langId
	 * @return void
	 */
	protected function addNewPagePathEntry($currentPagePath, $pathCacheCondition, $pageId, $mpvar, $langId, $rootPageId) {
		$condition = $pathCacheCondition . ' AND pagepath=' .
			$GLOBALS['TYPO3_DB']->fullQuoteStr($currentPagePath, 'tx_realurl_pathcache');
		list($count) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('COUNT(*) AS t',
			'tx_realurl_pathcache', $condition);
		if ($count['t'] == 0) {
			$insertArray = array(
				'page_id' => $pageId,
				'language_id' => $langId,
				'pagepath' => $currentPagePath,
				'expire' => 0,
				'rootpage_id' => $rootPageId,
				'mpvar' => $mpvar
			);
			$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_pathcache', $insertArray);
		}
	}

	/**
	 * Sets expiration time for the old path cache entries
	 *
	 * @param string $currentPagePath
	 * @param string $pathCacheCondition
	 * @return void
	 */
	protected function setExpirationOnOldPathCacheEntries($currentPagePath, $pathCacheCondition) {
		$expireDays = (isset($this->conf['expireDays']) ? $this->conf['expireDays'] : 60) * 24 * 3600;
		$condition = $pathCacheCondition . ' AND expire=0 AND pagepath<>' .
			$GLOBALS['TYPO3_DB']->fullQuoteStr($currentPagePath, 'tx_realurl_pathcache');
		$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_pathcache', $condition,
			array(
				'expire' => $this->makeExpirationTime($expireDays)
			),
			'expire'
		);
	}

	/**
	 * Removes all expired path cache entries
	 *
	 * @return void
	 */
	protected function removeExpiredPathCacheEntries() {
		$lastCleanUpFileName = PATH_site . 'typo3temp/realurl_last_clean_up';
		$lastCleanUpTime = @filemtime($lastCleanUpFileName);
		if ($lastCleanUpTime === false || (time() - $lastCleanUpTime >= 6*60*60)) {
			touch($lastCleanUpFileName);
			$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_pathcache',
				'expire>0 AND expire<' . $this->makeExpirationTime());
		}
	}

	/**
	 * Fetch the page path (in the correct language)
	 * Return it in an array like:
	 *   array(
	 *     'pagepath' => 'product_omschrijving/another_page_title/',
	 *     'langID' => '2',
	 *   );
	 *
	 * @param	integer		Page ID
	 * @param	string		MP variable string
	 * @param	integer		Language id
	 * @return	array		The page path etc.
	 */
	protected function IDtoPagePathSegments($id, $mpvar, $langID) {
		$result = false;

		// Get rootLine for current site (overlaid with any language overlay records).
		$this->createSysPageIfNecessary();
		$this->sysPage->sys_language_uid = $langID;
		$rootLine = $this->sysPage->getRootLine($id, $mpvar);
		$numberOfRootlineEntries = count($rootLine);
		$newRootLine = array();
		$rootFound = FALSE;
		if (!$GLOBALS['TSFE']->tmpl->rootLine) {
			$GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);
		}
		// Pass #1 -- check if linking a page in subdomain inside main domain
		$innerSubDomain = false;
		for ($i = $numberOfRootlineEntries - 1; $i >= 0; $i--) {
			if ($rootLine[$i]['is_siteroot']) {
				$this->pObj->devLog('Found siteroot in the rootline for id=' . $id);
				$rootFound = true;
				$innerSubDomain = true;
				for ( ; $i < $numberOfRootlineEntries; $i++) {
					$newRootLine[] = $rootLine[$i];
				}
				break;
			}
		}
		if (!$rootFound) {
			// Pass #2 -- check normal page
			$this->pObj->devLog('Starting to walk rootline for id=' . $id . ' from index=' . $i, $rootLine);
			for ($i = 0; $i < $numberOfRootlineEntries; $i++) {
				if ($GLOBALS['TSFE']->tmpl->rootLine[0]['uid'] == $rootLine[$i]['uid']) {
					$this->pObj->devLog('Found rootline', array('uid' => $id, 'rootline start pid' => $rootLine[$i]['uid']));
					$rootFound = true;
					for ( ; $i < $numberOfRootlineEntries; $i++) {
						$newRootLine[] = $rootLine[$i];
					}
					break;
				}
			}
		}
		if ($rootFound) {
			// Translate the rootline to a valid path (rootline contains localized titles at this point!):
			$pagePath = $this->rootLineToPath($newRootLine, $langID);
			$this->pObj->devLog('Got page path', array('uid' => $id, 'pagepath' => $pagePath));
			$rootPageId = $this->conf['rootpage_id'];
			if ($innerSubDomain) {
				$parts = parse_url($pagePath);
				$this->pObj->devLog('$innerSubDomain=true, showing page path parts', $parts);
				if ($parts['host'] == '') {
					foreach ($newRootLine as $rl) {
						$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('domainName', 'sys_domain', 'pid=' . $rl['uid'] . ' AND redirectTo=\'\' AND hidden=0', '', 'sorting');
						if (count($rows)) {
							$domain = $rows[0]['domainName'];
							$this->pObj->devLog('Found domain', $domain);
							$rootPageId = $rl['uid'];
						}
					}
				}
			}
			$result = array(
					'pagepath' => $pagePath,
					'langID' => intval($langID),
					'rootpage_id' => intval($rootPageId),
				);
		}

		return $result;
	}

	/**
	 * Build a virtual path for a page, like "products/product_1/features/"
	 * The path is language dependant.
	 * There is also a function $TSFE->sysPage->getPathFromRootline, but that one can only be used for a visual
	 * indication of the path in the backend, not for a real page path.
	 * Note also that the for-loop starts with 1 so the first page is stripped off. This is (in most cases) the
	 * root of the website (which is 'handled' by the domainname).
	 *
	 * @param	array		Rootline array for the current website (rootLine from TSFE->tmpl->rootLine but with modified localization according to language of the URL)
	 * @param	integer		Language identifier (as in sys_languages)
	 * @return	string		Path for the page, eg.
	 * @see IDtoPagePathSegments()
	 */
	protected function rootLineToPath($rl, $lang) {
		$paths = array();
		array_shift($rl); // Ignore the first path, as this is the root of the website
		$c = count($rl);
		$stopUsingCache = false;
		$this->pObj->devLog('rootLineToPath starts searching', array('rootline size' => count($rl)));
		for ($i = 1; $i <= $c; $i++) {
			$page = array_shift($rl);

			// First, check for cached path of this page:
			$cachedPagePath = false;
			if (!$page['tx_realurl_exclude'] && !$stopUsingCache && !$this->conf['disablePathCache']) {

				// Using pathq2 index!
				list($cachedPagePath) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pagepath', 'tx_realurl_pathcache',
								'page_id=' . intval($page['uid']) .
								' AND language_id=' . intval($lang) .
								' AND rootpage_id=' . intval($this->conf['rootpage_id']) .
								' AND mpvar=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($page['_MP_PARAM'], 'tx_realurl_pathcache') .
								' AND expire=0', '', '', 1);

				if (is_array($cachedPagePath)) {
					$lastPath = implode('/', $paths);
					$this->pObj->devLog('rootLineToPath found path', $lastPath);
					if ($cachedPagePath != false && substr($cachedPagePath['pagepath'], 0, strlen($lastPath)) != $lastPath) {
						// Oops. Cached path does not start from already generated path.
						// It means that path was mapped from a parallel mount point.
						// We cannot not rely on cache any more. Stop using it.
						$cachedPagePath = false;
						$stopUsingCache = true;
						$this->pObj->devLog('rootLineToPath stops searching');
					}
				}
			}

			// If a cached path was found for the page it will be inserted as the base of the new path, overriding anything build prior to this:
			if ($cachedPagePath) {
				$paths = array();
				$paths[$i] = $cachedPagePath['pagepath'];
			}
			else {
				// Building up the path from page title etc.
				if (!$page['tx_realurl_exclude'] || count($rl) == 0) {
					// List of "pages" fields to traverse for a "directory title" in the speaking URL (only from RootLine!!):
					$segTitleFieldArray = t3lib_div::trimExplode(',', $this->conf['segTitleFieldList'] ? $this->conf['segTitleFieldList'] : TX_REALURL_SEGTITLEFIELDLIST_DEFAULT, 1);
					$theTitle = '';
					foreach ($segTitleFieldArray as $fieldName) {
						if ($page[$fieldName]) {
							$theTitle = $page[$fieldName];
							break;
						}
					}

					$paths[$i] = $this->encodeTitle($theTitle);
				}
			}
		}

		return implode('/', $paths);
	}

	/*******************************
	 *
	 * URL-to-ID methods
	 *
	 ******************************/

	/**
	 * Convert a page path to an ID.
	 *
	 * @param	array		Array of segments from virtual path
	 * @return	integer		Page ID
	 * @see decodeSpURL_idFromPath()
	 */
	protected function pagePathtoID(&$pathParts) {

		$row = $postVar = false;

		// If pagePath cache is not disabled, look for entry:
		if (!$this->conf['disablePathCache']) {

			// Work from outside-in to look up path in cache:
			$postVar = false;
			$copy_pathParts = $pathParts;
			$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;
			foreach ($copy_pathParts as $key => $value) {
				$copy_pathParts[$key] = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $value, 'toLower');
			}
			while (count($copy_pathParts)) {
				// Using pathq1 index!
				list($row) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
						'tx_realurl_pathcache.*', 'tx_realurl_pathcache,pages',
						'tx_realurl_pathcache.page_id=pages.uid AND pages.deleted=0' .
						' AND rootpage_id=' . intval($this->conf['rootpage_id']) .
						' AND pagepath=' . $GLOBALS['TYPO3_DB']->fullQuoteStr(implode('/', $copy_pathParts), 'tx_realurl_pathcache'),
						'', 'expire', '1');

				// This lookup does not include language and MP var since those are supposed to be fully reflected in the built url!
				if (is_array($row)) {
					break;
				}

				// If no row was found, we simply pop off one element of the path and try again until there are no more elements in the array - which means we didn't find a match!
				$postVar = array_pop($copy_pathParts);
			}
		}

		// It could be that entry point to a page but it is not in the cache. If we popped
		// any items from path parts, we need to check if they are defined as postSetVars or
		// fixedPostVars on this page. This does not guarantie 100% success. For example,
		// if path to page is /hello/world/how/are/you and hello/world found in cache and
		// there is a postVar 'how' on this page, the check below will not work. But it is still
		// better than nothing.
		if ($row && $postVar) {
			$postVars = $this->pObj->getPostVarSetConfig($row['page_id'], 'postVarSets');
			if (!is_array($postVars) || !isset($postVars[$postVar])) {
				// Check fixed
				$postVars = $this->pObj->getPostVarSetConfig($row['page_id'], 'fixedPostVars');
				if (!is_array($postVars) || !isset($postVars[$postVar])) {
					// Not a postVar, so page most likely in not in cache. Clear row.
					// TODO It would be great to update cache in this case but usually TYPO3 is not
					// complitely initialized at this place. So we do not do it...
					$row = false;
				}
			}
		}

		// Process row if found:
		if ($row) { // We found it in the cache

			// Check for expiration. We can get one of three:
			//   1. expire = 0
			//   2. expire <= time()
			//   3. expire > time()
			// 1 is permanent, we do not process it. 2 is expired, we look for permanent or non-expired
			// (in this order!) entry for the same page od and redirect to corresponding path. 3 - same as
			// 1 but means that entry is going to expire eventually, nothing to do for us yet.
			if ($row['expire'] > 0) {
				$this->pObj->devLog('pagePathToId found row', $row);
				// 'expire' in the query is only for logging
				// Using pathq2 index!
				list($newEntry) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pagepath,expire', 'tx_realurl_pathcache',
						'page_id=' . intval($row['page_id']) . '
						AND language_id=' . intval($row['language_id']) . '
						AND (expire=0 OR expire>' . $row['expire'] . ')', '', 'expire', '1');
				$this->pObj->devLog('pagePathToId searched for new entry', $newEntry);

				// Redirect to new path immediately if it is found
				if ($newEntry) {
					// Replace path-segments with new ones:
					$originalDirs = $this->pObj->dirParts; // All original
					$cp_pathParts = $pathParts;
					// Popping of pages of original dirs (as many as are remaining in $pathParts)
					for ($a = 0; $a < count($pathParts); $a++) {
						array_pop($originalDirs); // Finding all preVars here
					}
					for ($a = 0; $a < count($copy_pathParts); $a++) {
						array_shift($cp_pathParts); // Finding all postVars here
					}
					$newPathSegments = explode('/', $newEntry['pagepath']); // Split new pagepath into segments.
					$newUrlSegments = array_merge($originalDirs, $newPathSegments, $cp_pathParts); // Merge those segments.
					$this->pObj->appendFilePart($newUrlSegments);
					$redirectUrl = implode('/', $newUrlSegments);

					header('HTTP/1.1 301 Moved Permanently');
					header('Location: ' . t3lib_div::locationHeaderUrl($redirectUrl));
					exit();
				}
				$this->pObj->disableDecodeCache = true;	// Do not cache this!
			}

			// Unshift the number of segments that must have defined the page:
			$cc = count($copy_pathParts);
			for ($a = 0; $a < $cc; $a++) {
				array_shift($pathParts);
			}

			// Assume we can use this info at first
			$id = $row['page_id'];
			$GET_VARS = $row['mpvar'] ? array('MP' => $row['mpvar']) : '';
		}
		else {
			// Find it
			list($id, $GET_VARS) = $this->findIDByURL($pathParts);
		}

		// Return found ID:
		return array($id, $GET_VARS);
	}

	/**
	 * Search recursively for the URL in the page tree and return the ID of the path ("manual" id resolve)
	 *
	 * @param	array		Path parts, passed by reference.
	 * @return	array		Info array, currently with "id" set to the ID.
	 */
	protected function findIDByURL(array &$urlParts) {

		$id = 0;
		$GET_VARS = '';
		$startPid = $this->getRootPid();
		if ($startPid && count($urlParts)) {
			list($id, $mpvar) = $this->findIDByPathOverride($startPid, $urlParts);
			if ($id != 0) {
				$startPid = $id;
			}
			list($id, $mpvar) = $this->findIDBySegment($startPid, '', $urlParts);
			if ($mpvar) {
				$GET_VARS = array('MP' => $mpvar);
			}
		}

		return array(intval($id), $GET_VARS);
	}

	/**
	 * Obtains root page id for the current request.
	 *
	 * @return int
	 */
	protected function getRootPid() {
		if ($this->conf['rootpage_id']) { // Take PID from rootpage_id if any:
			$startPid = intval($this->conf['rootpage_id']);
		}
		else {
			$startPid = $this->pObj->findRootPageId();
		}
		return intval($startPid);
	}

	/**
	 * Attempts to find the page inside the root page that has a path override
	 * that fits into the passed segments.
	 *
	 * @param int $rootPid
	 * @param array $urlParts
	 * @return array Key 0 is pid (or 0), key 2 is empty string
	 */
	protected function findIDByPathOverride($rootPid, array &$urlParts) {
		$pageInfo = array(0, '');
		$extraUrlSegments = array();
		while (count($urlParts) > 0) {
			// Search for the path inside the root page
			$url = implode('/', $urlParts);
			$pageInfo = $this->findPageByPath($rootPid, $url);
			if ($pageInfo[0]) {
				break;
			}
			// Not found, try smaller segment
			array_unshift($extraUrlSegments, array_pop($urlParts));
		}
		$urlParts = $extraUrlSegments;
		return $pageInfo;
	}

	/**
	 * Attempts to find the page inside the root page that has the given path.
	 *
	 * @param int $rootPid
	 * @param string $url
	 * @return array Key 0 is pid (or 0), key 2 is empty string
	 */
	protected function findPageByPath($rootPid, $url) {
		$pages = $this->fetchPagesForPath($url);
		foreach ($pages as $key => $page) {
			if (!$this->isAnyChildOf($page['pid'], $rootPid)) {
				unset($pages[$key]);
			}
		}
		if (count($pages) > 1) {
			$idList = array();
			foreach ($pages as $page) {
				$idList[] = $page['uid'];
			}
			// No need for hsc() because TSFE does that
			$this->pObj->decodeSpURL_throw404(sprintf(
				'Multiple pages exist for path "%s": %s',
				$url, implode(', ', $idList)));
		}
		reset($pages);
		$page = current($pages);
		return array($page['uid'], '');
	}

	/**
	 * Checks if the the page is any child of the root page.
	 *
	 * @param int $pid
	 * @param int $rootPid
	 * @return boolean
	 */
	protected function isAnyChildOf($pid, $rootPid) {
		$this->createSysPageIfNecessary();
		$rootLine = $this->sysPage->getRootLine($pid);
		foreach ($rootLine as $page) {
			if ($page['uid'] == $rootPid) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Fetches a list of pages (uid,pid) for path. The priority of search is:
	 * - pages
	 * - pages_language_overlay
	 *
	 * @param string $url
	 * @return array
	 */
	protected function fetchPagesForPath($url) {
		$pages = array();
		$language = $this->pObj->getDetectedLanguage();
		if ($language != 0) {
			$pagesOverlay = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('t1.pid',
				'pages_language_overlay t1, pages t2',
				't1.hidden=0 AND t1.deleted=0 AND ' .
				't2.hidden=0 AND t2.deleted=0 AND ' .
				't1.pid=t2.uid AND ' .
				't2.tx_realurl_pathoverride=1 AND ' .
				($language > 0 ? 't1.sys_language_uid=' . $language . ' AND ' : '') .
				't1.tx_realurl_pathsegment=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($url, 'pages_language_overlay'),
				'', '', '', 'pid'
			);
			if (count($pagesOverlay) > 0) {
				$pages = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,pid', 'pages',
					'hidden=0 AND deleted=0 AND uid IN (' . implode(',', array_keys($pagesOverlay)) . ')',
					'', '', '', 'uid');
			}
		}
		// $pages has strings as keys. Therefore array_merge will ensure uniqueness.
		// Selection from 'pages' table will override selection from
		// pages_language_overlay.
		$pages2 = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid,pid', 'pages',
			'hidden=0 AND deleted=0 AND tx_realurl_pathoverride=1 AND tx_realurl_pathsegment=' .
				$GLOBALS['TYPO3_DB']->fullQuoteStr($url, 'pages'),
				'', '', '', 'uid');
		if (count($pages2)) {
			$pages = array_merge($pages, $pages2);
		}
		return $pages;
	}


	/**
	 * Recursively search the subpages of $pid for the first part of $urlParts
	 *
	 * @param	integer		Page id in which to search subpages matching first part of urlParts
	 * @param	string		MP variable string
	 * @param	array		Segments of the virtual path (passed by reference; items removed)
	 * @param	array		Array with the current pid/mpvar to return if no processing is done.
	 * @return	array		With resolved id and $mpvar
	 */
	protected function findIDBySegment($startPid, $mpvar, array &$urlParts, $currentIdMp = '', $foundUID = false) {

		// Creating currentIdMp variable if not set:
		if (!is_array($currentIdMp)) {
			$currentIdMp = array($startPid, $mpvar, $foundUID);
		}

		// No more urlparts? Return what we have.
		if (count($urlParts) == 0) {
			return $currentIdMp;
		}

		// Get the title we need to find now:
		$segment = array_shift($urlParts);

		// Perform search:
		list($uid, $row, $exclude, $possibleMatch) = $this->findPageBySegmentAndPid($startPid, $segment);

		// If a title was found...
		if ($uid) {
			return $this->processFoundPage($row, $mpvar, $urlParts, true);
		}
		elseif (count($exclude)) {
			// There were excluded pages, we have to process those!
			foreach ($exclude as $row) {
				$urlPartsCopy = $urlParts;
				array_unshift($urlPartsCopy, $segment);
				$result = $this->processFoundPage($row, $mpvar, $urlPartsCopy, false);
				if ($result[2]) {
					$urlParts = $urlPartsCopy;
					return $result;
				}
			}
		}

			// the possible "exclude in URL segment" match must be checked if no other results in
			// deeper tree branches were found, because we want to access this page also
			// + Books <-- excluded in URL (= possibleMatch)
			//   - TYPO3
			//   - ExtJS
		if (count($possibleMatch) > 0) {
			return $this->processFoundPage($possibleMatch, $mpvar, $urlParts, true);
		}

		// No title, so we reached the end of the id identifying part of the path and now put back the current non-matched title segment before we return the PID:
		array_unshift($urlParts, $segment);
		return $currentIdMp;
	}

	/**
	 * Process title search result. This is executed both when title is found and
	 * when excluded segment is found
	 *
	 * @param	array	$row	Row to process
	 * @param	array	$mpvar	MP var
	 * @param	array	$urlParts	URL segments
	 * @return	array	Resolved id and mpvar
	 * @see findPageBySegment()
	 */
	protected function processFoundPage($row, $mpvar, array &$urlParts, $foundUID) {
		$uid = $row['uid'];
		// Set base currentIdMp for next level:
		$currentIdMp = array( $uid, $mpvar, $foundUID);

		// Modify values if it was a mount point:
		if (is_array($row['_IS_MOUNTPOINT'])) {
			$mpvar .= ($mpvar ? ',' : '') . $row['_IS_MOUNTPOINT']['MPvar'];
			if ($row['_IS_MOUNTPOINT']['overlay']) {
				$currentIdMp[1] = $mpvar; // Change mpvar for the currentIdMp variable.
			}
			else {
				$uid = $row['_IS_MOUNTPOINT']['mount_pid'];
			}
		}

		// Yep, go search for the next subpage
		return $this->findIDBySegment($uid, $mpvar, $urlParts, $currentIdMp, $foundUID);
	}

	/**
	 * Search for a title in a certain PID
	 *
	 * @param	integer		Page id in which to search subpages matching title
	 * @param	string		Title to search for
	 * @return	array		First entry is uid, second entry is the row selected, including information about the page as a mount point.
	 * @access private
	 * @see findPageBySegment()
	 */
	protected function findPageBySegmentAndPid($searchPid, $title) {

		// List of "pages" fields to traverse for a "directory title" in the speaking URL (only from RootLine!!):
		$segTitleFieldList = $this->conf['segTitleFieldList'] ? $this->conf['segTitleFieldList'] : TX_REALURL_SEGTITLEFIELDLIST_DEFAULT;
		$selList = t3lib_div::uniqueList('uid,pid,doktype,mount_pid,mount_pid_ol,tx_realurl_exclude,' . $segTitleFieldList);
		$segTitleFieldArray = t3lib_div::trimExplode(',', $segTitleFieldList, 1);

		// page select object - used to analyse mount points.
		$sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		/** @var t3lib_pageSelect $sys_page */

		// Build an array with encoded values from the segTitleFieldArray of the subpages
		// First we find field values from the default language
		// Pages are selected in menu order and if duplicate titles are found the first takes precedence!
		$titles = array(); // array(title => uid);
		$exclude = array();
		$uidTrack = array();
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selList, 'pages',
						'pid=' . intval($searchPid) .
						' AND deleted=0 AND doktype!=255', '', 'sorting');
		while (false != ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result))) {

			// Mount points:
			$mount_info = $sys_page->getMountPointInfo($row['uid'], $row);
			if (is_array($mount_info)) {
				// There is a valid mount point.
				if ($mount_info['overlay']) {
					// Overlay mode: Substitute WHOLE record:
					$result2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selList, 'pages',
									'uid=' . intval($mount_info['mount_pid']) .
									' AND deleted=0 AND doktype!=255');
					$mp_row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result2);
					if (is_array($mp_row)) {
						$row = $mp_row;
					}
					else {
						unset($row); // If the mount point could not be fetched, unset the row
					}
				}
				$row['_IS_MOUNTPOINT'] = $mount_info;
			}

			// Collect titles from selected row:
			if (is_array($row)) {
				if ($row['tx_realurl_exclude']) {
					// segment is excluded
					$exclude[] = $row;
				}
				// Process titles. Note that excluded segments are also searched
				// otherwise they will never be found
				$uidTrack[$row['uid']] = $row;
				foreach ($segTitleFieldArray as $fieldName) {
					if ($row[$fieldName]) {
						$encodedTitle = $this->encodeTitle($row[$fieldName]);
						if (!isset($titles[$fieldName][$encodedTitle])) {
							$titles[$fieldName][$encodedTitle] = $row['uid'];
						}
					}
				}
			}
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($result);

		// We have to search the language overlay too, if: a) the language isn't the default (0), b) if it's not set (-1)
		$uidTrackKeys = array_keys($uidTrack);
		$language = $this->pObj->getDetectedLanguage();
		if ($language != 0) {
			foreach ($uidTrackKeys as $l_id) {
				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(TX_REALURL_SEGTITLEFIELDLIST_PLO,
					'pages_language_overlay',
					'pid=' . intval($l_id) . ' AND deleted=0' .
					($language > 0 ? ' AND sys_language_uid=' . $language : '')
				);
				while (false != ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result))) {
					foreach ($segTitleFieldArray as $fieldName) {
						if ($row[$fieldName]) {
							$encodedTitle = $this->encodeTitle($row[$fieldName]);
							if (!isset($titles[$fieldName][$encodedTitle])) {
								$titles[$fieldName][$encodedTitle] = $l_id;
							}
						}
					}
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($result);
			}
		}

		// Merge titles:
		$segTitleFieldArray = array_reverse($segTitleFieldArray); // To observe the priority order...
		$allTitles = array();
		foreach ($segTitleFieldArray as $fieldName) {
			if (is_array($titles[$fieldName])) {
				$allTitles = t3lib_div::array_merge($allTitles, $titles[$fieldName]);
			}
		}

		// Return:
		$encodedTitle = $this->encodeTitle($title);
		$possibleMatch = array();
		if (isset($allTitles[$encodedTitle])) {
			if (!$uidTrack[$allTitles[$encodedTitle]]['tx_realurl_exclude']) {
				return array($allTitles[$encodedTitle], $uidTrack[$allTitles[$encodedTitle]], false, array());
			}
			$possibleMatch = $uidTrack[$allTitles[$encodedTitle]];
		}
		return array(false, false, $exclude, $possibleMatch);
	}

	/*******************************
	 *
	 * Helper functions
	 *
	 ******************************/

	/**
	 * Convert a title to something that can be used in an page path:
	 * - Convert spaces to underscores
	 * - Convert non A-Z characters to ASCII equivalents
	 * - Convert some special things like the 'ae'-character
	 * - Strip off all other symbols
	 * Works with the character set defined as "forceCharset"
	 *
	 * @param	string		Input title to clean
	 * @return	string		Encoded title, passed through rawurlencode() = ready to put in the URL.
	 * @internal The signature or visibility of this function may change at any moment!
	 * @see rootLineToPath()
	 */
	public function encodeTitle($title) {

		// Fetch character set:
		$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;

		// Convert to lowercase:
		$processedTitle = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $title, 'toLower');

		// Strip tags
		$processedTitle = strip_tags($processedTitle);

		// Convert some special tokens to the space character
		$space = isset($this->conf['spaceCharacter']) ? $this->conf['spaceCharacter'] : '_';
		$processedTitle = preg_replace('/[ \-+_]+/', $space, $processedTitle); // convert spaces

		// Convert extended letters to ascii equivalents
		$processedTitle = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($charset, $processedTitle);

		// Strip the rest
		if ($this->extConf['init']['enableAllUnicodeLetters']) {
			// Warning: slow!!!
			$processedTitle = preg_replace('/[^\p{L}0-9' . ($space ? preg_quote($space) : '') . ']/u', '', $processedTitle);
		}
		else {
			$processedTitle = preg_replace('/[^a-zA-Z0-9' . ($space ? preg_quote($space) : '') . ']/', '', $processedTitle);
		}
		$processedTitle = preg_replace('/\\' . $space . '{2,}/', $space, $processedTitle); // Convert multiple 'spaces' to a single one
		$processedTitle = trim($processedTitle, $space);

		if ($this->conf['encodeTitle_userProc']) {
			$encodingConfiguration = array('strtolower' => true, 'spaceCharacter' => $this->conf['spaceCharacter']);
			$params = array('pObj' => &$this, 'title' => $title, 'processedTitle' => $processedTitle, 'encodingConfiguration' => $encodingConfiguration);
			$processedTitle = t3lib_div::callUserFunction($this->conf['encodeTitle_userProc'], $params, $this);
		}

		// Return encoded URL:
		return rawurlencode(strtolower($processedTitle));
	}

	/**
	 * Makes expiration timestamp for SQL queries
	 *
	 * @param	int		$offsetFromNow	Offset to expiration
	 * @return	int		Expiration time stamp
	 */
	protected function makeExpirationTime($offsetFromNow = 0) {
		if (!t3lib_extMgm::isLoaded('adodb') && (TYPO3_db_host == '127.0.0.1' || TYPO3_db_host == 'localhost')) {
			// Same host, same time, optimize
			return $offsetFromNow ? '(UNIX_TIMESTAMP()+(' . $offsetFromNow . '))' : 'UNIX_TIMESTAMP()';
		}
		// External database or non-mysql -> round to next day
		$date = getdate(time() + $offsetFromNow);
		return mktime(0, 0, 0, $date['mon'], $date['mday'], $date['year']);
	}

	/**
	 * Gets the value of current language
	 *
	 * @return	integer		Current language or 0
	 */
	protected function getLanguageVar(array $urlParameters) {
		$lang = 0;
		// Setting the language variable based on GETvar in URL which has been configured to carry the language uid:
		if ($this->conf['languageGetVar']) {
			$lang = 0;
			if (isset($urlParameters[$this->conf['languageGetVar']])) {
				$lang = intval($urlParameters[$this->conf['languageGetVar']]);
			}
			elseif (isset($this->pObj->orig_paramKeyValues[$this->conf['languageGetVar']])) {
				$lang = intval($this->pObj->orig_paramKeyValues[$this->conf['languageGetVar']]);
			}

			// Might be excepted (like you should for CJK cases which does not translate to ASCII equivalents)
			if (t3lib_div::inList($this->conf['languageExceptionUids'], $lang)) {
				$lang = 0;
			}
		}
		else {
			// No language in URL, get default from TSFE
			$lang = intval($GLOBALS['TSFE']->config['config']['sys_language_uid']);
		}
		//debug(array('lang' => $lang, 'languageGetVar' => $this->conf['languageGetVar'], 'opkv' => $this->$this->pObj->orig_paramKeyValues[$this->conf['languageGetVar']]), 'realurl');
		return $lang;
	}

	/**
	 * Resolves shortcut to the page
	 *
	 * @param	array	$page	Page record
	 * @param	array	$disableGroupAccessCheck	Flag for getPage()
	 * @param	array	$log	Internal log
	 * @return	int	Found page id
	 */
	protected function resolveShortcut($page, $disableGroupAccessCheck, $log = array(), &$mpvar = null) {
		if (isset($log[$page['uid']])) {
			// loop detected!
			return $page['uid'];
		}
		$log[$page['uid']] = '';
		$pageid = $page['uid'];
		if ($page['shortcut_mode'] == 0) {
			// Jumps to a certain page
			if ($page['shortcut']) {
				$pageid = intval($page['shortcut']);
				$page = $GLOBALS['TSFE']->sys_page->getPage($pageid, $disableGroupAccessCheck);
				if ($page && $page['doktype'] == 4) {
					$mpvar = '';
					$pageid = $this->resolveShortcut($page, $disableGroupAccessCheck, $log, $mpvar);
				}
			}
		}
		elseif ($page['shortcut_mode'] == 1) {
			// Jumps to the first subpage
			$rows = $GLOBALS['TSFE']->sys_page->getMenu($page['uid']);
			if (count($rows) > 0) {
				reset($rows);
				$row = current($rows);
				$pageid = ($row['doktype'] == 4 ? $this->resolveShortcut($row, $disableGroupAccessCheck, $log, $mpvar) : $row['uid']);
			}

			if (isset($row['_MP_PARAM'])) {
				if ($mpvar) {
					$mpvar .= ',';
				}

				$mpvar .= $row['_MP_PARAM'];
			}
		}
		elseif ($page['shortcut_mode'] == 3) {
			// Jumps to the parent page
			$page = $GLOBALS['TSFE']->sys_page->getPage($page['pid'], $disableGroupAccessCheck);
			$pageid = $page['uid'];
			if ($page && $page['doktype'] == 4) {
				$pageid = $this->resolveShortcut($page, $disableGroupAccessCheck, $log, $mpvar);
			}
		}
		return $pageid;
	}

	/**
	 * Creates $this->sysPage if it does not exist yet
	 *
	 * @return void
	 */
	protected function createSysPageIfNecessary() {
		if (!is_object($this->sysPage)) {
			$this->sysPage = t3lib_div::makeInstance('t3lib_pageSelect');
			$this->sysPage->init($GLOBALS['TSFE']->showHiddenPage || $this->pObj->isBEUserLoggedIn());
		}
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php']);
}
?>
