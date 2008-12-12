<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2008 Dmitry Dulepov (dmitry@typo3.org)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
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
 * TCEmain hook to update path cache if page is renamed
 *
 * $Id$
 *
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   57: class tx_realurl_tcemain
 *   94:     function processDatamap_preProcessFieldArray($incomingFieldArray, $table, $id, &$pObj)
 *  154:     function processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, &$pObj)
 *  202:     function getFieldList($table, &$config)
 *  215:     function getConfigForPage($pid)
 *  236:     function getInfoFromOverlayPid($pid)
 *  247:     function createTSFE($pid)
 *
 * TOTAL FUNCTIONS: 6
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

/**
 * TCEmain hook to update path cache if page is renamed
 *
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 */
class tx_realurl_tcemain {

	/**
	 * Fields are recorded by {@link processDatamap_preProcessFieldArray}.
	 * Structure looks like:
	 * array(
	 *	$someNumericId => array(
	 * 		$table => array(
	 * 			$id => array(
	 * 				'configName' => $config_key_for_page_domain_in_realurl_conf,
	 * 				'realUid' => $realUid, // real page uid (in case if $id points to pages_language_overlay)
	 * 				'language' => $language,
	 * 			)
	 * 		)
	 * 	)
	 * )
	 *
	 * @var	array
	 */
	var	$fieldCollection = array();

	/**
	 * Saved TCEmain instances
	 *
	 * @var	array
	 */
	var	$tceMainInstList = array();

	/**
	 * If set to true, debug log is enabled
	 *
	 * @var	boolean
	 */
	var $enableDevLog;

	public function __construct() {
		$sysconf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
		$this->enableDevLog = $sysconf['enableDevLog'];
	}

	/**
	 * Records old page information for future use.
	 *
	 * @param	array		$incomingFieldArray	Fields to be modified
	 * @param	string		$table	Table name (we are interested only in 'pages' or 'pages_language_overlay')
	 * @param	mixed		$id	uid of the record. We are insterested only if it is integer
	 * @param	t3lib_TCEmain		$pObj	Reference to the calling object
	 * @return	void
	 */
	function processDatamap_preProcessFieldArray($incomingFieldArray, $table, $id, &$pObj) {
		/* @var $pObj t3lib_TCEmain */
		if (($table == 'pages' || $table == 'pages_language_overlay') && t3lib_div::testInt($id)) {

			if ($table != 'pages_language_overlay') {
				$realUid = $id; $language = 0;
			}
			else {
				list($realUid, $language) = $this->getInfoFromOverlayPid($id);
				if (!$realUid) {
					return;
				}
			}
			if ($this->enableDevLog) {
				t3lib_div::devLog('Found pids', 'realurl', 0, array('realUid' => $realUid, 'language' => $language));
			}
			// Quit immediately if page in not available in FE
			if (!($configAr = $this->getConfigForPage($realUid))) {
				// Page is not configured for realurl
				if ($this->enableDevLog) {
					t3lib_div::devLog('Configuration is not found for pid=' . $realUid, 'realurl');
				}
				return;
			}
			list($domain, $config) = $configAr;
			if ($config['pagePath']['type'] != 'user' || false === strpos($config['pagePath']['userFunc'], 'tx_realurl_advanced')) {
				// Not tx_realurl_advanced, nothing to do
				if ($this->enableDevLog) {
					t3lib_div::devLog('Not tx_realurl_advanced', 'realurl');
				}
				return;
			}
			$field_list = t3lib_div::trimExplode(',', $this->getFieldList($table, $config), true);
			$fields = array();
			foreach (array_keys($incomingFieldArray) as $field) {
				if (in_array($field, $field_list)) {
					$fields[] = $field;
				}
			}
			if (count($fields)) {
				if ($this->enableDevLog) {
					t3lib_div::devLog('Found modified fields of interest', 'realurl', 0, $fields);
				}
				list($values) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(implode(',', $fields), $table, 'uid=' . $id);
				$modified = false;
				foreach ($fields as $field) {
					if (($modified = ($values[$field] != $incomingFieldArray[$field]))) {
						break;
					}
				}
				if ($this->enableDevLog) {
					t3lib_div::devLog('$modified=' . intval($modified), 'realurl', 0, $fields);
				}
				if ($modified) {
					$tceMainId = count($this->tceMainInstList);
					$this->tceMainInstList[$tceMainId] = $pObj->tx_realurl_tcemain_id = uniqid('');
					$this->fieldCollection[$tceMainId][$table][$id] = array(
						'configName' => $domain,
						'realUid' => $realUid,
						'language' => $language,
					);
				}
			}
		}
	}

