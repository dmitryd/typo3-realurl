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
namespace DmitryDulepov\Realurl\Cache;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class contains a default implementation for the RealURL cache.
 *
 * @package DmitryDulepov\Realurl\Cache
 */
class DatabaseCache implements CacheInterface, SingletonInterface {

	/** @var int */
	static public $maximumNumberOfRecords = 500000;

	/** @var \TYPO3\CMS\Core\Database\DatabaseConnection */
	protected $databaseConnection;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];

		list($usec, $sec) = explode(' ', microtime());
		mt_srand($sec + $usec * 1000000);
	}

	/**
	 * Removes expired path cache entries.
	 *
	 * @return void
	 */
	public function clearExpiredCacheEntries() {
		$currentTime = time();
		$this->databaseConnection->sql_query('START TRANSACTION');
		$this->databaseConnection->exec_DELETEquery('tx_realurl_pathdata', 'expire>0 AND expire<' . $currentTime);
		$this->databaseConnection->exec_DELETEquery('tx_realurl_urldata', 'expire>0 AND expire<' . $currentTime);
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias', 'expire>0 AND expire<' . $currentTime);
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias_cache_map',
			'alias_uid NOT IN (SELECT uid FROM tx_realurl_uniqalias) OR url_cache_id NOT IN (SELECT uid FROM tx_realurl_urldata)');
		$this->databaseConnection->sql_query('COMMIT');
	}

	/**
	 * Empties the path cache for one page.
	 *
	 * @param int $pageId
	 * @return void
	 */
	public function clearPathCacheForPage($pageId) {
		$this->databaseConnection->exec_DELETEquery('tx_realurl_pathdata', 'page_id=' . (int)$pageId . ' AND expire=0');
	}

	/**
	 * Empties the URL cache.
	 *
	 * @return mixed
	 */
	public function clearUrlCache() {
		$this->databaseConnection->exec_TRUNCATEquery('tx_realurl_urldata');
		$this->databaseConnection->exec_TRUNCATEquery('tx_realurl_uniqalias_cache_map');
	}

	/**
	 * Clears URL cache by cache id.
	 *
	 * @param string $cacheId
	 * @return void
	 */
	public function clearUrlCacheById($cacheId) {
		$this->databaseConnection->exec_DELETEquery('tx_realurl_urldata', 'uid=' . (int)$cacheId);
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias_cache_map', 'url_cache_id=' . (int)$cacheId);
	}

	/**
	 * Empties the URL cache for one page.
	 *
	 * @param int $pageId
	 * @return void
	 */
	public function clearUrlCacheForPage($pageId) {
		$this->databaseConnection->sql_query('DELETE FROM tx_realurl_uniqalias_cache_map WHERE url_cache_id IN (SELECT uid FROM tx_realurl_urldata WHERE page_id=' . (int)$pageId . ')');
		$this->databaseConnection->exec_DELETEquery('tx_realurl_urldata', 'page_id=' . (int)$pageId);
	}

	/**
	 * Expires cache for the given page and language.
	 *
	 * @param int $pageId
	 * @param int|null $languageId
	 * @return void
	 */
	public function expireCache($pageId, $languageId = null) {
		$expirationTime = time() + 30*24*60*60;

		$this->databaseConnection->sql_query('START TRANSACTION');

		$this->databaseConnection->exec_UPDATEquery('tx_realurl_pathdata',
			'page_id=' . (int)$pageId . (!is_null($languageId) ? ' AND language_id=' . (int)$languageId : '') . ' AND expire=0',
			array('expire' => $expirationTime)
		);

		if (is_null($languageId)) {
			$this->databaseConnection->exec_UPDATEquery('tx_realurl_urldata',
				'page_id=' . (int)$pageId . ' AND expire=0',
				array('expire' => $expirationTime)
			);
		}
		else {
			$rows = $this->databaseConnection->exec_SELECTgetRows('*', 'tx_realurl_urldata',
				'page_id=' . (int)$pageId . ' AND expire=0'
			);
			foreach ($rows as $row) {
				$requestVariables = @json_decode($row['request_variables'], TRUE);
				if (is_array($requestVariables) && (int)$requestVariables['L'] === (int)$languageId) {
					$this->databaseConnection->exec_UPDATEquery('tx_realurl_urldata',
						'uid=' . (int)$row['uid'], array('expire' => $expirationTime)
					);
				}
			}
		}

		$this->databaseConnection->sql_query('COMMIT');
	}

	/**
	 * Expires URL cache by cache id.
	 *
	 * @param string $cacheId
	 * @param int $expirationTime
	 * @return void
	 */
	public function expireUrlCacheById($cacheId, $expirationTime) {
		$this->databaseConnection->exec_UPDATEquery('tx_realurl_urldata', 'uid=' . (int)$cacheId . ' AND expire=0', array(
			'expire' => $expirationTime
		));
	}

	/**
	 * Gets the entry from cache.
	 *
	 * @param int $rootPageId
	 * @param string $originalUrl
	 * @return UrlCacheEntry|null
	 */
	public function getUrlFromCacheByOriginalUrl($rootPageId, $originalUrl) {
		$cacheEntry = NULL;

		$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_urldata',
			'rootpage_id=' . (int)$rootPageId . ' AND ' .
				'original_url_hash=' . sprintf('%u', crc32($originalUrl)) . ' AND ' .
				'original_url=' . $this->databaseConnection->fullQuoteStr($originalUrl, 'tx_realurl_urldata'),
				'', 'expire'
		);
		if (is_array($row)) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\UrlCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry */
			$cacheEntry->setCacheId($row['uid']);
			$cacheEntry->setExpiration($row['expire']);
			$cacheEntry->setPageId($row['page_id']);
			$cacheEntry->setRootPageId($row['rootpage_id']);
			$cacheEntry->setOriginalUrl($originalUrl);
			$cacheEntry->setSpeakingUrl($row['speaking_url']);
			$requestVariables = json_decode($row['request_variables'], TRUE);
			// TODO Log a problem here because it must be an array always
			$cacheEntry->setRequestVariables(is_array($requestVariables) ? $requestVariables : array());
		}

		return $cacheEntry;
	}
	/**
	 * Gets the entry from cache. Language id is needed here because in some
	 * cases URLs can be the same for different languages (_DOMAINS & use alias,
	 * for example).
	 *
	 * We may not fallback to the default language here!
	 *
	 * @param int $rootPageId
	 * @param string $speakingUrl
	 * @param int|null $languageId
	 * @return UrlCacheEntry|null
	 */
	public function getUrlFromCacheBySpeakingUrl($rootPageId, $speakingUrl, $languageId) {
		$cacheEntry = NULL;

		$rows = $this->databaseConnection->exec_SELECTgetRows('*', 'tx_realurl_urldata',
			'rootpage_id=' . (int)$rootPageId . ' AND ' .
				'speaking_url_hash=' . sprintf('%u', crc32($speakingUrl)) . ' AND ' .
				'speaking_url=' . $this->databaseConnection->fullQuoteStr($speakingUrl, 'tx_realurl_urldata'),
				'', 'expire'
		);

		$row = null;
		foreach ($rows as $rowCandidate) {
			$variables = (array)@json_decode($rowCandidate['request_variables'], TRUE);
			if (is_null($languageId)) {
				// No language known, we retrieve only the URL with lowest expiration value
				// See https://github.com/dmitryd/typo3-realurl/issues/250
				if (is_null($row) || $rowCandidate['expire'] <= $row['expire']) {
					$row = $rowCandidate;
					if (isset($variables['cHash'])) {
						break;
					}
				}
			}
			else {
				// Should check for language match
				// See https://github.com/dmitryd/typo3-realurl/issues/103
				if (isset($variables['L'])) {
					if ((int)$variables['L'] === (int)$languageId) {
						// Found language!
						if (is_null($row) || $rowCandidate['expire'] <= $row['expire']) {
							$row = $rowCandidate;
							if (isset($variables['cHash'])) {
								break;
							}
						}
					}
				}
				elseif ($languageId === 0 && is_null($row)) {
					// No L in URL parameters of the URL but default language requested. This is a match.
					$row = $rowCandidate;
				}
			}
		}

		if (is_array($row)) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\UrlCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry */
			$cacheEntry->setCacheId($row['uid']);
			$cacheEntry->setExpiration($row['expire']);
			$cacheEntry->setPageId($row['page_id']);
			$cacheEntry->setRootPageId($row['rootpage_id']);
			$cacheEntry->setOriginalUrl($row['original_url']);
			$cacheEntry->setSpeakingUrl($speakingUrl);
			$requestVariables = @json_decode($row['request_variables'], TRUE);
			// TODO Log a problem here because it must be an array always
			$cacheEntry->setRequestVariables(is_array($requestVariables) ? $requestVariables : array());
		}

		return $cacheEntry;
	}

	/**
	 * Obtains non-expired (!) path from the path cache.
	 *
	 * @param int $rootPageId
	 * @param int $languageId
	 * @param int $pageId
	 * @param string $mpVar
	 * @return PathCacheEntry|null
	 */
	public function getPathFromCacheByPageId($rootPageId, $languageId, $pageId, $mpVar) {
		$cacheEntry = NULL;

		$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_pathdata',
			'page_id=' . (int)$pageId .
				' AND language_id=' . (int)$languageId .
				' AND rootpage_id=' . (int)$rootPageId .
				' AND mpvar=' . ($mpVar ? $this->databaseConnection->fullQuoteStr($mpVar, 'tx_realurl_pathdata') : '\'\'') .
				' AND expire=0'
		);
		if (is_array($row)) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $cacheEntry */
			$cacheEntry->setCacheId((int)$row['uid']);
			$cacheEntry->setExpiration((int)$row['expire']);
			$cacheEntry->setLanguageId((int)$row['language_id']);
			$cacheEntry->setMountPoint($row['mpvar']);
			$cacheEntry->setPageId((int)$row['page_id']);
			$cacheEntry->setPagePath($row['pagepath']);
			$cacheEntry->setRootPageId((int)$row['rootpage_id']);
		}

		return $cacheEntry;
	}

	/**
	 * Obtains path from the path cache.
	 *
	 * @param int $rootPageId
	 * @param int $languageId
	 * @param string|null $mountPoint null means exclude from search
	 * @param string $pagePath
	 * @return PathCacheEntry|null
	 */
	public function getPathFromCacheByPagePath($rootPageId, $languageId, $mountPoint, $pagePath) {
		$cacheEntry = NULL;

		$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_pathdata',
			'rootpage_id=' . (int)$rootPageId .
			' AND pagepath=' . $this->databaseConnection->fullQuoteStr($pagePath, 'tx_realurl_pathdata') .
			' AND language_id=' . (int)$languageId .
			(is_null($mountPoint) ? '' : ' AND mpvar=' . ($mountPoint ? $this->databaseConnection->fullQuoteStr($mountPoint, 'tx_realurl_pathdata') : '\'\'')),
			'', 'expire'
		);
		if (is_array($row)) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $cacheEntry */
			$cacheEntry->setCacheId((int)$row['uid']);
			$cacheEntry->setExpiration((int)$row['expire']);
			$cacheEntry->setLanguageId((int)$row['language_id']);
			$cacheEntry->setMountPoint($row['mpvar']);
			$cacheEntry->setPageId((int)$row['page_id']);
			$cacheEntry->setPagePath($row['pagepath']);
			$cacheEntry->setRootPageId((int)$row['rootpage_id']);
		}

		return $cacheEntry;
	}


	/**
	 * Puts path to the cache.
	 *
	 * @param PathCacheEntry $cacheEntry
	 * @return void
	 */
	public function putPathToCache(PathCacheEntry $cacheEntry) {
		$data = array(
			'expire' => $cacheEntry->getExpiration(),
			'language_id' => $cacheEntry->getLanguageId(),
			'mpvar' => $cacheEntry->getMountPoint(),
			'page_id' => $cacheEntry->getPageId(),
			'pagepath' => $cacheEntry->getPagePath(),
			'rootpage_id' => $cacheEntry->getRootPageId(),
		);
		if ($cacheEntry->getCacheId()) {
			// TODO Expire all other entries
			$this->databaseConnection->exec_UPDATEquery('tx_realurl_pathdata',
				'uid=' . $this->databaseConnection->fullQuoteStr($cacheEntry->getCacheId(), 'tx_realurl_pathdata'),
				$data
			);
		} else {
			$this->databaseConnection->exec_INSERTquery('tx_realurl_pathdata', $data);
			$cacheEntry->setCacheId($this->databaseConnection->sql_insert_id());
			$this->limitTableRecords('tx_realurl_pathdata');
		}
	}

	/**
	 * Sets the entry to cache.
	 *
	 * @param UrlCacheEntry $cacheEntry
	 * @return void
	 */
	public function putUrlToCache(UrlCacheEntry $cacheEntry) {
		$requestVariables = $cacheEntry->getRequestVariables();
		$data = array(
			'expire' => $cacheEntry->getExpiration(),
			'original_url' => $cacheEntry->getOriginalUrl(),
			'original_url_hash' => sprintf('%u', crc32($cacheEntry->getOriginalUrl())),
			'page_id' => $cacheEntry->getPageId(),
			'request_variables' => json_encode($requestVariables),
			'rootpage_id' => $cacheEntry->getRootPageId(),
			'speaking_url' => $cacheEntry->getSpeakingUrl(),
			'speaking_url_hash' => sprintf('%u', crc32($cacheEntry->getSpeakingUrl())),
		);
		if ($cacheEntry->getCacheId()) {
			$this->databaseConnection->exec_UPDATEquery('tx_realurl_urldata',
				'uid=' . $this->databaseConnection->fullQuoteStr($cacheEntry->getCacheId(), 'tx_realurl_urldata'),
				$data
			);
		} else {

			if ($this->limitTableRecords('tx_realurl_urldata')) {
				$this->databaseConnection->sql_query('DELETE FROM tx_realurl_uniqalias_cache_map WHERE url_cache_id NOT IN (SELECT uid FROM tx_realurl_urldata)');
			}

			// Remove expired URLs with the same path
			$languageStatement = '';
			if (isset($requestVariables['L'])) {
				$languageStatement = ' AND request_variables LIKE \'%"L":"' . (int)$requestVariables['L'] . '"%\'';
			}
			$this->databaseConnection->exec_DELETEquery('tx_realurl_urldata',
				'rootpage_id=' . (int)$cacheEntry->getRootPageId() . ' AND ' .
					'speaking_url_hash=' . sprintf('%u', crc32($cacheEntry->getSpeakingUrl())) . ' AND ' .
					'expire>0 AND ' .
					'speaking_url=' . $this->databaseConnection->fullQuoteStr($cacheEntry->getSpeakingUrl(), 'tx_realurl_urldata') .
					$languageStatement
			);

			// Add this entry
			$data['crdate'] = time();
			$this->databaseConnection->exec_INSERTquery('tx_realurl_urldata', $data);
			$cacheEntry->setCacheId($this->databaseConnection->sql_insert_id());
		}
	}

	/**
	 * Limits amount of records in the table. This does not run often.
	 * Records are removed in the uid order (oldest first). This is not a true
	 * clean up, which would be based on the last access timestamp but good
	 * enough to maintain performance.
	 *
	 * @param string $tableName
	 * @return bool
	 */
	protected function limitTableRecords($tableName) {
		$cleanedUp = false;
		if ((mt_rand(0, mt_getrandmax()) % 5000) == 0) {
			// Using exec_SELECTgetRows instead of exec_SELECTsingleRow because we need to set the limit
			list($row) = $this->databaseConnection->exec_SELECTgetRows('uid', $tableName,
				'', '', 'uid DESC', self::$maximumNumberOfRecords . ',1'
			);
			if (is_array($row)) {
				$this->databaseConnection->exec_DELETEquery($tableName, 'uid<=' . $row['uid']);
			}
			$cleanedUp = ($this->databaseConnection->sql_affected_rows() > 0);
		}

		return $cleanedUp;
	}
}
