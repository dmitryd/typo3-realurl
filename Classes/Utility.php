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
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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
	 * Checks if required update should run and runs it if necessary.
	 *
	 * @return void
	 */
	static public function checkAndPerformRequiredUpdates() {
		$currentUpdateLevel = 3;

		$registry = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Registry');
		/** @var \TYPO3\CMS\Core\Registry $registry */
		$updateLevel = (int)$registry->get('tx_realurl', 'updateLevel', 0);
		if ($updateLevel < $currentUpdateLevel) {
			require_once(ExtensionManagementUtility::extPath('realurl', 'class.ext_update.php'));
			$updater = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\ext_update');
			/** @var \DmitryDulepov\Realurl\ext_update $updater */
			if ($updater->access()) {
				$updater->main();
			}
			$registry->set('tx_realurl', 'updateLevel', $currentUpdateLevel);
		}
	}

	/**
	 * Converts a given string to a string that can be used as a URL segment.
	 * The result is not url-encoded.
	 *
	 * @param string $processedTitle
	 * @param string $spaceCharacter
	 * @param bool $strToLower
	 * @return string
	 */
	public function convertToSafeString($processedTitle, $spaceCharacter = '-', $strToLower = true) {
		if ($strToLower) {
			$processedTitle = $this->csConvertor->conv_case('utf-8', $processedTitle, 'toLower');
		}
		$processedTitle = strip_tags($processedTitle);
		$processedTitle = preg_replace('/[ \t\x{00A0}\-+_]+/u', $spaceCharacter, $processedTitle);
		$processedTitle = $this->csConvertor->specCharsToASCII('utf-8', $processedTitle);
		$processedTitle = preg_replace('/[^\p{L}0-9' . preg_quote($spaceCharacter) . ']/u', '', $processedTitle);
		$processedTitle = preg_replace('/' . preg_quote($spaceCharacter) . '{2,}/', $spaceCharacter, $processedTitle);
		$processedTitle = trim($processedTitle, $spaceCharacter);

		// TODO Post-processing hook here

		if ($strToLower) {
			$processedTitle = strtolower($processedTitle);
		}

		return $processedTitle;
	}

	/**
	 * Generates stack trace.
	 *
	 * @return string
	 */
	public function generateStackTrace() {
		$trace = debug_backtrace();
		array_shift($trace);
		$traceCount = count($trace);
		$tracePointer = 0;
		$lines = array();
		foreach ($trace as $traceEntry) {
			$codeLine = '';
			if (isset($traceEntry['class']) && $traceEntry['class']) {
				$codeLine .= $traceEntry['class'];
				$codeLine .= (isset($traceEntry['type']) && $traceEntry['type']) ? $traceEntry['type'] : '::';
			}
			if (isset($traceEntry['function']) && $traceEntry['function']) {
				$codeLine .= $traceEntry['function'];
				$codeLine .= isset($traceEntry['args']) && is_array($traceEntry['args']) ? $this->dumpFunctionArguments($traceEntry['args']) : '()';
				$codeLine .= ' ';
			}
			$codeLine .= 'at ';
			$codeLine .= ((isset($traceEntry['file']) && $traceEntry['file']) ? $traceEntry['file'] : '(unknown)');
			$codeLine .= ':';
			$codeLine .= ((isset($traceEntry['line']) && $traceEntry['line']) ? $traceEntry['line'] : '(?)');

			$lines[] = sprintf('  %3d: %s', $traceCount - $tracePointer, $codeLine);
			$tracePointer++;
		}
		// Free memory
		unset($trace);

		return implode(LF, $lines) . LF . LF;
	}

	/**
	 * Returns the cache to use.
	 *
	 * @return CacheInterface
	 */
	public function getCache() {
		$cache = CacheFactory::getCache();

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

	/**
	 * Dumps function arguments in a log-friendly way.
	 *
	 * @param array $arguments
	 * @return string
	 */
	protected function dumpFunctionArguments(array $arguments) {
		$dumpedArguments = array();
		foreach ($arguments as $argument) {
			if (is_numeric($argument)) {
				$dumpedArguments[] = $argument;
			} elseif (is_string($argument)) {
				if (strlen($argument) > 80) {
					$argument = substr($argument, 0, 30) . '...';
				}
				$argument = addslashes($argument);
				$argument = preg_replace('/\r/', '\r', $argument);
				$argument = preg_replace('/\n/', '\n', $argument);
				$dumpedArguments[] = '\'' . $argument . '\'';
			}
			elseif (is_null($argument)) {
				$dumpedArguments[] = 'null';
			}
			elseif (is_object($argument)) {
				$dumpedArguments[] = get_class($argument);
			}
			elseif (is_array($argument)) {
				$dumpedArguments[] = 'array(' . (count($arguments) ? '...' : '') . ')';
			}
			else {
				$dumpedArguments[] = gettype($argument);
			}
		}

		return '(' . implode(', ', $dumpedArguments) . ')';
	}
}
