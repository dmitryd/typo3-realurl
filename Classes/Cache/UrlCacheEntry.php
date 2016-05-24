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
 * This class contains information needed to decode the speaking URL.
 *
 * @package DmitryDulepov\Realurl\Cache
 */
class UrlCacheEntry {

	/** @var string */
	protected $cacheId = 0;

	/** @var int */
	protected $expiration = 0;

	/** @var string */
	protected $originalUrl = '';

	/** @var int */
	protected $pageId = 0;

	protected $requestVariables = array();

	/** @var int */
	protected $rootPageId = 0;

	/** @var string */
	protected $speakingUrl = '';

	
	
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
		return (int)$this->expiration;
	}

	/**
	 * @return string
	 */
	public function getOriginalUrl() {
		return $this->originalUrl;
	}

	/**
	 * @return int
	 */
	public function getPageId() {
		return $this->pageId;
	}

	/**
	 * @return array
	 */
	public function getRequestVariables() {
		return $this->requestVariables;
	}

	/**
	 * @return int
	 */
	public function getRootPageId() {
		return $this->rootPageId;
	}

	/**
	 * @return string
	 */
	public function getSpeakingUrl() {
		return $this->speakingUrl;
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
	 * @param string $originalUrl
	 */
	public function setOriginalUrl($originalUrl) {
		$this->originalUrl = $originalUrl;
	}

	/**
	 * @param int $pageId
	 */
	public function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * @param array $requestVariables
	 */
	public function setRequestVariables(array $requestVariables) {
		$this->requestVariables = $requestVariables;
	}

	/**
	 * @param int $rootPageId
	 */
	public function setRootPageId($rootPageId) {
		$this->rootPageId = $rootPageId;
	}

	/**
	 * @param string $speakingUrl
	 */
	public function setSpeakingUrl($speakingUrl) {
		$this->speakingUrl = $speakingUrl;
	}
}