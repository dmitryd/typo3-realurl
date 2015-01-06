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
namespace DmitryDulepov\Realurl\Encoder;

use DmitryDulepov\Realurl\EncodeDecoderBase;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * This class contains encoder for the RealURL.
 *
 * @package DmitryDulepov\Realurl\Encoder
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class UrlEncoder extends EncodeDecoderBase {

	/** @var string */
	protected $encodedUrl = '';

	/** @var PageRepository */
	protected $pageRepository;

	/** @var int */
	protected $sysLanguageUid;

	/** @var string */
	protected $urlToEncode;

	/** @var array */
	protected $urlParameters = array();

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		parent::__construct();
		$this->pageRepository = $GLOBALS['TSFE']->sys_page;
	}

	/**
	 * Entry point for the URL encoder.
	 *
	 * @param array $encoderParameters
	 * @return void
	 */
	public function encodeUrl(array &$encoderParameters) {
		$this->urlToEncode = $encoderParameters['LD']['totalURL'];
		if ($this->canEncoderExecute()) {
			$this->executeEncoder();
			if ($this->encodedUrl) {
				$encoderParameters['LD']['totalURL'] = $this->encodedUrl;
			}
		}
	}

	/**
	 * Post-processes the URL. If necessary prepends another domain to the URL.
	 *
	 * @param array $parameters
	 * @param ContentObjectRenderer $pObj
	 * @return void
	 */
	public function postProcessEncodedUrl(array &$parameters, ContentObjectRenderer $pObj) {
		// Nothing for now
	}

	/**
	 * Adds remaining parameters to the generated URL.
	 *
	 * @return void
	 */
	protected function addRemainingUrlParameters() {
		unset($this->urlParameters['id']);
		if (count($this->urlParameters) == 1 && isset($this->urlParameters['cHash'])) {
			unset($this->urlParameters['cHash']);
		}
		elseif (count($this->urlParameters) > 0) {
			$this->encodedUrl .= '?' . ltrim(GeneralUtility::implodeArrayForUrl('', $this->urlParameters), '&');
		}
	}

	/**
	 * Adds an entry to the path cache.
	 *
	 * @param string $pagePath
	 * @return void
	 */
	protected function addToPathCache($pagePath) {
		/** @noinspection PhpUndefinedMethodInspection */
		$GLOBALS['TYPO3_DB']->exec_INSERTquery('tx_realurl_pathcache', array(
			'page_id' => $this->urlParameters['id'],
			'language_id' => $this->sysLanguageUid,
			'rootpage_id' => $this->rootPageId,
			'mpvar' => '',
			'pagepath' => $pagePath,
			'expire' => 0,
		));
	}

	/**
	 * Appends a string to $this->encodedUrl properly handling slashes in between.
	 *
	 * @param string $stringToAppend
	 * @param bool $addSlash
	 * @return void
	 */
	protected function appendToEncodedUrl($stringToAppend, $addSlash = TRUE) {
		if ($stringToAppend) {
			$this->encodedUrl = ($this->encodedUrl ? rtrim($this->encodedUrl, '/') . '/' : '') . trim($stringToAppend, '/');
			if ($addSlash) {
				$this->encodedUrl .= '/';
			}
		}
	}

	/**
	 * Checks if RealURL can encode URLs.
	 *
	 * @return bool
	 */
	protected function canEncoderExecute() {
		return $this->isRealURLEnabled() && !$this->isBackendMode() && !$this->isInWorkspace() && $this->isTypo3Url();
	}

	/**
	 * Creates a path part of the URL.
	 *
	 * @return void
	 */
	protected function createPathComponent() {
		$rooLineUtility = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Utility\\RootlineUtility', $this->urlParameters['id']);
		$rootLine = $rooLineUtility->get();

		array_pop($rootLine);

		$components = array();
		foreach (array_reverse($rootLine) as $page) {
			foreach (self::$pageTitleFields as $field) {
				if ($page[$field]) {
					$segment = $this->utility->convertToSafeString($page[$field]);
					if ($segment) {
						$components[] = $segment;
						$this->appendToEncodedUrl($segment);
						continue 2;
					}
				}
			}
		}

		if (count($components) > 0) {
			$this->addToPathCache(implode('/', $components));
		}
	}

	/**
	 * Encodes the path to the page.
	 *
	 * @return void
	 */
	protected function encodePathComponents() {
		$cacheRecord = $this->getFromPathCache();
		if (is_array($cacheRecord)) {
			$this->appendToEncodedUrl($cacheRecord['pagepath']);
		}
		else {
			$this->createPathComponent();
		}
	}

	/**
	 * Encodes the URL.
	 *
	 * @return void
	 */
	protected function executeEncoder() {
		$this->parseUrlParameters();
		$this->setLanguage();

		// TODO Encode preVars
		$this->encodePathComponents();
		// TODO Encode fixedPostVars
		// TODO Encode postVarSets
		// TODO Handle file name

		$this->addRemainingUrlParameters();

		// TODO Handle cHash

	}

	/**
	 * Fetches the record from the patch cache.
	 *
	 * @return array|null
	 */
	protected function getFromPathCache() {
		/** @noinspection PhpUndefinedMethodInspection */
		return $GLOBALS['TYPO3_DB']->exec_SELECTgetSingleRow('*', 'tx_realurl_pathcache',
			'page_id=' . (int)$this->urlParameters['id'] . ' AND language_id=' . $this->sysLanguageUid .
				' AND rootpage_id=' . (int)$this->rootPageId . ' AND expire=0'
		);
	}

	/**
	 * Checks if system runs in non-live workspace
	 *
	 * @return boolean
	 */
	protected function isBackendMode() {
		return (TYPO3_MODE == 'BE');
	}

	/**
	 * Checks if system runs in non-live workspace
	 *
	 * @return boolean
	 */
	protected function isInWorkspace() {
		$result = false;
		if ($GLOBALS['TSFE']->beUserLogin) {
			$result = ($GLOBALS['BE_USER']->workspace !== 0);
		}
		return $result;
	}

	/**
	 * Checks if RealURl is enabled.
	 *
	 * @return bool
	 */
	protected function isRealURLEnabled() {
		return (bool)$GLOBALS['TSFE']->config['config']['tx_realurl_enable'];
	}

	/**
	 * Checks if a TYPO3 URL is going to be encoded.
	 *
	 * @return bool
	 */
	protected function isTypo3Url() {
		$prefix = $GLOBALS['TSFE']->absRefPrefix . 'index.php';
		return substr($this->urlToEncode, 0, strlen($prefix)) === $prefix;
	}

	/**
	 * Parses query string to a set of key/value inside $this->urlParameters.
	 *
	 * @return void
	 */
	protected function parseUrlParameters() {
		$urlParts = parse_url($this->urlToEncode);
		if ($urlParts['query']) {
			// Can use parse_str() here because we do not need deep arrays here.
			$parts = GeneralUtility::trimExplode('&', $urlParts['query']);
			foreach ($parts as $part) {
				list($parameter, $value) = explode('=', $part);
				$this->urlParameters[$parameter] = $value;
			}
		}
	}

	/**
	 * Sets language for the encoder either from the URl or from the TSFE.
	 *
	 * @return void
	 */
	protected function setLanguage() {
		if (isset($this->urlParameters['L']) && MathUtility::canBeInterpretedAsInteger($this->urlParameters['L'])) {
			$this->sysLanguageUid = (int)$this->urlParameters['L'];
		}
		else {
			$this->sysLanguageUid = (int)$GLOBALS['TSFE']->sys_language_uid;
		}
	}
}
