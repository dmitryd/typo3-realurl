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

			// Branching out based on type:
		switch((string)$params['mode'])	{
			case 'encode':
				$this->pObjRef = &$params['pObj'];
				$this->conf = $params['conf'];
				return $this->IDtoPagePath($params['paramKeyValues'],$params['pathParts']);
			break;
			case 'decode':
				$this->pObjRef = &$params['pObj'];
				$this->conf = $params['conf'];
				return $this->pagePathtoID($params['pathParts']);
			break;
			default:
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
	 * This routine also takes care of updating the cache in case a page has been changed etc.
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

			if ($page['doktype']==4)	{ // Shortcut. Martin! Watch out for the kind of shortcuts that redirects to "first subpage" etc - they cannot be translated! You should look for such a configuration and NOT translate the id if that is found!
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
			// Don't select an expiring path though...
		if (TYPO3_DLOG)	t3lib_div::devLog("(select from cache $pageid,$lang)", 'realurl');
		$path = '';
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('pagepath', 'tx_realurl_pathcache', 'page_id='.intval($pageid).' AND language_id='.intval($lang).' AND expire=0');
		if ($GLOBALS['TYPO3_DB']->sql_num_rows($result) > 1) { // If there seems to be more than one page path cached for this combo, go fix it
			$this->fixURLCache($pageid,$lang);
			$cachedPagePath = FALSE;
		} else {
			$cachedPagePath = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result);
		}

			// If a cached page path was found, get it now:
		if (is_array($cachedPagePath)) {
			if (TYPO3_DLOG)	t3lib_div::devLog("(cached: {$cachedPagePath['url']})", 'realurl');
			$pagePath = $cachedPagePath['pagepath'];
		} else {
				// There's no page path cached yet, just call updateCache() to let it fix that
			if (TYPO3_DLOG)	t3lib_div::devLog("(create new)",'realurl');
			$pagePath = $this->updateURLCache($pageid,$lang);
		}

			// Exploding the path, adding the entries to $pathParts (which is passed by reference and therefore automatically returned to main application)
		$pagePath_exploded = explode('/',$pagePath);
		$pathParts = array_merge($pathParts,$pagePath_exploded);
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
	 * @param	integer		Language uid
	 * @return	string		The page path
	 */
	function updateURLCache($id,$lang = -1) {
		if (TYPO3_DLOG)	t3lib_div::devLog('{ Update '.$id.','.$lang.' ','realurl');

			// Build the new page path, in the correct language
		$pagepathRec = $this->IDtoPagePathSegments($id, $lang);
		$pagepath = $pagepathRec['pagepath'];
		$pagepathHash = $pagepathRec['pagepathhash'];
		$langID = $pagepathRec['langID'];

		if (TYPO3_DLOG)	t3lib_div::devLog('(fetch old)','realurl');

			// Fetch all current versions of the page path (i.e. all languages)
		$oldUrls = array();
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('language_id, pagepath', 'tx_realurl_pathcache', 'page_id='.intval($id).' AND expire=0');
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

		$insertArray = array(
			'page_id' => $id,
			'language_id' => $langID,
			'pagepath' => $pagepath,
			'hash' => $pagepathHash,
			'expire' => 0
		);
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_pathcache', $insertArray);

		if (TYPO3_DLOG)	t3lib_div::devLog("[$pagepath] }",5);

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
	 * @param	integer		Language id
	 * @return	array		The page path etc.
	 */
	function IDtoPagePathSegments($id,$langID = -1) {
			// Check to see if we already built this one in this session
		$cacheKey = $id.'.'.$langID;
		if (isset($this->IDtoPagePathCache[$cacheKey]))
			return($this->IDtoPagePathCache[$cacheKey]);

			// Get rootLine for current site (overlaid with any language overlay records).
		if (!is_object($this->sys_page))	{	// Create object if not found before:
				// Initialize the page-select functions.
			$this->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
			$this->sys_page->init($GLOBALS['TSFE']->showHiddenPage);
		}
		$this->sys_page->sys_language_uid = $langID;
		$rootLine = $this->sys_page->getRootLine($id);
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
			'pagepathhash' => substr(md5($pagepath),0,10)
		);

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
			$paths[$i] = $this->encodeTitle($page['nav_title'] ? $page['nav_title'] : $page['title']);
		}

		return implode('/',$paths); // Return path, ending in a slash, or empty string
	}

	/**
	 * Convert a title to something that can be used in an page path:
	 * - Convert spaces to underscores
	 * - Convert accented characters to their non-accented variant
	 * - Convert some special things like the 'ae'-character
	 * - Strip off all other symbols
	 * Works only on iso-8859-1 strings.
	 *
	 * @param	string		Input title to clean
	 * @return	string		Encoded title.
	 * @see rootLineToPath()
	 */
	function encodeTitle($title) {
		$title = strtolower($title); // lowercase page path
		$space = $this->conf['spaceCharacter'];
		$title = strtr($title,'àáâãäåçèéêëìíîïñòóôõöøùúûüýÿµ -+_','aaaaaaceeeeiiiinoooooouuuuyyu'.$space.$space.$space.$space); // remove accents, convert spaces
		$title = strtr($title,array('þ' => 'th', 'ð' => 'dh', 'ß' => 'ss', 'æ' => 'ae')); // rewrite some special chars
		$title = ereg_replace('[^a-z0-9\\'.$space.']', '', $title); // strip the rest
		$title = ereg_replace('\\'.$space.'+',$space,$title); // Convert multiple underscores to a single one
		$title = trim($title,$space);
		return rawurlencode($title);
	}

	/**
	 * Convert a page path to an ID.
	 *
	 * @param	array		Array of segments from virtual path
	 * @return	integer		Page ID
	 * @see decodeSpURL_idFromPath()
	 */
	function pagePathtoID(&$pathParts) {

			// Work from outside-in to look up path in cache:
		$copy_pathParts = $pathParts;
		while(1)	{
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'page_id, expire',
						'tx_realurl_pathcache',
						'hash="'.substr(md5(implode('/',$copy_pathParts)),0,10).'"'
					);
			if ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($result))	{
				break;
			} else {	// If no row was found, we simply pop off one element of the path and try again until there are no more elements in the array - which means we didn't find a match!
				array_pop($copy_pathParts);
				if (!count($copy_pathParts))	break;
			}
		}

		if ($row) { // We found it in the cache
			if (TYPO3_DLOG)	t3lib_div::devLog("FOUND ",'realurl',1);

				// Unshift the number of segments that must have defined the page:
			$cc = count($copy_pathParts);
			for($a=0;$a<$cc;$a++)	{
				array_shift($pathParts);
			}

				// Assume we can use this info at first
			$id = $row['page_id'];

			if ($row['expire']) { // Is the URL marked as an old URL?
				if (TYPO3_DLOG)	t3lib_div::devLog("EXPIRED ",'realurl');
				$info = $this->findIDByURL($pathParts); // Search if there's a newly created page with the same URL
				if ($info['id']!=-1) {
						// We actually found something, so we should store the NEW page<->pagepath-pair.
						// We can't call UpdateURLCache here though, because sys_page->getRootLine doesn't exist yet...
					if (TYPO3_DLOG)	t3lib_div::devLog("NEW ",'realurl');
					$id = $info['id'];
					$this->cacheURLlater = 1; // The new pagepath is cached in fetch_the_id();
				}
			} else {
				$this->freshURLFound = 1; // We actually found a fresh URL
			}
		} else { // Let's search for it
			if (TYPO3_DLOG)	t3lib_div::devLog("NOT_FOUND_SEARCHING ",'realurl');

				// Find & cache it
			$info = $this->findIDByURL($pathParts);

				// !! TODO !! Even a 404 is returned as an array. Could be used to implement the following TODO.
			if ($info['id']!=-1) {
				if (TYPO3_DLOG)	t3lib_div::devLog("FOUND ",'realurl');

				$id = $info['id'];
				$this->cacheURLlater = 1;
			} else {
				// !! TODO !! Multilanguage 404-error. We DO have the language, so we could use it...
				if (TYPO3_DLOG)	t3lib_div::devLog("NOT_FOUND ",'realurl');
				$id = -1;
			}
		}

			// Return found ID:
		if (TYPO3_DLOG)	t3lib_div::devLog("Path resolved to ID: ".$id,'realurl');
		return $id;
	}

	/**
	 * Search recursively for the URL in the page tree
	 * If we find, it will be cached and we return something like:
	 *   array('id' => 123);
	 * If we don't find it, return FALSE
	 *
	 * @param	array		Path parts, passed by reference.
	 * @return	array		Info array, currently with "id" set to the ID.
	 */
	function findIDByURL(&$urlParts) {

		$info = array();
		$info['id'] = 0;


			// Find the rootpage of $domain
		$domain = ereg('^[[:alnum:]]*:\/\/','',t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
						'pid',
						'sys_domain',
						'(domainName="'.$GLOBALS['TYPO3_DB']->quoteStr($domain, 'sys_domain').'" OR domainName="'.$GLOBALS['TYPO3_DB']->quoteStr(substr($domain,0,-1),'sys_domain').'") AND hidden=0'
					);
		$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($result);

			// Find the default startpid when we couldn't find the domain, so we can search for the URL.
		if (!$row) {
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
							'uid',
							'pages',
							'pid=0 AND deleted=0 AND doktype<200 AND hidden=0',
							'',
							'sorting',
							'1'
						);
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($result);
			if ($row)
				$pid = $row[0]; // Use the first 'visible' page in the tree as the root
			else
				$pid = 0; // Let Type figure it out
		} else {
			$pid = $row[0];
		}

			// Now, recursively search for the path from this root
		if ($pid != 0)	{
   			$info['id'] = $this->searchTitle($pid,$urlParts,0);
		}

		return $info;
	}


	/**
	 * Recursively search the subpages of $pid for the first part of $urlParts
	 * If we find it, we return the id, if we don't we return -1.
	 *
	 * @param	integer		Page id in which to search subpages matching first part of urlParts
	 * @param	array		Segments of the virtual path
	 * @param	integer		Language uid - not used currently.
	 * @return	integer		The resolved page id.
	 */
	function searchTitle($pid,&$urlParts,$lang) {

			// Done, the $pid is the requested id
		if (count($urlParts)==0)	return $pid;

		// Get the title we need to find now
		$title = array_shift($urlParts);

			// Build an array with encoded titles of the subpages
			// First we find titles in the default language
		$titles = array(); // array(title => uid);
		$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('uid,title', 'pages', 'pid = '.intval($pid).' AND deleted = 0 AND doktype != 255');
		while ($row = mysql_fetch_row($result)) {
			$titles[$this->encodeTitle($row[1])] = $row[0];
		}

		 	// We have to search the language overlay too, if: a) the language isn't the default (0), b) if it's not set (-1)
		$titles_backup = $titles; // We're gonna change the array in the loop, so let's be safe and loop with the backup
		foreach($titles_backup as $l_title => $l_id) {
			$result = $GLOBALS['TYPO3_DB']->exec_SELECTquery('title', 'pages_language_overlay', 'pid='.intval($l_id));		// Check deleted=0 ???
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_row($result);

			if ($row) { // If a language overlay is found, ...
				#unset($titles[$l_title]); // throw the entry of the default language away
				$titles[$this->encodeTitle($row[0])] = $l_id; // Add this language
			}
		}

			// Does $title exist in $titles?
		if (!isset($titles[$title]))	{
			#return -1; // Nope, return 404

				// Instead of returning "-1" when a page is not found anymore, we return the id of the page so far as we got up the tree! This is because the remaining parts of hte path MIGHT belong to postVarSets and therefore it is not necessarily an error!
			array_unshift($urlParts,$title);	//
			return $pid;
		} else {
			return $this->searchTitle($titles[$title],$urlParts,$lang); // Yep, go search for the next subpage
		}
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_advanced.php']);
}
?>
