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
 * @coauthor	Kasper Skaarhoj <kasper@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   77: class tx_realurl_advanced
 *  104:     function main(&$params,$ref)
 *
 *              SECTION: "path" ID-to-URL methods
 *  151:     function IDtoPagePath(&$paramKeyValues, &$pathParts)
 *  228:     function fixURLCache($id,$lang = -1)
 *  248:     function updateURLCache($id,$lang = -1)
 *  332:     function IDtoPagePathSegments($id,$langID = -1)
 *  384:     function rootLineToPath($rl)
 *  408:     function encodeTitle($title)
 *  426:     function pagePathtoID(&$pathParts)
 *  497:     function findIDByURL(&$urlParts)
 *  548:     function searchTitle($pid,&$urlParts,$lang)
 *  590:     function setupPathPrefix()
 *
 * TOTAL FUNCTIONS: 11
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */










/**
 * Class for translating page ids to/from path strings (Speaking URLs)
 *
 * @author	Martin Poelstra <martin@beryllium.net>
 * @coauthor	Kasper Skaarhoj <kasper@typo3.com>
 * @package TYPO3
 * @subpackage tx_realurl
 */
class tx_realurl_advanced {

	var $sys_page;						// t3lib_page object for finding rootline on the fly.

		// Internal, for "path" id resolver:
	var $IDtoPagePathCache = array();	// Contains cached versions of page paths for id/language combinations.

		// Internal, dynamic:
	var $pObjRef;				// Reference to the parent object of "tx_realurl"
	var $conf;					// Local configuration for the "pagePath"






	/**
	 * Main function, called for both encoding and deconding of URLs.
	 * Based on the "mode" key in the $params array it branches out to either decode or encode functions.
	 *
	 * @param	array		Parameters passed from parent object, "tx_realurl". Some values are passed by reference! (paramKeyValues, pathParts and pObj)
	 * @param	object		Copy of parent object. Not used.
	 * @return	mixed		Depends on branching.
	 */
	function main(&$params,$ref)	{

			// Setting internal variables:
		$this->pObjRef = &$params['pObj'];
		$this->conf = $params['conf'];

			// Branching out based on type:
		switch((string)$params['mode'])	{
			case 'encode':
				return $this->IDtoPagePath($params['paramKeyValues'],$params['pathParts']);
			break;
			case 'decode':
				return $this->pagePathtoID($params['pathParts']);
			break;
		}
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
		while (($pageid>0) && ($loopCount>0)) {
			$loopCount--;

			$page = $GLOBALS['TSFE']->sys_page->getPage($pageid);
			if (!$page) {
				$pageid = -1;
				break;
			}

			if (($page['doktype']==4) && ($page['shortcut_mode'] == 0))	{ // Shortcut
				$pageid = $page['shortcut'] ? $page['shortcut'] : $pageid;
			} else { // done
				$pageid = $page['uid'];
				break;
			}
		}

			// The page wasn't found. Just return FALSE, so the calling function can revert to another way to build the link
		if ($pageid == -1)	return FALSE;

			// Setting the language variable based on GETvar in URL which has been configured to carry the language uid:
		if ($this->conf['languageGetVar'])	{
			$lang = intval($this->pObjRef->orig_paramKeyValues[$this->conf['languageGetVar']]);
		} else $lang = 0;

			// Fetch cached path
		if (!$this->conf['disablePathCache'])	{

			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'pagepath',
					'tx_realurl_pathcache',
					'page_id='.intval($pageid).
						' AND language_id='.intval($lang).
						' AND rootpage_id='.intval($this->conf['rootpage_id']).
						' AND expire=0'
				);

			if ($GLOBALS['TYPO3_DB']->sql_num_rows($result) > 1) { // If there seems to be more than one page path cached for this combo, go fix it
				$this->fixURLCache($pageid,$lang);
				$cachedPagePath = FALSE;
			} else {
				$cachedPagePath = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
			}
		} else {
			$cachedPagePath = FALSE;
		}

			// If a cached page path was found, get it now:
		if (is_array($cachedPagePath)) {
			if (TYPO3_DLOG)	t3lib_div::devLog("(cached: {$cachedPagePath['url']})", 'realurl');
			$pagePath = $cachedPagePath['pagepath'];
		} else {
				// There's no page path cached yet, just call updateCache() to let it fix that
			if (TYPO3_DLOG)	t3lib_div::devLog("(create new)",'realurl');
			$pagePath = $this->updateURLCache($pageid,$mpvar,$lang);
		}

