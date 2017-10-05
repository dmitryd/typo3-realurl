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
namespace DmitryDulepov\Realurl\Hooks;

use DmitryDulepov\Realurl\Cache\CacheFactory;
use DmitryDulepov\Realurl\Cache\CacheInterface;
use DmitryDulepov\Realurl\EncodeDecoderBase;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\MathUtility;

class DataHandler implements SingletonInterface {

	/** @var CacheInterface */
	protected $cache;

	/** @var \TYPO3\CMS\Dbal\Database\DatabaseConnection */
	protected $databaseConnection;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->cache = CacheFactory::getCache();
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
		EncodeDecoderBase::overwritePageTitleFieldsFromConfiguration();
	}

	/**
	 * Clears path and URL caches if the page was deleted.
	 *
	 * @param string $table
	 * @param string|int $id
	 */
	public function processCmdmap_deleteAction($table, $id) {
		if (($table === 'pages' || $table === 'pages_language_overlay') && MathUtility::canBeInterpretedAsInteger($id)) {
			if ($table === 'pages_language_overlay') {
				$record = BackendUtility::getRecord($table, $id);
				$id = $record['pid'];
			}
			$this->cache->clearPathCacheForPage((int)$id);
			$this->cache->clearUrlCacheForPage((int)$id);
			$this->clearUrlCacheForAliasChanges($table, (int)$id);
		}
	}

	/**
	 * Expires caches if the page was moved.
	 *
	 * @param string $command
	 * @param string $table
	 * @param int $id
	 */
	public function processCmdmap_postProcess($command, $table, $id) {
		if ($command === 'move' && $table === 'pages') {
			$this->expireCachesForPageAndSubpages((int)$id, 0);

			$languageOverlays = $this->getRecordsByField('pages_language_overlay', 'pid', $id);
			if (is_array($languageOverlays)) {
				foreach ($languageOverlays as $languageOverlay) {
					$this->expireCachesForPageAndSubpages($languageOverlay['pid'], $languageOverlay['sys_language_uid']);
				}
			}
		}
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
	 */
	public function processDatamap_afterDatabaseOperations($status, $tableName, $recordId, array $databaseData, /** @noinspection PhpUnusedParameterInspection */ \TYPO3\CMS\Core\DataHandling\DataHandler $dataHandler) {
		if (!MathUtility::canBeInterpretedAsInteger($recordId)) {
			$recordId = (int)$dataHandler->substNEWwithIDs[$recordId];
		}
		$this->expireCache($status, $tableName, $recordId, $databaseData);
		$this->clearAutoConfiguration($tableName, $databaseData);
		if ($status !== 'new') {
			$this->clearUrlCacheForAliasChanges($tableName, (int)$recordId);
		}
	}

	/**
	 * Clears automatic configuration when necessary. Note: we do not check if
	 * it is enabled. Even if now it is disabled, later it can be re-enabled
	 * and suddenly obsolete config will be used. So we clear always.
	 *
	 * @param string $tableName
	 * @param array $databaseData
	 */
	protected function clearAutoConfiguration($tableName, array $databaseData) {
		if ($tableName === 'sys_domain' || $tableName === 'pages' && isset($databaseData['is_siteroot'])) {
			if (file_exists(PATH_site . TX_REALURL_AUTOCONF_FILE)) {
				@unlink(PATH_site . TX_REALURL_AUTOCONF_FILE);
			}
		}
	}

	/**
	 * Clears URL cache if it is found in the alias table.
	 *
	 * @param string $tableName
	 * @param int $recordId
	 * @return void
	 */
	protected function clearUrlCacheForAliasChanges($tableName, $recordId) {
		if (!preg_match('/^(?:sys_|cf_)/', $tableName)) {
			if ($tableName == 'pages_language_overlay') {
				$tableName = 'pages';
			}
			$languageId = 0;
			if (isset($GLOBALS['TCA'][$tableName]['ctrl']['languageField']) && isset($GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'])) {
				// tx_realurl_uniqalias* tables store uid of records in the
				// main language. We have to find the uid of the current
				// record in the main language to proceed.
				//
				// We disable deleteClause here because this function is also
				// called when the aliases record is deleted.
				$record = BackendUtility::getRecord($tableName, $recordId, '*', '', false);
				if ($record) {
					$languageField = $GLOBALS['TCA'][$tableName]['ctrl']['languageField'];
					$languageId = (int)$record[$languageField];
					if ($languageId > 0) {
						// Can't use !== 0 here because we need to ignore "$languageId === -1"
						$transOrigPointerField = $GLOBALS['TCA'][$tableName]['ctrl']['transOrigPointerField'];
						$recordId = (int)$record[$transOrigPointerField];
						unset($transOrigPointerField);
					}
				}
				unset($record);
			}
			$expirationTime = time() + 30*24*60*60;
			// This check would be sufficient for most cases but only when id_field is 'uid' in the configuration
			$result = $this->databaseConnection->sql_query(
				'SELECT tx_realurl_uniqalias.uid,expire,url_cache_id FROM ' .
				'tx_realurl_uniqalias LEFT JOIN tx_realurl_uniqalias_cache_map ON tx_realurl_uniqalias.uid=alias_uid ' .
				'WHERE tablename=' . $this->databaseConnection->fullQuoteStr($tableName, 'tx_realurl_uniqalias') . ' ' .
				'AND value_id=' . $recordId . ' AND lang=' . $languageId
			);
			while (FALSE !== ($data = $this->databaseConnection->sql_fetch_assoc($result))) {
				if ($data['url_cache_id']) {
					$this->cache->expireUrlCacheById($data['url_cache_id'], $expirationTime);
				}
				if ((int)$data['expire'] === 0) {
					$this->databaseConnection->exec_UPDATEquery('tx_realurl_uniqalias', 'uid=' . (int)$data['uid'], array(
						'expire' => $expirationTime
					));
				}
			}
			$this->databaseConnection->sql_free_result($result);
		}
	}

	/**
	 * Expires cache if necessary when the record changes. For 'pages' we expire
	 * cache only if the page was modified. For 'pages_language_overlay' we
	 * do it on creation of a new translation too. For reasons see
	 * https://github.com/dmitryd/typo3-realurl/issues/313#issuecomment-257268851
	 *
	 * @param string $status
	 * @param string $tableName
	 * @param string|int $recordId
	 * @param array $databaseData
	 * @return void
	 */
	protected function expireCache($status, $tableName, $recordId, array $databaseData) {
		if ($status === 'update' && $tableName === 'pages' || $tableName === 'pages_language_overlay') {
			if ($tableName == 'pages') {
				$languageId = 0;
				$pageId = $recordId;
			} else {
				$fullRecord = BackendUtility::getRecord($tableName, $recordId);
				$pageId = $fullRecord['pid'];
				$languageId = $fullRecord['sys_language_uid'];
				unset($fullRecord);
			}
			$expireCache = FALSE;
			if (isset($databaseData['hidden'])) {
				$expireCache = TRUE;
			} else if (isset($databaseData['tx_realurl_pathoverride']) || isset($databaseData['tx_realurl_exclude'])) {
				$expireCache = TRUE;
				
				// Updating alternative language pages as well
				$languageOverlays = $this->getRecordsByField('pages_language_overlay', 'pid', $pageId);
				if (is_array($languageOverlays)) {
					foreach ($languageOverlays as $languageOverlay) {
						$this->expireCachesForPageAndSubpages($languageOverlay['pid'], $languageOverlay['sys_language_uid']);
					}
				}
			} else {
				foreach (EncodeDecoderBase::$pageTitleFields as $fieldName) {
					if (isset($databaseData[$fieldName])) {
						$expireCache = TRUE;
						break;
					}
				}
			}
			if ($expireCache) {
				$this->expireCachesForPageAndSubpages($pageId, $languageId);
			}
		}
	}

	/**
	 * Expires cache for the page and subpages.
	 *
	 * @param int $pageId
	 * @param int $languageId
	 * @param int $level
	 * @return void
	 */
	protected function expireCachesForPageAndSubpages($pageId, $languageId, $level = 0) {
		if ($pageId) {
			$this->cache->expireCache($pageId, $languageId);
			if ($level++ < 20) {
				$subpages = $this->getRecordsByField('pages', 'pid', $pageId);
				if (is_array($subpages)) {
					$uidList = array();
					foreach ($subpages as $subpage) {
						$uidList[] = (int)$subpage['uid'];
					}
					unset($subpages);
					foreach ($uidList as $uid) {
						$this->expireCachesForPageAndSubpages($uid, $languageId, $level);
					}
				}
			}
		}
	}

	/**
	 * Fetches records from the database by the field name. This is a replacement for the
	 * BackendUtility::getRecordsByField() method, which is deprecated since TYPO3 8.7.
	 *
	 * @param string $tableName
	 * @param string $fieldName
	 * @param mixed $fieldValue
	 * @return array
	 */
	protected function getRecordsByField($tableName, $fieldName, $fieldValue)
	{
		$rows = $this->databaseConnection->exec_SELECTgetRows(
			'*',
			$tableName,
			$fieldName . '=' . $this->databaseConnection->fullQuoteStr($fieldValue, $tableName)
		);

		return (array)$rows;
	}
}
