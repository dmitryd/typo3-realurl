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

use DmitryDulepov\Realurl\Cache\CacheInterface;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

/**
 * This class contains common methods for RealURL encoder and decoder.
 *
 * @package DmitryDulepov\Realurl
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
abstract class EncodeDecoderBase {

	/** @var CacheInterface */
	protected $cache;

	/** @var \DmitryDulepov\Realurl\Configuration\ConfigurationReader */
	protected $configuration;

	/** @var \TYPO3\CMS\Core\Database\DatabaseConnection */
	protected $databaseConnection;

	/** @var string */
	protected $emptySegmentValue;

	/** @var array */
	protected $ignoredUrlParameters = array();

	/** @var \TYPO3\CMS\Core\Log\Logger */
	protected $logger;

	/** @var PageRepository */
	protected $pageRepository = NULL;

	/** @var array */
	static public $pageTitleFields = array('tx_realurl_pathsegment', 'alias', 'nav_title', 'title', 'uid');

	/** @var int */
	protected $rootPageId;

	/**
	 * Undocumented, unsupported & deprecated.
	 *
	 * @var string
	 */
	protected $separatorCharacter;

	/** @var TypoScriptFrontendController */
	protected $tsfe;

	/** @var \DmitryDulepov\Realurl\Utility */
	protected $utility;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		Utility::checkAndPerformRequiredUpdates();
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
		$this->logger = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Log\\LogManager')->getLogger(get_class($this));
		$this->tsfe = $GLOBALS['TSFE'];
		// Warning! It is important to init the new object and not reuse any existing object
		// $this->pageRepository->sys_language_uid must stay 0 because we will do overlays manually
		$this->pageRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		$this->pageRepository->init(FALSE);
		self::overwritePageTitleFieldsFromConfiguration();
	}

	/**
	 * Creates a query string (without preceding question mark) from
	 * parameters.
	 *
	 * @param array $parameters
	 * @return mixed
	 */
	protected function createQueryStringFromParameters(array $parameters) {
		return substr(GeneralUtility::implodeArrayForUrl('', $parameters, '', false, true), 1);
	}

	/**
	 * Checks conditions for xxxVar.
	 *
	 * @param array $conditionConfiguration
	 * @param string $previousValue
	 * @return bool
	 */
	protected function checkLegacyCondition(array $conditionConfiguration, $previousValue) {
		$result = true;

		// Check previous value
		if (isset($conditionConfiguration['prevValueInList'])) {
			if (!GeneralUtility::inList($conditionConfiguration['prevValueInList'], $previousValue))
			$result = false;
		}

		return $result;
	}

	/**
	 * Sets configuration blocks for fixedPostVars and postVarSets according
	 * to priority: current page id first, _DEFAULT last. Also resolves aliases
	 * for configuration.
	 *
	 * @param array $configuration
	 * @param int $pageId
	 * @return array
	 */
	protected function getConfigurationForPostVars(array $configuration, $pageId) {
		$configurationBlock = NULL;
		if (isset($configuration[$pageId])) {
			$maxTries = 10;
			while ($maxTries-- && isset($configuration[$pageId]) && !is_array($configuration[$pageId])) {
				$pageId = $configuration[$pageId];
			}
			if (is_array($configuration[$pageId])) {
				$configurationBlock = $configuration[$pageId];
			}
		}
		if (is_null($configurationBlock) && isset($configuration['_DEFAULT'])) {
			$configurationBlock = $configuration['_DEFAULT'];
		}
		if (!is_array($configurationBlock)) {
			$configurationBlock = array();
		}

		return $configurationBlock;
	}

	/**
	 * Initializes confguration reader.
	 *
	 * @return void
	 */
	abstract protected function initializeConfiguration();

	/**
	 * Looks up an ID value (integer) in lookup-table based on input alias value.
	 * (The lookup table for id<->alias is meant to contain UNIQUE alias strings for id integers)
	 * In the lookup table 'tx_realurl_uniqalias' the field "value_alias" should be unique (per combination of field_alias+field_id+tablename)! However the "value_id" field doesn't have to; that is a feature which allows more aliases to point to the same id. The alias selected for converting id to alias will be the first inserted at the moment. This might be more intelligent in the future, having an order column which can be controlled from the backend for instance!
	 *
	 * @param array $configuration
	 * @param string $aliasValue
	 * @return int ID integer. If none is found: false
	 */
	protected function getFromAliasCacheByAliasValue(array $configuration, $aliasValue) {
		$row = $this->databaseConnection->exec_SELECTgetSingleRow('value_id', 'tx_realurl_uniqalias',
				'value_alias=' . $this->databaseConnection->fullQuoteStr($aliasValue, 'tx_realurl_uniqalias') .
				' AND field_alias=' . $this->databaseConnection->fullQuoteStr($configuration['alias_field'], 'tx_realurl_uniqalias') .
				' AND field_id=' . $this->databaseConnection->fullQuoteStr($configuration['id_field'], 'tx_realurl_uniqalias') .
				' AND tablename=' . $this->databaseConnection->fullQuoteStr($configuration['table'], 'tx_realurl_uniqalias') .
				' AND (expire=0 OR expire>' . time() . ')');

		return (is_array($row) ? (int)$row['value_id'] : false);
	}

	/**
	 * Initializes the instance.
	 *
	 * @throws \Exception
	 */
	protected function initialize() {
		$this->initializeConfiguration();
		$this->emptySegmentValue = $this->configuration->get('init/emptySegmentValue');
		$this->rootPageId = (int)$this->configuration->get('pagePath/rootpage_id');
		$this->utility = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Utility', $this->configuration);
		$this->cache = $this->utility->getCache();
		$this->separatorCharacter = $this->configuration->get('pagePath/spaceCharacter');
	}

	/**
	 * Checks if system runs in non-live workspace
	 *
	 * @return boolean
	 */
	protected function isInWorkspace() {
		$result = false;
		if ($this->tsfe->beUserLogin) {
			$result = ($GLOBALS['BE_USER']->workspace !== 0);
		}
		return $result;
	}

	/**
	 * Overwrites page title fields from extension configuration. This function
	 * is used from the constructor and also from DataHandler hook, thus made
	 * public.
	 *
	 * @return void
	 */
	public static function overwritePageTitleFieldsFromConfiguration() {
		$configuration = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
		if (!empty($configuration['segTitleFieldList'])) {
			$segTitleFieldList = GeneralUtility::trimExplode(',', $configuration['segTitleFieldList']);
			if (count($segTitleFieldList) > 0) {
				self::$pageTitleFields = $segTitleFieldList;
			}
		}
	}

	/**
	 * Removes ignored parameters from the query string.
	 *
	 * @param string $queryString
	 * @return string
	 */
	protected function removeIgnoredParametersFromQueryString($queryString) {
		if ($queryString) {
			$ignoredParametersRegExp = $this->configuration->get('cache/ignoredGetParametersRegExp');
			if ($ignoredParametersRegExp) {
				$collectedParameters = array();
				foreach (explode('&', trim($queryString, '&')) as $parameterPair) {
					list($parameterName, $parameterValue) = explode('=', $parameterPair, 2);
					if ($parameterName !== '') {
						$parameterName = urldecode($parameterName);
						if (preg_match($ignoredParametersRegExp, $parameterName)) {
							$this->ignoredUrlParameters[$parameterName] = urldecode($parameterValue);
						}
						else {
							$collectedParameters[$parameterName] = urldecode($parameterValue);
						}
					}
				}
				$queryString = $this->createQueryStringFromParameters($collectedParameters);
			}
		} else {
			$queryString = '';
		}

		return $queryString;
	}

	/**
	 * Removes ignored parameters from the URL. Removed parameters are stored in
	 * $this->ignoredUrlParameters and can be restored using restoreIgnoredUrlParameters.
	 *
	 * @param string $url
	 * @return string
	 */
	protected function removeIgnoredParametersFromURL($url) {
		list($path, $queryString) = explode('?', $url, 2);
		$queryString = $this->removeIgnoredParametersFromQueryString((string)$queryString);

		$url = $path;
		if (!empty($queryString)) {
			$url .= '?';
		}
		$url .= $queryString;

		return $url;
	}

	/**
	 * Restores ignored URL parameters.
	 *
	 * @param array $urlParameters
	 */
	protected function restoreIgnoredUrlParameters(array &$urlParameters) {
		if (count($this->ignoredUrlParameters) > 0) {
			ArrayUtility::mergeRecursiveWithOverrule($urlParameters, $this->ignoredUrlParameters);
			$this->sortArrayDeep($urlParameters);
		}
	}

	/**
	 * Restores ignored URL parameters.
	 *
	 * @param string $url
	 * @return string
	 */
	protected function restoreIgnoredUrlParametersInURL($url) {
		if (count($this->ignoredUrlParameters) > 0) {
			list($path, $queryString) = explode('?', $url);
			$appendedPart = $this->createQueryStringFromParameters($this->ignoredUrlParameters);
			if (!empty($queryString)) {
				$queryString .= '&';
			}
			$queryString .= $appendedPart;

			$url = $path . '?' . $queryString;
		}

		return $url;
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
		foreach ($pathParts as $key => $part) {
			if (is_array($part)) {
				$this->sortArrayDeep($pathParts[$key]);
			}
		}
	}
}
