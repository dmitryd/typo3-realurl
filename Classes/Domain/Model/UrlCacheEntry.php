<?php
namespace DmitryDulepov\Realurl\Domain\Model;
/***************************************************************
*  Copyright notice
*
*  (c) 2016 Dmitry Dulepov <dmitry.dulepov@gmail.com>
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
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * This class represents a url cache entry, It is used in the Backend
 * administration module.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class UrlCacheEntry extends AbstractEntity {

	/** @var int */
	protected $expire;

	/** @var string */
	protected $originalUrl;

	/** @var int */
	protected $pageId;

	/** @var string */
	protected $requestVariables;

	/** @var int */
	protected $rootPageId;

	/** @var string */
	protected $speakingUrl;

	/**
	 * @return int
	 */
	public function getExpire() {
		return $this->expire;
	}

	/**
	 * @return string
	 */
	public function getOriginalUrl() {
		return $this->originalUrl;
	}

	/**
	 * @param string $originalUrl
	 */
	public function setOriginalUrl($originalUrl) {
		$this->originalUrl = $originalUrl;
	}

	/**
	 * @return int
	 */
	public function getPageId() {
		return $this->pageId;
	}

	/**
	 * @param int $pageId
	 */
	public function setPageId($pageId) {
		$this->pageId = $pageId;
	}

	/**
	 * @return string
	 */
	public function getRequestVariables() {
		return $this->requestVariables;
	}

	/**
	 * @param string $requestVariables
	 */
	public function setRequestVariables($requestVariables) {
		$this->requestVariables = $requestVariables;
	}

	/**
	 * @return int
	 */
	public function getRootPageId() {
		return $this->rootPageId;
	}

	/**
	 * @param int $rootPageId
	 */
	public function setRootPageId($rootPageId) {
		$this->rootPageId = $rootPageId;
	}

	/**
	 * @return string
	 */
	public function getSpeakingUrl() {
		return $this->speakingUrl;
	}

	/**
	 * @param string $speakingUrl
	 */
	public function setSpeakingUrl($speakingUrl) {
		$this->speakingUrl = $speakingUrl;
	}
}
