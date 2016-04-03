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
namespace DmitryDulepov\Realurl;

use DmitryDulepov\Realurl\Cache\CacheFactory;
use DmitryDulepov\Realurl\Cache\CacheInterface;
use DmitryDulepov\Realurl\Configuration\ConfigurationReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class provides common helper functions for EXT:realurl. Funcions here
 * should be stable but there is no 100% guarantee.
 *
 * @package DmitryDulepov\Realurl
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class Utility {

	/** @var \TYPO3\CMS\Core\Charset\CharsetConverter  */
	protected $csConvertor;

	/** @var ConfigurationReader */
	protected $configuration;

	/**
	 * Initializes the class.
	 *
	 * @param ConfigurationReader $configuration
	 */
	public function __construct(ConfigurationReader $configuration) {
		$this->csConvertor = TYPO3_MODE == 'BE' ? $GLOBALS['LANG']->csConvObj : $GLOBALS['TSFE']->csConvObj;
		$this->configuration = $configuration;
	}

	/**
	 * Converts a given string to a string that can be used as a URL segment.
	 * The result is not url-encoded.
	 *
	 * @param string $string
	 * @param string $spaceCharacter
	 * @return string
	 */
	public function convertToSafeString($string, $spaceCharacter = '-') {
		$processedTitle = $this->csConvertor->conv_case('utf-8', $string, 'toLower');
		$processedTitle = strip_tags($processedTitle);
		$processedTitle = preg_replace('/[ \-+_]+/', $spaceCharacter, $processedTitle);
		$processedTitle = $this->csConvertor->specCharsToASCII('utf-8', $processedTitle);
		$processedTitle = preg_replace('/[^\p{L}0-9' . preg_quote($spaceCharacter) . ']/u', '', $processedTitle);
		$processedTitle = preg_replace('/' . preg_quote($spaceCharacter) . '{2,}/', $spaceCharacter, $processedTitle);
		$processedTitle = trim($processedTitle, $spaceCharacter);

		// TODO Post-processing hook here

		$processedTitle = strtolower($processedTitle);

		return $processedTitle;
	}

	/**
	 * Returns the cache to use.
	 *
	 * @return CacheInterface
	 */
	public function getCache() {
		if (TYPO3_MODE !== 'FE' || is_object($GLOBALS['BE_USER']) || $this->configuration->get('cache/disable')) {
			$cache = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Cache\\NullCache');
		} else {
			$cache = CacheFactory::getCache();
		}
		return $cache;
	}

	/**
	 * Obtains the current host.
	 *
	 * @return string
	 */
	public function getCurrentHost() {
		static $cachedHost = null;

		if ($cachedHost === null) {
			$currentHost = (string)GeneralUtility::getIndpEnv('HTTP_HOST');

			// Call user hooks
			if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['getHost'])) {
				foreach($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['getHost'] as $userFunc) {
					$hookParams = array(
						'host' => $currentHost,
					);
					$newHost = GeneralUtility::callUserFunction($userFunc, $hookParams, $this);
					if (!empty($newHost) && is_string($newHost)) {
						$currentHost = $newHost;
					}
				}
			}
			$cachedHost = $currentHost;
		}

		return $cachedHost;
	}
}
