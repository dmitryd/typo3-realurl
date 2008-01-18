<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2004 Martin Poelstra (martin@beryllium.net)
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
 *   95:     function main(&$params, $ref)
 *
 *              SECTION: "path" ID-to-URL methods
 *  136:     function IDtoPagePath(&$paramKeyValues, &$pathParts)
 *  232:     function updateURLCache($id, $mpvar, $lang, $cached_pagepath = '')
 *  279:     function IDtoPagePathSegments($id, $mpvar, $langID)
 *  337:     function rootLineToPath($rl, $lang)
 *
 *              SECTION: URL-to-ID methods
 *  406:     function pagePathtoID(&$pathParts)
 *  534:     function findIDByURL(&$urlParts)
 *  569:     function searchTitle($pid, $mpvar, &$urlParts, $currentIdMp = '')
 *  623:     function searchTitle_searchPid($searchPid, $title)
 *
 *              SECTION: Helper functions
 *  728:     function encodeTitle($title)
 *  763:     function makeExpirationTime($offsetFromNow = 0)
 *  778:     function getLanguageVar()
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

	/** @var t3lib_pageSelect */
	var $sys_page; // t3lib_page object for finding rootline on the fly.

	// Internal, for "path" id resolver:
	var $IDtoPagePathCache = array(); // Contains cached versions of page paths for id/language combinations.

	// Internal, dynamic:
	var $pObj; // Reference to the parent object of "tx_realurl"
	var $conf; // Local configuration for the "pagePath"

	/**
	 * Main function, called for both encoding and deconding of URLs.
	 * Based on the "mode" key in the $params array it branches out to either decode or encode functions.
	 *
	 * @param	array		Parameters passed from parent object, "tx_realurl". Some values are passed by reference! (paramKeyValues, pathParts and pObj)
	 * @param	tx_realurl		Copy of parent object. Not used.
	 * @return	mixed		Depends on branching.
	 */
	function main(&$params, $ref) {
		/* @var $ref tx_realurl */

		// Setting internal variables:
		$this->pObj = &$ref;
		$this->conf = $params['conf'];

		// See if cache should be disabled
		if ($ref->isBEUserLoggedIn()) {
			$this->conf['disablePathCache'] = true;
		}

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
	function IDtoPagePath(&$paramKeyValues, &$pathParts) {

		// Get page id and remove entry in paramKeyValues:
		$pageid = $paramKeyValues['id'];
		unset($paramKeyValues['id']);

		// Get MP variable and remove entry in paramKeyValues:
		$mpvar = $paramKeyValues['MP'];
		unset($paramKeyValues['MP']);

		// Convert a page-alias to a page-id if needed
		if (!is_numeric($pageid)) {
			$pageid = $GLOBALS['TSFE']->sys_page->getPageIdFromAlias($pageid);
		}

		// Fetch pagerecord, resolve shortcuts
		$page = array();
		$loopCount = 20; // Max 20 shortcuts, to prevent an endless loop
		while (($pageid > 0) && ($loopCount > 0)) {
			$loopCount--;

			$disableGroupAccessCheck = ($GLOBALS['TSFE']->config['config']['typolinkLinkAccessRestrictedPages'] ? true : false);
			$page = $GLOBALS['TSFE']->sys_page->getPage($pageid, $disableGroupAccessCheck);
			if (!$page) {
				$pageid = -1;
				break;
			}

			if (!$this->conf['dontResolveShortcuts'] && ($page['doktype'] == 4) && ($page['shortcut_mode'] == 0)) { // Shortcut
				$pageid = $page['shortcut'] ? $page['shortcut'] : $pageid;
			}
			else { // done
				$pageid = $page['uid'];
				break;
			}
		}

		// The page wasn't found. Just return FALSE, so the calling function can revert to another way to build the link
		if ($pageid == -1) {
			return;
		}

		// Set error if applicable.
		if ($this->conf['excludePageIds'] && t3lib_div::inList($this->conf['excludePageIds'], $pageid)) {
			$this->pObj->encodeError = TRUE;
			return;
		}

		$lang = $this->getLanguageVar();

		// Fetch cached path
		$cachedPagePath = false;
		if (!$this->conf['disablePathCache']) {

			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pagepath', 'tx_realurl_pathcache',
							'page_id=' . intval($pageid) . ' AND language_id=' . intval($lang) .
							//' AND rootpage_id='.intval($this->conf['rootpage_id']).
							' AND mpvar=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($mpvar, 'tx_realurl_pathcache') .
							' AND expire=0');
			if ($GLOBALS['TYPO3_DB']->sql_num_rows($result) == 1) { // More than one entry for a page with no expire time is wrong...!
				$cachedPagePath = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($result);
		}

		// If a cached page path was found, get it now:
		if (is_array($cachedPagePath) && !$this->conf['autoUpdatePathCache']) {
			$pagePath = $cachedPagePath['pagepath'];
		}
		else {
			// There's no page path cached yet (or if autoUpdatePathCache is set), just call updateCache() to let it generate and possibly cache the path
			$pagePath = $this->updateURLCache($pageid, $mpvar, $lang, $cachedPagePath['pagepath']);
		}

		// Set error if applicable.
		if ($pagePath === '__ERROR') {
			$this->pObj->encodeError = TRUE;
			return;
		}

		// Exploding the path, adding the entries to $pathParts (which is passed by reference and therefore automatically returned to main application)
		if (strlen($pagePath)) {
			$pagePath_exploded = explode('/', $pagePath);
			$pathParts = array_merge($pathParts, $pagePath_exploded);
		}
	}

	/**
	 * Insert into the pathcache, if enabled.
	 *
	 * @param	integer		Page id
	 * @param	string		MP variable string
	 * @param	integer		Language uid
	 * @param	string		If set, then a new entry will be inserted ONLY if it is different from $cached_pagepath
	 * @return	string		The page path
	 */
	function updateURLCache($id, $mpvar, $lang, $cached_pagepath = '') {
		// Build the new page path, in the correct language
		$pagepathRec = $this->IDtoPagePathSegments($id, $mpvar, $lang);
		if (!$pagepathRec) {
			return '__ERROR';
		}

		$pagepath = $pagepathRec['pagepath'];
		$pagepathHash = $pagepathRec['pagepathhash'];
		$langID = $pagepathRec['langID'];

		if (!$this->conf['disablePathCache'] && ((!$cached_pagepath && $pagepath) || (string)$pagepath !== (string)$cached_pagepath)) {

			// First, set expiration on existing records:
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_pathcache',
						'page_id=' . intval($id) . ' AND language_id=' . intval($langID) .
						' AND rootpage_id=' . intval($this->conf['rootpage_id']) .
						' AND mpvar=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($mpvar, 'tx_realurl_pathcache') .
						' AND expire=0',
						array(
							'expire' => $this->makeExpirationTime(($this->conf['expireDays'] ? $this->conf['expireDays'] : 60) * 24 * 3600)
						)
					);

			// Insert URL in cache:
			$insertArray = array('page_id' => $id, 'language_id' => $langID, 'pagepath' => $pagepath, 'hash' => $pagepathHash, 'expire' => 0, 'rootpage_id' => intval($this->conf['rootpage_id']), 'mpvar' => $mpvar);

			$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_pathcache', $insertArray);
		}

		return $pagepathRec['pagepath'];
	}

	/**
	 * Fetch the page path (in the correct language)
	 * Return it in an array like:
	 *   array(
	 *     'pagepath' => 'product_omschrijving/another_page_title/',
	 *     'pagepathhash' => 'd0646c1c88',
	 *     'langID' => '2',
	 *   );
	 *
	 * @param	integer		Page ID
	 * @param	string		MP variable string
	 * @param	integer		Language id
	 * @return	array		The page path etc.
	 */
	function IDtoPagePathSegments($id, $mpvar, $langID) {
		// Check to see if we already built this one in this session
		$cacheKey = $id . '.' . $mpvar . '.' . $langID;
		if (!isset($this->IDtoPagePathCache[$cacheKey])) {

			// Get rootLine for current site (overlaid with any language overlay records).
			if (!is_object($this->sys_page)) { // Create object if not found before:
				// Initialize the page-select functions.
				$this->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
				$this->sys_page->init($GLOBALS['TSFE']->showHiddenPage || $this->pObj->isBEUserLoggedIn());
			}
			$this->sys_page->sys_language_uid = $langID;
			$rootLine = $this->sys_page->getRootLine($id, $mpvar);
			$cc = count($rootLine);
			$newRootLine = array();
			$rootFound = FALSE;
			if (!$GLOBALS['TSFE']->tmpl->rootLine) {
				$GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);
			}
			for ($a = 0; $a < $cc; $a++) {
				if ($GLOBALS['TSFE']->tmpl->rootLine[0]['uid'] == $rootLine[$a]['uid']) {
					$rootFound = TRUE;
				}
				if ($rootFound) {
					$newRootLine[] = $rootLine[$a];
				}
			}

			if ($rootFound) {
				// Translate the rootline to a valid path (rootline contains localized titles at this point!):
				$pagepath = $this->rootLineToPath($newRootLine, $langID);
				$this->IDtoPagePathCache[$cacheKey] = array(
						'pagepath' => $pagepath,
						'langID' => $langID,
						'pagepathhash' => substr(md5($pagepath), 0, 10)
					);
			}
			else { // Outside of root line:
				$this->IDtoPagePathCache[$cacheKey] = FALSE;
			}
		}

		return $this->IDtoPagePathCache[$cacheKey];
	}

	/**
	 * Build a virtual path for a page, like "products/product_1/features/"
	 * The path is language dependant.
	 * There is also a function $TSFE->sys_page->getPathFromRootline, but that one can only be used for a visual
	 * indication of the path in the backend, not for a real page path.
	 * Note also that the for-loop starts with 1 so the first page is stripped off. This is (in most cases) the
	 * root of the website (which is 'handled' by the domainname).
	 *
	 * @param	array		Rootline array for the current website (rootLine from TSFE->tmpl->rootLine but with modified localization according to language of the URL)
	 * @param	integer		Language identifier (as in sys_languages)
	 * @return	string		Path for the page, eg.
	 * @see IDtoPagePathSegments()
	 */
	function rootLineToPath($rl, $lang) {
		$paths = array();
		array_shift($rl); // Ignore the first path, as this is the root of the website
		$c = count($rl);
		$stopUsingCache = false;
		for ($i = 1; $i <= $c; $i++) {
			$page = array_shift($rl);

			// First, check for cached path of this page:
			$cachedPagePath = false;
			if (!$stopUsingCache && !$this->conf['disablePathCache'] && !$this->conf['autoUpdatePathCache']) {

				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pagepath', 'tx_realurl_pathcache',
								'page_id=' . intval($page['uid']) .
								' AND language_id=' . intval($lang) .
								' AND rootpage_id=' . intval($this->conf['rootpage_id']) .
								' AND mpvar=' . $GLOBALS['TYPO3_DB']->fullQuoteStr($page['_MP_PARAM'], 'tx_realurl_pathcache') .
								' AND expire=0');

				if ($GLOBALS['TYPO3_DB']->sql_num_rows($result) == 1) { // If there seems to be more than one page path cached for this combo, we will fix it later
					$cachedPagePath = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
					$lastPath = implode('/', $paths);
					if ($cachedPagePath != false && substr($cachedPagePath['pagepath'], 0, strlen($lastPath)) != $lastPath) {
						// Oops. Cached path does not start from already generated path.
						// It means that path was mapped from a parallel mount point.
						// We cannot not rely on cache any more. Stop using it.
						$cachedPagePath = false;
						$stopUsingCache = true;
					}
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($result);
			}

			// If a cached path was found for the page it will be inserted as the base of the new path, overriding anything build prior to this:
			if ($cachedPagePath != false) {
				$paths = array();
				$paths[$i] = $cachedPagePath['pagepath'];
			}
			else { // Building up the path from page title etc.
				// List of "pages" fields to traverse for a "directory title" in the speaking URL (only from RootLine!!):
				$segTitleFieldArray = t3lib_div::trimExplode(',', $this->conf['segTitleFieldList'] ? $this->conf['segTitleFieldList'] : 'tx_realurl_pathsegment,alias,nav_title,title', 1);
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
	function pagePathtoID(&$pathParts) {

		// Init:
		$GET_VARS = '';

		// If pagePath cache is not disabled, look for entry:
		if (!$this->conf['disablePathCache']) {

			if (!isset($this->conf['firstHitPathCache'])) {
				$this->conf['firstHitPathCache'] = ((!isset($this->pObj->extConf['postVarSets']) || count($this->pObj->extConf['postVarSets']) == 0) && (!isset($this->pObj->extConf['fixedPostVars']) || count($this->pObj->extConf['fixedPostVars']) == 0));
			}

			// Work from outside-in to look up path in cache:
			$postVar = false;
			$copy_pathParts = $pathParts;
			$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;
			foreach ($copy_pathParts as $key => $value) {
				$copy_pathParts[$key] = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $value, 'toLower');
			}
			while (count($copy_pathParts)) {
				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('tx_realurl_pathcache.*', 'tx_realurl_pathcache,pages',
						'tx_realurl_pathcache.page_id=pages.uid AND pages.deleted=0' .
						' AND hash=' . $GLOBALS['TYPO3_DB']->fullQuoteStr(substr(md5(implode('/', $copy_pathParts)), 0, 10), 'tx_realurl_pathcache') .
						' AND rootpage_id=' . intval($this->conf['rootpage_id']), '', 'expire', '1');

				// This lookup does not include language and MP var since those are supposed to be fully reflected in the built url!
				if (false !== ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result))) {
					break;
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($result);

				if ($this->conf['firstHitPathCache']) {
					break;
				}

				// If no row was found, we simply pop off one element of the path and try again until there are no more elements in the array - which means we didn't find a match!
				$postVar = array_pop($copy_pathParts);
			}
		} else {
			$row = false;
		}

		// It could be that entry point to a page but it is not in the cache. If we popped
		// any items from path parts, we need to check if they are defined as postSetVars or
		// fixedPostVars on this page. This does not guarantie 100% success. For example,
		// if path to page is /hello/world/how/are/you and hello/world found in cache and
		// there is a postVar 'how' on this page, the check below will not work. But it is still
		// better than nothing.
		if ($row && $postVar) {
			$postVars = $this->pObjRef->getPostVarSetConfig($row['pid'], 'postVarSets');
			if (!is_array($postVars) || !isset($postVars[$postVar])) {
				// Check fixed
				$postVars = $this->pObjRef->getPostVarSetConfig($row['pid'], 'fixedPostVars');
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
			// (in thos order!) entry for the same page od and redirect to corresponding path. 3 - same as
			// 1 but means that entry is going to expire eventually, nothing to do for us yet.
			if ($row['expire'] > 0 && $row['expire'] <= time()) {
				list($newEntry) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pagepath', 'tx_realurl_pathcache',
						'page_id=' . intval($row['page_id']) . '
						AND language_id=' . intval($row['language_id']) . '
						AND (expire=0 OR expire>' . $this->makeExpirationTime() . ')', '', 'expire', '1');

				if ($newEntry) {
					$this->pObj->disableDecodeCache = true;
					header('HTTP/1.1 301 Moved Permanently');

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
					$newUrlSegments[] = $this->pObj->filePart; // Add any filename as well
					$redirectUrl = implode('/', $newUrlSegments); // Create redirect URL:

					header('Location: ' . t3lib_div::locationHeaderUrl($redirectUrl));
					exit();
				}
			}

			// Unshift the number of segments that must have defined the page:
			$cc = count($copy_pathParts);
			for ($a = 0; $a < $cc; $a++) {
				array_shift($pathParts);
			}

			// Assume we can use this info at first
			$id = $row['page_id'];
			$GET_VARS = $row['mpvar'] ? array('MP' => $row['mpvar']) : '';
		} else {

			// Find it
			list($info, $GET_VARS) = $this->findIDByURL($pathParts);

			// Setting id:
			$id = ($info['id'] ? $info['id'] : 0);
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
	function findIDByURL(&$urlParts) {

		// Initialize:
		$info = array();
		$info['id'] = 0;
		$GET_VARS = '';

		// Find the PID where to begin the resolve:
		if ($this->conf['rootpage_id']) { // Take PID from rootpage_id if any:
			$pid = intval($this->conf['rootpage_id']);
		}
		else {
			$pid = $this->pObj->findRootPageIdByHost();
		}

		// Now, recursively search for the path from this root (if there are any elements in $urlParts)
		if ($pid && count($urlParts)) {
			list($info['id'], $mpvar) = $this->searchTitle($pid, '', $urlParts);
			if ($mpvar) {
				$GET_VARS = array('MP' => $mpvar);
			}
		}

		return array($info, $GET_VARS);
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
	function searchTitle($pid, $mpvar, &$urlParts, $currentIdMp = '') {

		// Creating currentIdMp variable if not set:
		if (!is_array($currentIdMp)) {
			$currentIdMp = array($pid, $mpvar);
		}

		// No more urlparts? Return what we have.
		if (count($urlParts) == 0) {
			return $currentIdMp;
		}

		// Get the title we need to find now:
		$title = array_shift($urlParts);

		// Perform search:
		list($uid, $row) = $this->searchTitle_searchPid($pid, $title);

		// If a title was found...
		if ($uid) {

			// Set base currentIdMp for next level:
			$currentIdMp = array($uid, $mpvar);

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
			return $this->searchTitle($uid, $mpvar, $urlParts, $currentIdMp);
		}
		else {
			// No title, so we reached the end of the id identifying part of the path and now put back the current non-matched title segment before we return the PID:
			array_unshift($urlParts, $title);
			return $currentIdMp;
		}
	}

	/**
	 * Search for a title in a certain PID
	 *
	 * @param	integer		Page id in which to search subpages matching title
	 * @param	string		Title to search for
	 * @return	array		First entry is uid , second entry is the row selected, including information about the page as a mount point.
	 * @access private
	 * @see searchTitle()
	 */
	function searchTitle_searchPid($searchPid, $title) {

		// List of "pages" fields to traverse for a "directory title" in the speaking URL (only from RootLine!!):
		$segTitleFieldList = $this->conf['segTitleFieldList'] ? $this->conf['segTitleFieldList'] : 'tx_realurl_pathsegment,alias,nav_title,title';
		$selList = t3lib_div::uniqueList('uid,pid,doktype,mount_pid,mount_pid_ol,' . $segTitleFieldList);
		$segTitleFieldArray = t3lib_div::trimExplode(',', $segTitleFieldList, 1);

		// page select object - used to analyse mount points.
		$sys_page = t3lib_div::makeInstance('t3lib_pageSelect');

		// Build an array with encoded values from the segTitleFieldArray of the subpages
		// First we find field values from the default language
		// Pages are selected in menu order and if duplicate titles are found the first takes precedence!
		$titles = array(); // array(title => uid);
		$uidTrack = array();
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selList, 'pages',
						'pid = ' . intval($searchPid) .
						' AND deleted = 0 AND doktype != 255', '', 'sorting');
		while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result))) {

			// Mount points:
			$mount_info = $sys_page->getMountPointInfo($row['uid'], $row);
			if (is_array($mount_info)) { // There is a valid mount point.
				if ($mount_info['overlay']) { // Overlay mode: Substitute WHOLE record:
					$result2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selList, 'pages',
									'uid = ' . intval($mount_info['mount_pid']) .
									' AND deleted = 0 AND doktype != 255');
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
		foreach ($uidTrackKeys as $l_id) {
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('nav_title,title', 'pages_language_overlay', 'pid=' . intval($l_id));
			while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result))) {
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
		if (isset($allTitles[$encodedTitle])) {
			return array($allTitles[$encodedTitle], $uidTrack[$allTitles[$encodedTitle]]);
		}
		return false;
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
	 * @see rootLineToPath()
	 */
	function encodeTitle($title) {

		// Fetch character set:
		$charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ? $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] : $GLOBALS['TSFE']->defaultCharSet;

		// Convert to lowercase:
		$processedTitle = $GLOBALS['TSFE']->csConvObj->conv_case($charset, $title, 'toLower');

		// Convert some special tokens to the space character:
		$space = isset($this->conf['spaceCharacter']) ? $this->conf['spaceCharacter'] : '_';
		$processedTitle = preg_replace('/[ \-+_]+/', $space, $processedTitle); // convert spaces

		// Convert extended letters to ascii equivalents:
		$processedTitle = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($charset, $processedTitle);

		// Strip the rest...:
		$processedTitle = ereg_replace('[^a-zA-Z0-9\\' . $space . ']', '', $processedTitle); // strip the rest
		$processedTitle = ereg_replace('\\' . $space . '+', $space, $processedTitle); // Convert multiple 'spaces' to a single one
		$processedTitle = trim($processedTitle, $space);

		if ($this->conf['encodeTitle_userProc']) {
			$params = array('pObj' => &$this, 'title' => $title, 'processedTitle' => $processedTitle);
			$processedTitle = t3lib_div::callUserFunction($this->conf['encodeTitle_userProc'], $params, $this);
		}

		// Return encoded URL:
		return rawurlencode($processedTitle);
	}

	/**
	 * Makes expiration timestamp for SQL queries
	 *
	 * @param	int		$offsetFromNow	Offset to expiration
	 * @return	int		Expiration time stamp
	 */
	function makeExpirationTime($offsetFromNow = 0) {
		if (!t3lib_extMgm::isLoaded('adodb') && (TYPO3_db_host == '127.0.0.1' || TYPO3_db_host == 'localhost')) {
			// Same host, same time, optimize
			return $offsetFromNow ? '(UNIX_TIMESTAMP()+(' + $offsetFromNow + '))' : 'UNIX_TIMESTAMP()';
		}
		// External datbase or non-mysql -> round to next day
		$date = getdate(time() + $offsetFromNow);
		return mktime(0, 0, 0, $date['mon'], $date['mday'], $date['year']);
	}

	/**
	 * Gets the value of current language
	 *
	 * @return	integer		Current language or 0
	 */
	function getLanguageVar() {
		$lang = 0;
		// Setting the language variable based on GETvar in URL which has been configured to carry the language uid:
		if ($this->conf['languageGetVar']) {
			$lang = intval($this->pObj->orig_paramKeyValues[$this->conf['languageGetVar']]);

			// Might be excepted (like you should for CJK cases which does not translate to ASCII equivalents)
			if (t3lib_div::inList($this->conf['languageExceptionUids'], $lang)) {
				$lang = 0;
			}
		}
		return $lang;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php']);
}
?>