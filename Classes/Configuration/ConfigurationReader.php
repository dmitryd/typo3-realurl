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
namespace DmitryDulepov\Realurl\Configuration;

use \DmitryDulepov\Realurl\Utility;
use \TYPO3\CMS\Backend\Utility\BackendUtility;
use \TYPO3\CMS\Core\SingletonInterface;
use \TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class provides configuration loading and access for the rest of the
 * extension.
 *
 * @package DmitryDulepov\Realurl\Configuration
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class ConfigurationReader implements SingletonInterface {

	/** @var array */
	protected $configuration = array();

	/** @var array */
	protected $extConfiguration = array();

	/** @var \DmitryDulepov\Realurl\Utility */
	protected $utility;

	/**
	 * Default values for some configuration options.
	 *
	 * @var array
	 */
	protected $defaultValues = array(
		'cache/banUrlsRegExp' => '/tx_solr|tx_indexed_search|(?:^|\?|&)q=/',
		'cache/disable' => FALSE,
		'fileName/acceptHTMLsuffix' => TRUE,
		'fileName/defaultToHTMLsuffixOnPrev' => FALSE,
		'init/appendMissingSlash' => 'ifNotFile,redirect[301]',
	);

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->utility = Utility::getInstance();

		$this->loadExtConfiguration();
		$this->performAutomaticConfiguration();
		$this->setConfigurationForTheCurentDomain();
	}

	/**
	 * Obtains the configuration by its path. Paths are separated by '/'.
	 * Leading and trailing slashes are removed.
	 * Special use: 'extconf/xxx' gets the entry from the ext_conf_template.txt.
	 *
	 * @param string $path
	 * @return mixed
	 */
	public function get($path) {
		if (substr($path, 0, 8) == 'extconf/') {
			$value = $this->extConfiguration[substr($path, 8)];
		} else {
			$value = $this->getFromConfiguration($path);
		}

		return $value;
	}

	/**
	 * Obtains the configuration key to use.
	 *
	 * @return string
	 */
	protected function getConfigurationKey() {
		$result = '_DEFAULT';

		$globalConfig = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		if (is_array($globalConfig)) {
			$configuration = NULL;
			$hostName = $this->utility->getCurrentHost();
			if (isset($globalConfig[$hostName])) {
				$result = $hostName;
			} elseif (substr($hostName, 0, 4) === 'www.') {
				$alternativeHostName = substr($hostName, 4);
				if (isset($globalConfig[$alternativeHostName])) {
					$result = $alternativeHostName;
				}
			}
		}

		return $this->resolveConfigurationKey($result);
	}

	/**
	 * Obtains the default value for the option.
	 *
	 * @param string $path
	 * @return mixed
	 */
	protected function getDefaultValue($path) {
		return isset($this->defaultValues[$path]) ? $this->defaultValues[$path] : '';
	}

	/**
	 * Creates the instance of this class.
	 *
	 * @return ConfigurationReader
	 */
	static public function getInstance() {
		return GeneralUtility::makeInstance(__CLASS__);
	}

	/**
	 * Obtains the value by path from the configuration.
	 *
	 * @param string $path
	 * @return mixed
	 * @see get()
	 */
	protected function getFromConfiguration($path) {
		$path = trim($path, '/');
		$value = $this->getDefaultValue($path);
		$configuration = $this->configuration;
		$segments = explode('/', $path);
		$segmentCount = count($segments);
		for ($currentSegmentNumber = 0; $currentSegmentNumber < $segmentCount; $currentSegmentNumber++) {
			$key = $segments[$currentSegmentNumber];
			if ($currentSegmentNumber < $segmentCount - 1) {
				// Must be an array
				if (isset($configuration[$key]) && is_array($configuration[$key])) {
					$configuration = $configuration[$key];
				} else {
					break;
				}
			} elseif (isset($configuration[$key])) {
				$value = $configuration[$key];
			}
		}

		return $value;
	}

	/**
	 * Loads extension configuration.
	 *
	 * @return void
	 */
	protected function loadExtConfiguration() {
		$configuration = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);
		if (is_array($configuration)) {
			$this->extConfiguration = $configuration;
		}
	}

	/**
	 * Performs automatic configuration if necessary.
	 *
	 * @return void
	 */
	protected function performAutomaticConfiguration() {
		if (!isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']) && $this->extConfiguration['enableAutoConf']) {
			$autoconfigurator = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Configuration\\AutomaticConfigurator');
			/** @var \DmitryDulepov\Realurl\Configuration\AutomaticConfigurator $autoconfigurator */
			$autoconfigurator->configure();

			/** @noinspection PhpIncludeInspection */
			require_once(PATH_site . TX_REALURL_AUTOCONF_FILE);
		}
	}

	/**
	 * Resolves configuration aliases. For example:
	 * 'domain1' => 'domain2',
	 * 'domain2' => 'domain3',
	 * 'domain3' => array(....)
	 * will resolve 'domain1' and 'domain2' to 'domain3'.
	 *
	 * @param string $keyAlias
	 * @return string
	 */
	protected function resolveConfigurationKey($keyAlias) {
		$globalConfig = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		$maxLoops = 30;
		do {
			$lastKey = $keyAlias;
			$keyAlias = $globalConfig[$keyAlias];
		} while ($maxLoops-- && is_string($keyAlias));

		return is_array($keyAlias) ? $lastKey : '_DEFAULT';
	}

	/**
	 * Sets the configuration from the current domain.
	 *
	 * @return void
	 */
	protected function setConfigurationForTheCurentDomain() {
		$globalConfig = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		if (is_array($globalConfig)) {
			$configurationKey = $this->getConfigurationKey();
			$configuration = $globalConfig[$configurationKey];
			if (is_array($configuration)) {
				$this->configuration = $configuration;
			}

			$this->setRootPageId();

			if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DOMAINS'])) {
				$this->configuration['domains'] = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DOMAINS'];
				$this->mergeDomainsConfiguration($configurationKey);
			}
		}
	}

	/**
	 * Updates _DOMAINS configuration to include only relevant entries and remove
	 * rootpage_id option.
	 *
	 * @param string $configurationKey
	 * @return void
	 */
	protected function mergeDomainsConfiguration($configurationKey) {
		$newEncodeConfiguration = array();
		foreach ($this->configuration['domains']['encode'] as $key => $value) {
			if (isset($value['useConfiguration']) && $this->resolveConfigurationKey($value['useConfiguration']) !== $configurationKey) {
				// Not applicable to this configuration
				continue;
			}
			if (isset($value['rootpage_id']) && (int)$value['rootpage_id'] !== (int)$this->configuration['pagePath']['rootpage_id']) {
				// Not applicable for this root page
				continue;
			}
			$newEncodeConfiguration[$key] = $value;
		}
		$this->configuration['domains']['encode'] = $newEncodeConfiguration;
	}

	/**
	 * Sets the root page id from the current host if that is not set already.
	 *
	 * @return void
	 */
	protected function setRootPageId() {
		if (!isset($this->configuration['pagePath']['rootpage_id'])) {
			$this->setRootPageIdFromDomainRecord() || $this->setRootPageIdFromRootFlag() || $this->setRootPageIdFromTopLevelPages();
		}
	}

	/**
	 * Sets the root page id from domain records.
	 *
	 * @return bool
	 */
	protected function setRootPageIdFromDomainRecord() {
		$result = FALSE;

		// TODO Consider using PageRepository::getDomainStartPage()
		$domainRecord = BackendUtility::getDomainStartPage($this->utility->getCurrentHost());
		if (is_array($domainRecord)) {
			$this->configuration['pagePath']['rootpage_id'] = (int)$domainRecord['pid'];
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Sets the root page id from pages with the root flag.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function setRootPageIdFromRootFlag() {
		$result = FALSE;

		/** @noinspection PhpUndefinedMethodInspection */
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid', 'pages', 'is_siteroot=1 AND deleted=0 AND hidden=0');
		if (count($rows) > 1) {
			// Cannot be done: too many of them!
			throw new \Exception('RealURL was not able to find the root page id for the domain "' . $this->utility->getCurrentHost() . '"', 1420480928);
		}
		elseif (count($rows) !== 0) {
			$this->configuration['pagePath']['rootpage_id'] = (int)$rows[0]['uid'];
			$result = TRUE;
		}

		return $result;
	}

	/**
	 * Sets the root page id from the top level pages.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function setRootPageIdFromTopLevelPages() {
		/** @noinspection PhpUndefinedMethodInspection */
		$rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows('uid', 'pages',
			'pid=0 AND doktype IN (1,2,4) AND deleted=0 AND hidden=0');
		if (count($rows) !== 1) {
			// Cannot be done: too many of them!
			throw new \Exception('RealURL was not able to find the root page id for the domain "' . $this->utility->getCurrentHost() . '"', 1420480982);
		}
		$this->configuration['pagePath']['rootpage_id'] = (int)$rows[0]['uid'];

		return TRUE;
	}
}
