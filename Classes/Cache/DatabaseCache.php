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

	/** @var \TYPO3\CMS\Core\Database\DatabaseConnection */
	protected $databaseConnection;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Empties the URL cache.
	 *
	 * @return mixed
	 */
	public function clearUrlCache() {
		$this->databaseConnection->exec_TRUNCATEquery('tx_realurl_urlcache');
	}

	/**
	 * Empties the URL cache for one page.
	 *
	 * @param int $pageId
	 * @return void
	 */
	public function clearUrlCacheForPage($pageId) {
		$this->databaseConnection->exec_DELETEquery('tx_realurl_urlcache', 'page_id=' . (int)$pageId);
	}

	/**
	 * Expires path cache for the given page and language.
	 *
	 * @param int $pageId
	 * @param int $languageId
	 * @return void
	 */
	public function expirePathCache($pageId, $languageId) {
		$this->databaseConnection->exec_UPDATEquery('tx_realurl_pathcache',
			'page_id=' . (int)$pageId . ' AND language_id=' . (int)$languageId . ' AND expire=0',
			array('expire' => time() + 30*24*60*60)
		);
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

		$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_urlcache',
			'rootpage_id=' . $rootPageId . ' AND ' .
				'original_url=' . $this->databaseConnection->fullQuoteStr($originalUrl, 'tx_realurl_urlcache')
		);
		if (is_array($row)) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\UrlCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry */
			$cacheEntry->setCacheId($row['cache_id']);
			$cacheEntry->setPageId($row['page_id']);
			$cacheEntry->setRootPageId($row['rootpage_id']);
			$cacheEntry->setOriginalUrl($originalUrl);
			$cacheEntry->setSpeakingUrl($row['speaking_url']);
			$requestVariables = json_decode($row['request_variables'], TRUE);
			// TODO Log a problem here because it must be an array always
			$cacheEntry->setRequestVariables(is_array($requestVariables) ? $requestVariables : array());

			// Update timestamp
			$currentTime = time();
			if ((int)$row['tstamp'] !== $currentTime) {
				$this->databaseConnection->exec_UPDATEquery('tx_realurl_urlcache',
					'cache_id=' . $this->databaseConnection->fullQuoteStr($cacheEntry->getCacheId(), 'tx_realurl_urlcache'),
					array('tstamp' => $currentTime)
				);
			}
		}

		return $cacheEntry;
	}
	/**
	 * Gets the entry from cache.
	 *
	 * @param int $rootPageId
	 * @param string $speakingUrl
	 * @return UrlCacheEntry|null
	 */
	public function getUrlFromCacheBySpeakingUrl($rootPageId, $speakingUrl) {
		$cacheEntry = NULL;

		$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_urlcache',
			'rootpage_id=' . $rootPageId . ' AND ' .
				'speaking_url=' . $this->databaseConnection->fullQuoteStr($speakingUrl, 'tx_realurl_urlcache')
		);
		if (is_array($row)) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\UrlCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\UrlCacheEntry $cacheEntry */
			$cacheEntry->setCacheId($row['cache_id']);
			$cacheEntry->setPageId($row['page_id']);
			$cacheEntry->setRootPageId($row['rootpage_id']);
			$cacheEntry->setOriginalUrl($row['original_url']);
			$cacheEntry->setSpeakingUrl($speakingUrl);
			$requestVariables = json_decode($row['request_variables'], TRUE);
			// TODO Log a problem here because it must be an array always
			$cacheEntry->setRequestVariables(is_array($requestVariables) ? $requestVariables : array());

			// Update timestamp
			$currentTime = time();
			if ((int)$row['tstamp'] !== $currentTime) {
				$this->databaseConnection->exec_UPDATEquery('tx_realurl_urlcache',
					'cache_id=' . $this->databaseConnection->fullQuoteStr($cacheEntry->getCacheId(), 'tx_realurl_urlcache'),
					array('tstamp' => $currentTime)
				);
			}
		}

		return $cacheEntry;
	}

	/**
	 * Obtains non-expired (!) path from the path cache.
	 *
	 * @param int $rootPageId
	 * @param int $languageId
	 * @param int $pageId
	 * @return PathCacheEntry|null
	 */
	public function getPathFromCacheByPageId($rootPageId, $languageId, $pageId) {
		$cacheEntry = NULL;

		$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_pathcache',
			'page_id=' . (int)$pageId . ' AND language_id=' . (int)$languageId .
				' AND rootpage_id=' . (int)$rootPageId . ' AND expire=0'
		);
		if (is_array($row)) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $cacheEntry */
			$cacheEntry->setCacheId((int)$row['cache_id']);
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
	 * @param string $mountPoint
	 * @param string $pagePath
	 * @return PathCacheEntry|null
	 */
	public function getPathFromCacheByPagePath($rootPageId, $mountPoint, $pagePath) {
		$cacheEntry = NULL;

		$row = $this->databaseConnection->exec_SELECTgetSingleRow('*', 'tx_realurl_pathcache',
			'rootpage_id=' . (int)$rootPageId . ' AND pagepath=' . $this->databaseConnection->fullQuoteStr($pagePath, 'tx_realurl_pathcache'),
			'', 'expire'
		);
		if (is_array($row)) {
			$cacheEntry = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\PathCacheEntry');
			/** @var \DmitryDulepov\Realurl\Cache\PathCacheEntry $cacheEntry */
			$cacheEntry->setCacheId((int)$row['cache_id']);
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
			$this->databaseConnection->exec_UPDATEquery('tx_realurl_pathcache',
				'cache_id=' . $this->databaseConnection->fullQuoteStr($cacheEntry->getCacheId(), 'tx_realurl_pathcache'),
				$data
			);
		}
		else {
			$this->databaseConnection->exec_INSERTquery('tx_realurl_pathcache', $data);
			$cacheEntry->setCacheId($this->databaseConnection->sql_insert_id());
		}
	}

	/**
	 * Sets the entry to cache.
	 *
	 * @param UrlCacheEntry $cacheEntry
	 * @return void
	 */
	public function putUrlToCache(UrlCacheEntry $cacheEntry) {
		$data = array(
			'original_url' => $cacheEntry->getOriginalUrl(),
			'page_id' => $cacheEntry->getPageId(),
			'request_variables' => json_encode($cacheEntry->getRequestVariables()),
			'rootpage_id' => $cacheEntry->getRootPageId(),
			'speaking_url' => $cacheEntry->getSpeakingUrl(),
			'tstamp' => time(),
		);
		if ($cacheEntry->getCacheId()) {
			$this->databaseConnection->exec_UPDATEquery('tx_realurl_urlcache',
				'cache_id=' . $this->databaseConnection->fullQuoteStr($cacheEntry->getCacheId(), 'tx_realurl_urlcache'),
				$data
			);
		}
		else {
			$data['crdate'] = $data['tstamp'];
			$this->databaseConnection->exec_INSERTquery('tx_realurl_urlcache', $data);
			$cacheEntry->setCacheId($this->databaseConnection->sql_insert_id());
		}
	}


}
