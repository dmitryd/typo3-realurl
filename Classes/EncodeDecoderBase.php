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
namespace DmitryDulepov\Realurl;

use DmitryDulepov\Realurl\Configuration\ConfigurationReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class contains common methods for RealURL encoder and decoder.
 *
 * @package DmitryDulepov\Realurl
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
abstract class EncodeDecoderBase {

	const URL_CACHE_ID = 'realurl_url_cache';

	/** @var \TYPO3\CMS\Core\Cache\Frontend\FrontendInterface */
	protected $urlCache = NULL;

	/** @var \DmitryDulepov\Realurl\Configuration\ConfigurationReader */
	protected $configuration;

	/** @var \TYPO3\CMS\Core\Database\DatabaseConnection */
	protected $databaseConnection;

	/** @var array */
	static protected $pageTitleFields = array('tx_realurl_pathsegment', 'alias', 'nav_title', 'title', 'uid');

	/** @var int */
	protected $rootPageId;

	/** @var \DmitryDulepov\Realurl\Utility */
	protected $utility;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
		$this->configuration = ConfigurationReader::getInstance();
		$this->rootPageId = (int)$this->configuration->get('pagePath/rootpage_id');
		$this->utility = Utility::getInstance();
		$this->initializeCaches();
	}

	/**
	 * Creates a cache key for the given path.
	 *
	 * @param string $path
	 * @return string
	 */
	public function getCacheKey($path) {
		return $this->rootPageId . '_' . sha1($this->getSortedUrl($path));
	}

	/**
	 * Initializes the cache for URLs.
	 *
	 * @return void
	 */
	protected function initializeCaches() {
		// TODO Disable caches if BE user is logged in
		$cacheManager = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Cache\\CacheManager');
		/** @var \TYPO3\CMS\Core\Cache\CacheManager $cacheManager */
		if ($cacheManager->hasCache(self::URL_CACHE_ID)) {
			$this->urlCache = $cacheManager->getCache(self::URL_CACHE_ID);
		}
	}

	/**
	 * Obtains URL with all query parameters sorted.
	 *
	 * @param string $url
	 * @return string
	 */
	protected function getSortedUrl($url) {
		$urlParts = parse_url($url);
		$sortedUrl = $urlParts['path'];
		if ($urlParts['query']) {
			parse_str($url, $pathParts);
			$this->sortArrayDeep($pathParts);
			$sortedUrl .= '?' . ltrim(GeneralUtility::implodeArrayForUrl('', $pathParts), '&');
		}

		return $sortedUrl;
	}

	/**
	 * Sets the entry to cache.
	 *
	 * @param string $cacheKey
	 * @param array $cacheInfo
	 */
	protected function putToUrlCache($cacheKey, array $cacheInfo) {
		if ($this->urlCache && $cacheInfo['id']) {
			$this->urlCache->set($cacheKey, $cacheInfo);
		}
	}

	/**
	 * Sorts the array deeply.
	 *
	 * @param array $pathParts
	 * @return void
	 */
	protected function sortArrayDeep(array &$pathParts) {
		if (count($pathParts) > 1) {
			ksort($pathParts);
		}
		foreach ($pathParts as &$part) {
			if (is_array($part)) {
				$this->sortArrayDeep($part);
			}
		}
	}
}