			// Exploding the path, adding the entries to $pathParts (which is passed by reference and therefore automatically returned to main application)
		if (strlen($pagePath))	{
			$pagePath_exploded = explode('/',$pagePath);
			$pathParts = array_merge($pathParts,$pagePath_exploded);
		}
    }

	/**
	 * Fix the cache.
	 * This function is called when something appears to be wrong. This shouldn't ever be the case,
	 * but you'll never now...
	 *
	 * @param	integer		Page ID
	 * @param	integer		Language ID
	 * @return	void
	 */
	function fixURLCache($id,$lang = -1) {
			// Currently the only thing it does, is throw away all information about $id, because it
			// probably has duplicates in the database.
		if (TYPO3_DLOG)	t3lib_div::devLog('!!!! ERROR IN URLCACHE, FIXING ('.$id.','.$lang.') !!!!', 'realurl');
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_pathcache', 'page_id = '.intval($id));
	}

	/**
	 * Update the cache.
	 * We don't check if it's a shortcut or something. We just cache it.
	 * First find all current page paths that refer to this page (= all languages). Now first check if there actually IS a change
	 * in the path. If not, just update the timestamp, otherwise set the expire-times of all page paths starting with one of the
	 * old page paths we just found.
	 * Save the new page path in the cache (this time only the requested language).
	 * Optionally find cached page-content containing one of these page paths and delete it.
	 *
	 * @param	integer		Page id
	 * @param	string		MP variable string
	 * @param	integer		Language uid
	 * @return	string		The page path
	 */
	function updateURLCache($id,$mpvar,$lang) {
		if (TYPO3_DLOG)	t3lib_div::devLog('{ Update '.$id.','.$lang.' ','realurl');

			// Build the new page path, in the correct language
		$pagepathRec = $this->IDtoPagePathSegments($id, $mpvar, $lang);
		$pagepath = $pagepathRec['pagepath'];
		$pagepathHash = $pagepathRec['pagepathhash'];
		$langID = $pagepathRec['langID'];

		if (!$this->conf['disablePathCache'])	{
/*
			if (TYPO3_DLOG)	t3lib_div::devLog('(fetch old)','realurl');

				// Fetch all current versions of the page path (i.e. all languages)
			$oldUrls = array();
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'language_id, pagepath',
					'tx_realurl_pathcache',
					'page_id='.intval($id).
						' AND expire=0'.
						' AND rootpage_id='.intval($this->conf['rootpage_id'])
				);
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result))	{
				$oldUrls[$row['language_id']] = $row['pagepath'];
			}

				// Now let's see if the path has changed:
				// - If the page path isn't present in the requested language, we should just insert it and exit
				// - If it IS present, it should match. If it doesn't match, we should update the cache
			if (isset($oldUrls[$langID])) {
				if ($oldUrls[$langID]==$pagepath)	{
					if (TYPO3_DLOG)	t3lib_div::devLog('(no change) }','realurl');
					return $pagepathRec['pagepath'];
				} else {

					if (TYPO3_DLOG)	t3lib_div::devLog('(changed)','realurl');

						// !! TODO !! It might be a cool to search the rootline for this page, and see if the page path
						// of every page in that root matches the cached version.
						// If not, we just call UpdateURLCache of that URL first.

						// Update (=set expire-time of) all pagepaths starting with one of the $oldUrls
					$days = intval($this->conf['expireDays']);

					$updateArray = array();
					$updateArray['expire'] = time()+$days*3600*24;

					foreach ($oldUrls as $index => $oldUrl)	{
						$oldUrls[$index] = '(pagepath LIKE "'.$GLOBALS['TYPO3_DB']->quoteStr($oldUrl,'tx_realurl_pathcache').'%")';
					}
					$updateWhere = 'expire=0 AND ('.implode(' OR ',$oldUrls).')';

					if (TYPO3_DLOG)	t3lib_div::devLog("(updating old pagepaths)",'realurl');
					$GLOBALS['TYPO3_DB']->exec_UPDATEquery('tx_realurl_pathcache', $updateArray, $updateWhere);
					if (TYPO3_DLOG)	t3lib_div::devLog("Update status: ".$GLOBALS['TYPO3_DB']->sql_error(),'realurl');

						// We have to see if there are old pages that previously had the same path as this one currently has.
					if (TYPO3_DLOG)	t3lib_div::devLog('(deleting same pagepath)','realurl');
					$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('DISTINCT page_id','tx_realurl_pathcache', 'pagepath LIKE "'.$GLOBALS['TYPO3_DB']->quoteStr($pagepath,'tx_realurl_pathcache').'%"');
					while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_row($result)) {
						$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_pathcache', 'page_id='.intval($row[0]));
					}
				}
			}
*/

				// Insert URL in cache:
			$insertArray = array(
				'page_id' => $id,
				'language_id' => $langID,
				'pagepath' => $pagepath,
				'hash' => $pagepathHash,
				'expire' => 0,
				'rootpage_id' => intval($this->conf['rootpage_id']),
				'mpvar' => $mpvar
			);

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
	function IDtoPagePathSegments($id,$mpvar,$langID) {
			// Check to see if we already built this one in this session
		$cacheKey = $id.'.'.$mpvar.'.'.$langID;
		if (!isset($this->IDtoPagePathCache[$cacheKey]))	{

				// Get rootLine for current site (overlaid with any language overlay records).
			if (!is_object($this->sys_page))	{	// Create object if not found before:
					// Initialize the page-select functions.
				$this->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
				$this->sys_page->init($GLOBALS['TSFE']->showHiddenPage);
			}
			$this->sys_page->sys_language_uid = $langID;
			$rootLine = $this->sys_page->getRootLine($id,$mpvar);
			$cc = count($rootLine);
			$newRootLine = array();
			$rootFound = FALSE;
			for($a=0;$a<$cc;$a++)	{
				if ($GLOBALS['TSFE']->tmpl->rootLine[0]['uid'] == $rootLine[$a]['uid'])	{
					$rootFound = TRUE;
				}
				if ($rootFound)	{
					$newRootLine[] = $rootLine[$a];
				}
			}

				// Translate the rootline to a valid path (rootline contains localized titles at this point!):
			$pagepath = $this->rootLineToPath($newRootLine);

			$this->IDtoPagePathCache[$cacheKey] = array(
				'pagepath' => $pagepath,
				'langID' => $langID,
				'pagepathhash' => substr(md5($pagepath),0,10),
			);
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
	 * @return	string		Path for the page, eg.
	 * @see IDtoPagePathSegments()
	 */
	function rootLineToPath($rl) {
		$paths = array();
		array_shift($rl); // Ignore the first path, as this is the root of the website
		$c = count($rl);
		for ($i = 1; $i <= $c; $i++) {
			$page = array_shift($rl);

				// List of "pages" fields to traverse for a "directory title" in the speaking URL (only from RootLine!!):
			$segTitleFieldArray = t3lib_div::trimExplode(',', $this->conf['segTitleFieldList'] ? $this->conf['segTitleFieldList'] : 'nav_title,title', 1);
			$theTitle = '';
			foreach($segTitleFieldArray as $fieldName)	{
				if ($page[$fieldName])	{
					$theTitle = $page[$fieldName];
					break;
				}
			}

			$paths[$i] = $this->encodeTitle($theTitle);
		}

		return implode('/',$paths); // Return path, ending in a slash, or empty string
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
		if (!$this->conf['disablePathCache'])	{

				// Work from outside-in to look up path in cache:
			$copy_pathParts = $pathParts;
			while(1)	{
				$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
							'page_id, expire, mpvar',
							'tx_realurl_pathcache',
							'hash="'.substr(md5(implode('/',$copy_pathParts)),0,10).'"'.
								' AND rootpage_id='.intval($this->conf['rootpage_id'])
						);
				if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result))	{
					break;
				} elseif ($this->conf['firstHitPathCache'])	{
					break;
				} else {	// If no row was found, we simply pop off one element of the path and try again until there are no more elements in the array - which means we didn't find a match!
					array_pop($copy_pathParts);
					if (!count($copy_pathParts))	break;
				}
			}
		} else {
			$row = FALSE;
		}

			// Process row if found:
		if ($row) { // We found it in the cache
			if (TYPO3_DLOG)	t3lib_div::devLog("FOUND ",'realurl',1);

				// Unshift the number of segments that must have defined the page:
			$cc = count($copy_pathParts);
			for($a=0;$a<$cc;$a++)	{
				array_shift($pathParts);
			}

				// Assume we can use this info at first
			$id = $row['page_id'];
			$GET_VARS = $row['mpvar'] ? array('MP' => $row['mpvar']) : '';
		} else { // Let's search for it
			if (TYPO3_DLOG)	t3lib_div::devLog("NOT_FOUND_SEARCHING ",'realurl');

				// Find it
			list($info,$GET_VARS) = $this->findIDByURL($pathParts);

				// Setting id:
			if ($info['id']) {
				if (TYPO3_DLOG)	t3lib_div::devLog("FOUND ",'realurl');
				$id = $info['id'];
			} else {
					// No page found!
				if (TYPO3_DLOG)	t3lib_div::devLog("NOT_FOUND ",'realurl');
				$id = -1;
			}
		}

			// Return found ID:
		if (TYPO3_DLOG)	t3lib_div::devLog("Path resolved to ID: ".$id,'realurl');
		return array($id,$GET_VARS);
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
		if ($this->conf['rootpage_id'])	{	// Take PID from rootpage_id if any:
			$pid = intval($this->conf['rootpage_id']);
		} else {	// Otherwise, take the FIRST page under root level!
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
							'uid',
							'pages',
							'pid=0 AND deleted=0 AND doktype<200 AND hidden=0',
							'',
							'sorting',
							'1'
						);
			list($pid) = $GLOBALS['TYPO3_DB']->sql_fetch_row($result);
		}

			// Now, recursively search for the path from this root (if there are any elements in $urlParts)
		if ($pid && count($urlParts))	{
   			list($info['id'],$mpvar) = $this->searchTitle($pid,'',$urlParts);
			if ($mpvar)	{
				$GET_VARS = array('MP' => $mpvar);
			}
		}

		return array($info,$GET_VARS);
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
	function searchTitle($pid, $mpvar, &$urlParts, $currentIdMp='') {

			// Creating currentIdMp variable if not set:
		if (!is_array($currentIdMp))	{
			$currentIdMp = array($pid, $mpvar);
		}

			// No more urlparts? Return what we have.
		if (count($urlParts)==0)	{
			return $currentIdMp;
		}

			// Get the title we need to find now:
		$title = array_shift($urlParts);

			// Perform search:
		list($uid, $row) = $this->searchTitle_searchPid($pid,$title);

			// If a title was found...
		if ($uid)	{

				// Set base currentIdMp for next level:
			$currentIdMp = array($uid, $mpvar);

				// Modify values if it was a mount point:
			if (is_array($row['_IS_MOUNTPOINT']))	{
				$mpvar.= ($mpvar?',':'').$row['_IS_MOUNTPOINT']['MPvar'];
				if ($row['_IS_MOUNTPOINT']['overlay'])	{
					$currentIdMp[1] = $mpvar;	// Change mpvar for the currentIdMp variable.
				} else {
					$uid = $row['_IS_MOUNTPOINT']['mount_pid'];
				}
			}

				// Yep, go search for the next subpage
			return $this->searchTitle($uid,$mpvar,$urlParts,$currentIdMp);
		} else {
				// No title, so we reached the end of the id identifying part of the path and now put back the current non-matched title segment before we return the PID:
			array_unshift($urlParts,$title);
			return $currentIdMp;
		}
	}

	/**
	 * Search for a title in a certain PID
	 *
	 * @param	integer		Page id in which to search subpages matching title
	 * @param	string		Title to search for
	 * @return	array		First entry is uid , second entry is the row selected, including information about the page as a mount point.
	 * @see searchTitle()
	 * @access private
	 */
	function searchTitle_searchPid($searchPid,$title)	{

			// List of "pages" fields to traverse for a "directory title" in the speaking URL (only from RootLine!!):
		$segTitleFieldList = $this->conf['segTitleFieldList'] ? $this->conf['segTitleFieldList'] : 'nav_title,title';
		$selList = t3lib_div::uniqueList('uid,pid,doktype,mount_pid,mount_pid_ol,'.$segTitleFieldList);
		$segTitleFieldArray = t3lib_div::trimExplode(',', $segTitleFieldList, 1);

			// page select object - used to analyse mount points.
		$sys_page = t3lib_div::makeInstance('t3lib_pageSelect');

			// Build an array with encoded values from the segTitleFieldArray of the subpages
			// First we find field values from the default language
			// Pages are selected in menu order and if duplicate titles are found the first takes precedence!
		$titles = array(); // array(title => uid);
		$uidTrack = array();
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selList, 'pages', 'pid = '.intval($searchPid).' AND deleted = 0 AND doktype != 255','','sorting');
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {

				// Mount points:
			$mount_info = $sys_page->getMountPointInfo($row['uid'], $row);
			if (is_array($mount_info))	{	// There is a valid mount point.
				if ($mount_info['overlay'])	{	// Overlay mode: Substitute WHOLE record:
					$result2 = $GLOBALS['TYPO3_DB']->exec_SELECTquery($selList, 'pages', 'uid = '.intval($mount_info['mount_pid']).' AND deleted = 0 AND doktype != 255');
					$mp_row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result2);
					if (is_array($mp_row))	{
						$row = $mp_row;
					} else unset($row);	// If the mount point could not be fetched, unset the row
				}
				$row['_IS_MOUNTPOINT'] = $mount_info;
			}

				// Collect titles from selected row:
			if (is_array($row))	{
				$uidTrack[$row['uid']] = $row;
				foreach($segTitleFieldArray as $fieldName)	{
					if ($row[$fieldName])	{
						$encodedTitle = $this->encodeTitle($row[$fieldName]);
						if (!isset($titles[$fieldName][$encodedTitle]))	{
							$titles[$fieldName][$encodedTitle] = $row['uid'];
						}
					}
				}
			}
		}

			// We have to search the language overlay too, if: a) the language isn't the default (0), b) if it's not set (-1)
		$uidTrackKeys = array_keys($uidTrack);
		foreach($uidTrackKeys as $l_id) {
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('nav_title,title', 'pages_language_overlay', 'pid='.intval($l_id));
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result)) {
				foreach($segTitleFieldArray as $fieldName)	{
					if ($row[$fieldName])	{
						$encodedTitle = $this->encodeTitle($row[$fieldName]);
						if (!isset($titles[$fieldName][$encodedTitle]))	{
							$titles[$fieldName][$encodedTitle] = $l_id;
						}
					}
				}
			}
		}

			// Merge titles:
		$segTitleFieldArray = array_reverse($segTitleFieldArray);	// To observe the priority order...
		$allTitles = array();
		foreach($segTitleFieldArray as $fieldName)	{
			if (is_array($titles[$fieldName]))	{
				$allTitles = array_merge($allTitles,$titles[$fieldName]);
			}
		}

			// Return:
		if (isset($allTitles[$title]))	{
			return array($allTitles[$title], $uidTrack[$allTitles[$title]]);
		}
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
		$processedTitle = $GLOBALS['TSFE']->csConvObj->conv_case($charset,$title,'toLower');

			// Convert some special tokens to the space character:
		$space = $this->conf['spaceCharacter'] ? $this->conf['spaceCharacter'] : '_';
		$processedTitle = strtr($processedTitle,' -+_',$space.$space.$space.$space); // convert spaces

			// Convert extended letters to ascii equivalents:
		$processedTitle = $GLOBALS['TSFE']->csConvObj->specCharsToASCII($charset,$processedTitle);

			// Strip the rest...:
		$processedTitle = ereg_replace('[^a-zA-Z0-9\\'.$space.']', '', $processedTitle); // strip the rest
		$processedTitle = ereg_replace('\\'.$space.'+',$space,$processedTitle); // Convert multiple 'spaces' to a single one
		$processedTitle = trim($processedTitle,$space);

		if ($this->conf['encodeTitle_userProc'])	{
			$params = array(
				'pObj' => &$this,
				'title' => $title,
				'processedTitle' => $processedTitle,
			);
			$processedTitle = t3lib_div::callUserFunction($this->conf['encodeTitle_userProc'], $params, $this);
		}

			// Return encoded URL:
		return rawurlencode($processedTitle);
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php']);
}
?>