	/**
	 * TCEmain hook to expire old records and add new ones
	 *
	 * @param	string		$status	'new' (ignoring) or 'update'
	 * @param	string		$table	Table name
	 * @param	int		$id	ID of the record
	 * @param	array		$fieldArray	Fields
	 * @param	t3lib_TCEmain		$pObj	Parent object
	 * @return	void
	 */
	function processDatamap_afterDatabaseOperations($status, $table, $id, $fieldArray, &$pObj) {
		/* @var $pObj t3lib_TCEmain */
		if (isset($pObj->tx_realurl_tcemain_id)) {
			$tceMainInst = array_search($pObj->tx_realurl_tcemain_id, $this->tceMainInstList);
			if ($this->enableDevLog) {
				t3lib_div::devLog('$processDatamap_afterDatabaseOperations', 'realurl', 0, array('status' => $status, 'tceMainInst' => $tceMainInst));
			}
			if ($status == 'update' && $tceMainInst !== false && isset($this->fieldCollection[$tceMainInst][$table][$id])) {
				$configName = &$this->fieldCollection[$tceMainInst][$table][$id]['configName'];
				$config = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$configName];
				// Now we need to call the whole realurl to process the ID. We cannot just
				// call tx_realurl_advanced because tx_realurl_advanced needs
				// tx_realurl as parent object
				$userFunc = $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['tx_realurl'];
				$savedAutoUpdatePathCache = $config['pagePath']['autoUpdatePathCache'];
				$config['pagePath']['autoUpdatePathCache'] = true;
				$url = 'index.php?id=' . $this->fieldCollection[$tceMainInst][$table][$id]['realUid'];
				if ($table == 'pages_language_overlay') {
					$url .= '&' . ($config['pagePath']['languageGetVar'] ? $config['pagePath']['languageGetVar'] : 'L') . '=' . $config['language'];
				}
				$params = array(
					'LD' => array(
						'totalURL' => $url
					),
					'TCEmainHook' => true
				);

				$tsfe = $GLOBALS['TSFE'];
				if ($this->createTSFE($this->fieldCollection[$tceMainInst][$table][$id]['realUid'])) {
					// Here only if we can create speaking URL for the page (page is not sysfolder, etc)
					$parent = $GLOBALS['TSFE'];
					if ($this->enableDevLog) {
						t3lib_div::devLog('Calling FE', 'realurl');
					}
					t3lib_div::callUserFunction($userFunc, $params, $parent);	// Note: encodeSpUrl does not use parent object at all!
					$config['pagePath']['autoUpdatePathCache'] = $savedAutoUpdatePathCache;
				}
				$GLOBALS['TSFE'] = $tsfe;

				// Clean up to help PHP free memory more efficiently
				unset($this->fieldCollection[$tceMainInst][$table][$id]);
				if (count($this->fieldCollection[$tceMainInst][$table]) == 0) {
					unset($this->fieldCollection[$tceMainInst][$table]);
					if (count($this->fieldCollection[$tceMainInst]) == 0) {
						unset($this->fieldCollection[$tceMainInst]);
						unset($this->tceMainInstList[$tceMainInst]);
					}
				}
			}
		}
		// Clear caches if exclude flag was changed
		if ($status == 'update' && $table == 'pages' &&
				(isset($fieldArray['tx_realurl_exclude']) ||
				isset($fieldArray['shortcut_mode']) ||
				isset($fieldArray['doktype']))) {
			$this->clearBranchCache(intval($id), isset($fieldArray['tx_realurl_exclude']));
		}
	}

	/**
	 * Clears branch cache for the given page
	 *
	 * @param	int	$id	Page id
	 * @param	boolean	$recurse	true if should clear cache for subpages
	 * @return	void
	 */
	function clearBranchCache($id, $recurse) {
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_urldecodecache',
				'page_id=' . $id);
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_urlencodecache',
				'page_id=' . $id);
		$GLOBALS['TYPO3_DB']->exec_DELETEquery('tx_realurl_pathcache',
				'page_id=' . $id . ' AND expire=0');
		if ($recurse) {
			// Recurse to sub pages
			$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid', 'pages',
					'deleted=0 AND pid=' . $id);
			foreach ($rows as $row) {
				$this->clearBranchCache($row['uid'], $recurse);
			}
		}
	}

	/**
	 * Retrieves field list to check for modification
	 *
	 * @param	string		$table	Table name
	 * @param	array		$config	Configuration for this
	 * @return	string		Comma-separated field list
	 */
	function getFieldList($table, &$config) {
		if ($table == 'pages_language_overlay') {
			return TX_REALURL_SEGTITLEFIELDLIST_PLO;
		}
		return isset($config['pagePath']['segTitleFieldList']) ? $config['pagePath']['segTitleFieldList'] : TX_REALURL_SEGTITLEFIELDLIST_DEFAULT;
	}

	/**
	 * Retrieves RealURL configuration for given pid
	 *
	 * @param	int		$pid	Page uid
	 * @return	mixed		Configuration array or false
	 */
	function getConfigForPage($pid) {
		$rootline = t3lib_BEfunc::BEgetRootLine($pid);
		if (($domain = t3lib_BEfunc::firstDomainRecord($rootline))) {
			if ($this->enableDevLog) {
				t3lib_div::devLog('Found domain record', 'realurl', 0, $domain);
			}
			if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$domain])) {
				$config = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$domain];
				while (is_string($config)) {
					$domain = $config;
					$config = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$config];
				}
				return array($domain, $config);
			}
		}
		if ($this->enableDevLog) {
			t3lib_div::devLog('Checking default', 'realurl', 0, intval(isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT'])));
		}
		return (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT']) ? array('_DEFAULT', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DEFAULT']) : false);
	}

	/**
	 * Retrieves real page id given its overlay id
	 *
	 * @param	int		$pid	Page id
	 * @return	array		Array with two members: real page uid and sys_language_uid
	 */
	function getInfoFromOverlayPid($pid) {
		list($rec) = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('pid,sys_language_uid', 'pages_language_overlay', 'uid=' . intval($pid));
		return (is_array($rec) ? array($rec['uid'], $rec['sys_language_overlay']) : false);
	}

	/**
	 * Creates TSFE for executing RealURL. Code idea comes from vara_feurlfrombe extension.
	 *
	 * @param	int		$pid	Page uid
	 * @return	void
	 */
	function createTSFE($pid) {
		require_once(PATH_site.'typo3/sysext/cms/tslib/class.tslib_fe.php');
		require_once(PATH_site.'t3lib/class.t3lib_userauth.php');
		require_once(PATH_site.'typo3/sysext/cms/tslib/class.tslib_feuserauth.php');
		require_once(PATH_site.'t3lib/class.t3lib_cs.php');
		require_once(PATH_site.'typo3/sysext/cms/tslib/class.tslib_content.php') ;
		require_once(PATH_site.'t3lib/class.t3lib_tstemplate.php');
		require_once(PATH_site.'t3lib/class.t3lib_page.php');
		require_once(PATH_site.'t3lib/class.t3lib_timetrack.php');

		$temp_TTclassName = t3lib_div::makeInstanceClassName('t3lib_timeTrack');
		$GLOBALS['TT'] = new $temp_TTclassName();
		$GLOBALS['TT']->start();

		// Finds the TSFE classname
		$TSFEclassName = t3lib_div::makeInstanceClassName('tslib_fe');

		// Create the TSFE class.
		$GLOBALS['TSFE'] = new $TSFEclassName($GLOBALS['TYPO3_CONF_VARS'], $pid, '0', 0, '','','','');

		$GLOBALS['TSFE']->config['config']['language']='default';

		// Fire all the required function to get the typo3 FE all set up.
		$GLOBALS['TSFE']->id = $pid;
		$GLOBALS['TSFE']->connectToMySQL();

		// Prevent mysql debug messages from messing up the output
		$sqlDebug = $GLOBALS['TYPO3_DB']->debugOutput;
		$GLOBALS['TYPO3_DB']->debugOutput = false;

		$GLOBALS['TSFE']->initLLVars();
		$GLOBALS['TSFE']->initFEuser();

		// Look up the page
		$GLOBALS['TSFE']->sys_page = t3lib_div::makeInstance('t3lib_pageSelect');
		$GLOBALS['TSFE']->sys_page->init($GLOBALS['TSFE']->showHiddenPage);

		// If the page is not found (if the page is a sysfolder, etc), then return no URL, preventing any further processing which would result in an error page.
		$page = $GLOBALS['TSFE']->sys_page->getPage($pid);

		if (count($page) == 0) {
			$GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
			return false;
		}

		// If the page is a shortcut, look up the page to which the shortcut references, and do the same check as above.
		if ($page['doktype']==4 && count($GLOBALS['TSFE']->getPageShortcut($page['shortcut'],$page['shortcut_mode'],$page['uid'])) == 0) {
			$GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
			return false;
		}

		// Spacer pages and sysfolders result in a page not found page too...
		if ($page['doktype'] == 199 || $page['doktype'] == 254) {
			$GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
			return false;
		}

		$GLOBALS['TSFE']->getPageAndRootline();
		$GLOBALS['TSFE']->initTemplate();
		$GLOBALS['TSFE']->forceTemplateParsing = 1;

		// Find the root template
		$GLOBALS['TSFE']->tmpl->start($GLOBALS['TSFE']->rootLine);

		// Fill the pSetup from the same variables from the same location as where tslib_fe->getConfigArray will get them, so they can be checked before this function is called
		$GLOBALS['TSFE']->sPre = $GLOBALS['TSFE']->tmpl->setup['types.'][$GLOBALS['TSFE']->type];	 // toplevel - objArrayName
		$GLOBALS['TSFE']->pSetup = $GLOBALS['TSFE']->tmpl->setup[$GLOBALS['TSFE']->sPre.'.'];

		// If there is no root template found, there is no point in continuing which would result in a 'template not found' page and then call exit php. Then there would be no clickmenu at all.
		// And the same applies if pSetup is empty, which would result in a "The page is not configured" message.
		if (!$GLOBALS['TSFE']->tmpl->loaded || ($GLOBALS['TSFE']->tmpl->loaded && !$GLOBALS['TSFE']->pSetup)) {
			$GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
			return false;
		}

		$GLOBALS['TSFE']->getConfigArray();

		$GLOBALS['TSFE']->inituserGroups();
		$GLOBALS['TSFE']->connectToDB();
		$GLOBALS['TSFE']->determineId();

		$GLOBALS['TYPO3_DB']->debugOutput = $sqlDebug;
		return true;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_tcemain.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_tcemain.php']);
}

?>