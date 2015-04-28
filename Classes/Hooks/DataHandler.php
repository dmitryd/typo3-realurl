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
namespace DmitryDulepov\Realurl\Hooks;

use DmitryDulepov\Realurl\Cache\CacheFactory;
use DmitryDulepov\Realurl\Cache\CacheInterface;
use DmitryDulepov\Realurl\EncodeDecoderBase;
use TYPO3\CMS\Backend\Utility\BackendUtility;

class DataHandler {

	/** @var CacheInterface */
	protected $cache;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->cache = CacheFactory::getCache();
	}

	/**
	 * A DataHandler hook to expire old records.
	 *
	 * @param string $status 'new' (ignoring) or 'update'
	 * @param string $tableName
	 * @param int $recordId
	 * @param array $databaseData
	 * @param \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler
	 * @return void
	 * @todo Expire unique alias cache: how to get the proper timeout value easily here?
	 */
	public function processDatamap_afterDatabaseOperations($status, $tableName, $recordId, array $databaseData, \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler) {
		$this->expirePathCache($status, $tableName, $recordId, $databaseData);
		//$this->processContentUpdates($status, $tableName, $recordId, $databaseData, $dataHandler);
		//$this->clearAutoConfiguration($tableName);
	}

	/**
	 * Expires path cache of necessary when the record changes.
	 *
	 * @param string $status
	 * @param string $tableName
	 * @param string|int $recordId
	 * @param array $databaseData
	 * @return void
	 */
	protected function expirePathCache($status, $tableName, $recordId, array $databaseData) {
		if ($status !== 'new' && ($tableName == 'pages' || $tableName == 'pages_language_overlay')) {
			if ($tableName == 'pages') {
				$languageId = 0;
				$pageId = $recordId;
			}
			else {
				$fullRecord = BackendUtility::getRecord($tableName, $recordId);
				$pageId = $fullRecord['pid'];
				$languageId = $fullRecord['sys_language_uid'];
				unset($fullRecord);
			}
			$expireCache = FALSE;
			foreach (EncodeDecoderBase::$pageTitleFields as $fieldName) {
				if (isset($databaseData[$fieldName])) {
					$expireCache = TRUE;
					break;
				}
			}
			if ($expireCache) {
				$this->expirePathCacheForPageAndSubpages($pageId, $languageId);
			}
		}
	}

	/**
	 * Expires path cache fo the page and subpages.
	 *
	 * @param int $pageId
	 * @param int $languageId
	 * @return void
	 */
	protected function expirePathCacheForPageAndSubpages($pageId, $languageId) {
		$this->cache->expirePathCache($pageId, $languageId);
		$subpages = BackendUtility::getRecordsByField('pages', 'pid', $pageId);
		$uidList = array();
		foreach ($subpages as $subpage) {
			$uidList[] = (int)$subpage['uid'];
		}
		unset($subpages);
		foreach ($uidList as $uid) {
			$this->cache->expirePathCache($uid, $languageId);
			$this->expirePathCacheForPageAndSubpages($uid, $languageId);
		}
	}
}
