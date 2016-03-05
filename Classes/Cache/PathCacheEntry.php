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
 * This class contains a information needed to decode the path component of the URL.
 *
 * @package DmitryDulepov\Realurl\Cache
 */
class PathCacheEntry {

	/** @var string */
	protected $cacheId = '';

	/** @var int */
	protected $expiration = 0;

	/** @var int */
	protected $languageId = 0;

	/** @var string */
	protected $mountPoint = '';

	/** @var int */
	protected $pageId = 0;

	/** @var string */
	protected $pagePath = '';

	/** @var int */
	protected $rootPageId = 0;

	/**
	 * Resets cache_id on cloning.
	 */
	public function __clone() {
		$this->cacheId = '';
	}

	/**
	 * @return string
	 */
	public function getCacheId() {
		return $this->cacheId;
	}

	/**
	 * @return int
	 */
	public function getExpiration() {
		return $this->expiration;
	}

	/**
	 * @return int
	 */
	public function getLanguageId() {
		return $this->languageId;
	}

	/**
	 * @return string
	 */
	public function getMountPoint() {
		return $this->mountPoint;
	}

	/**
	 * @return int
	 */
	public function getPageId() {
		return $this->pageId;
	}

	/**
	 * @return string
	 */
	public function getPagePath() {
		return $this->pagePath;
	}

	/**
	 * @return int
	 */
	public function getRootPageId() {
		return $this->rootPageId;
	}

	/**
	 * @param string $cacheId
	 */
	public function setCacheId($cacheId) {
		$this->cacheId = $cacheId;
	}

	/**
	 * @param int $expiration
	 */
	public function setExpiration($expiration) {
		$this->expiration = $expiration;
	}

	/**
	 * @param int $languageId
	 */
	public function setLanguageId($languageId) {
		$this->languageId = $languageId;
	}

	/**
	 * @param string $mountPoint
	 */
	public function setMountPoint($mountPoint) {
		$this->mountPoint = $mountPoint;
	}

	/**
	 * @param int $pageId
	 */
	public function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * @param string $pagePath
	 */
	public function setPagePath($pagePath) {
		$this->pagePath = $pagePath;
	}

	/**
	 * @param int $rootPageId
	 */
	public function setRootPageId($rootPageId) {
		$this->rootPageId = $rootPageId;
	}

}
