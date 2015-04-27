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
namespace DmitryDulepov\Realurl\Cache;

use TYPO3\CMS\Core\SingletonInterface;

/**
 * This class contains a dummy cache for the RealURL. It is used when RealURL
 * runs with the Backend user logged in.
 *
 * @package DmitryDulepov\Realurl\Cache
 */
class NullCache implements CacheInterface, SingletonInterface {

	/**
	 * Empties the URL cache.
	 *
	 * @return mixed
	 */
	public function clearUrlCache() {
		// Do nothing
	}

	/**
	 * Empties the URL cache for one page.
	 *
	 * @param int $pageId
	 * @return void
	 */
	public function clearUrlCacheForPage($pageId) {
		// Do nothing
	}

	/**
	 * Gets the entry from cache.
	 *
	 * @param int $rootPageId
	 * @param string $originalUrl
	 * @return UrlCacheEntry|null
	 */
	public function getUrlFromCacheByOriginalUrl($rootPageId, $originalUrl) {
		return NULL;
	}

	/**
	 * Gets the entry from cache.
	 *
	 * @param int $rootPageId
	 * @param string $speakingUrl
	 * @return UrlCacheEntry|null
	 */
	public function getUrlFromCacheBySpeakingUrl($rootPageId, $speakingUrl) {
		return NULL;
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
		return NULL;
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
		return NULL;
	}

	/**
	 * Puts path to the cache.
	 *
	 * @param PathCacheEntry $cacheEntry
	 * @return void
	 */
	public function putPathToCache(PathCacheEntry $cacheEntry) {
		// Do nothing
	}

	/**
	 * Sets the entry to cache.
	 *
	 * @param UrlCacheEntry $cacheEntry
	 */
	public function putUrlToCache(UrlCacheEntry $cacheEntry) {
		// Do nothing
	}

}
