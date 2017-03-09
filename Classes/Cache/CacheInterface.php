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

/**
 * This interface defines cache functionality as required by RealURL. Other
 * extensions can implement this interface and provide their own cache
 * implementations. The implementation class is set in the RealURL configuration.
 *
 * WARNING! This interface is still not stable! It can be dropped in the future
 * completly!
 *
 * @package DmitryDulepov\Realurl\Cache
 */
interface CacheInterface {

	/**
	 * Removes expired cache entries.
	 *
	 * @return void
	 */
	public function clearExpiredCacheEntries();

	/**
	 * Empties the path cache for one page.
	 *
	 * @param int $pageId
	 * @return void
	 */
	public function clearPathCacheForPage($pageId);

	/**
	 * Empties the URL cache.
	 *
	 * @return void
	 */
	public function clearUrlCache();

	/**
	 * Clears URL cache by cache id.
	 *
	 * @param string $cacheId
	 * @return void
	 */
	public function clearUrlCacheById($cacheId);

	/**
	 * Empties the URL cache for one page.
	 *
	 * @param int $pageId
	 * @return void
	 */
	public function clearUrlCacheForPage($pageId);

	/**
	 * Expires cache for the given page and language.
	 *
	 * @param int $pageId
	 * @param int|null $languageId
	 * @return void
	 */
	public function expireCache($pageId, $languageId = null);

	/**
	 * Expires URL cache by cache id.
	 *
	 * @param string $cacheId
	 * @param int $expirationTime
	 * @return void
	 */
	public function expireUrlCacheById($cacheId, $expirationTime);

	/**
	 * Gets the entry from cache.
	 *
	 * @param int $rootPageId
	 * @param string $originalUrl
	 * @return UrlCacheEntry|null
	 */
	public function getUrlFromCacheByOriginalUrl($rootPageId, $originalUrl);

	/**
	 * Gets the entry from cache.
	 *
	 * @param int $rootPageId
	 * @param string $speakingUrl
	 * @param int|null $languageId
	 * @return UrlCacheEntry|null
	 */
	public function getUrlFromCacheBySpeakingUrl($rootPageId, $speakingUrl, $languageId);

	/**
	 * Obtains non-expired (!) path from the path cache.
	 *
	 * @param int $rootPageId
	 * @param int $languageId
	 * @param int $pageId
	 * @param string $mpVar
	 * @return PathCacheEntry|null
	 */
	public function getPathFromCacheByPageId($rootPageId, $languageId, $pageId, $mpVar);

	/**
	 * Obtains path from the path cache.
	 *
	 * @param int $rootPageId
	 * @param int $languageId
	 * @param string|null $mountPoint null means exclude from search
	 * @param string $pagePath
	 * @return PathCacheEntry|null
	 */
	public function getPathFromCacheByPagePath($rootPageId, $languageId, $mountPoint, $pagePath);

	/**
	 * Puts path to the cache. This must override existing entry if cache id is set in the cache entry.
	 *
	 * @param PathCacheEntry $cacheEntry
	 * @return void
	 */
	public function putPathToCache(PathCacheEntry $cacheEntry);

	/**
	 * Sets the entry to cache. This must override existing entry if cache id is set in the cache entry.
	 *
	 * @param UrlCacheEntry $cacheEntry
	 * @return void
	 */
	public function putUrlToCache(UrlCacheEntry $cacheEntry);

}
