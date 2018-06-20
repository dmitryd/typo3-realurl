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
namespace DmitryDulepov\Realurl\Configuration;

use \TYPO3\CMS\Backend\Utility\BackendUtility;
use \TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * This class provides configuration loading and access for the rest of the
 * extension.
 *
 * @package DmitryDulepov\Realurl\Configuration
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class ConfigurationReader {

	const MODE_ENCODE = 0;
	const MODE_DECODE = 1;

	/** @var string */
	protected $alternativeHostName;

	/** @var array */
	protected $configuration = array();

	/** @var array|null */
	protected $domainConfiguration = null;

	/** @var \Exception */
	protected $exception = null;

	/** @var array */
	protected $extConfiguration = array();

	/** @var array */
	protected $getVarsToSet = array();

	/** @var string */
	protected $hostName;

	/** @var int */
	protected $mode;

	/** @var \TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController */
	protected $tsfe;

	/** @var array */
	protected $urlParameters;

	/** @var \DmitryDulepov\Realurl\Utility */
	protected $utility;

	/**
	 * Default values for some configuration options.
	 *
	 * @var array
	 */
	protected $defaultValues = array(
		'cache/banUrlsRegExp' => '/tx_solr|tx_indexedsearch|tx_kesearch|(?:^|\?|&)q=/',
		'cache/ignoredGetParametersRegExp' => '/^(?:gclid|utm_(?:source|medium|campaign|term|content)|pk_campaign|pk_kwd|TSFE_ADMIN_PANEL.*)$/',
		'fileName/acceptHTMLsuffix' => TRUE,
		'fileName/defaultToHTMLsuffixOnPrev' => FALSE,
		'init/appendMissingSlash' => 'ifNotFile,redirect[301]',
		'init/defaultLanguageUid' => 0,
		'init/emptySegmentValue' => '',
		'pagePath/spaceCharacter' => '-', // undocumented & deprecated!
	);

	/**
	 * Initializes the class.
	 *
	 * @param int $mode One of MODE_* constants
	 * @param array $urlParameters
	 */
	public function __construct($mode, array $urlParameters = array()) {
		$this->mode = $mode;
		$this->tsfe = $GLOBALS['TSFE'];
		$this->urlParameters = $urlParameters;
		$this->utility = GeneralUtility::makeInstance('DmitryDulepov\\Realurl\\Utility', $this);

		try {
			$this->loadExtConfiguration();
			$this->performAutomaticConfiguration();
			$this->setHostnames();
			$this->setConfigurationForTheCurrentDomain();
			$this->postProcessConfiguration();
		}
		catch (\Exception $exception) {
			$this->exception = $exception;
		}
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
	 * Returns _GET vars to set.
	 *
	 * @return array
	 */
	public function getGetVarsToSet() {
		return $this->getVarsToSet;
	}

	/**
	 * Returns the current mode.
	 *
	 * @return int
	 */
	public function getMode() {
		return $this->mode;
	}

	/**
	 * If the configuration is invalid throws an exception stored earlier. This
	 * makes sense only we are have a speaking url.
	 *
	 * This must be called once prior to using get() call.
	 *
	 * @throws \Exception
	 */
	public function validate() {
		if ($this->exception !== null) {
			throw $this->exception;
		}
	}

	/**
	 * Checks if RealURL configuration exists.
	 *
	 * @return bool
	 */
	protected function doesConfigurationExist() {
		$result = false;

		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'])) {
			$hookList = array(
				'ConfigurationReader_postProc',
				'decodeSpURL_preProc',
				'encodeSpURL_earlyHook',
				'encodeSpURL_postProc',
				'getHost',
				'storeInUrlCache',
			);
			$configurationCopy = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
			foreach ($hookList as $hook) {
				unset($configurationCopy[$hook]);
			}

			$result = count($configurationCopy) > 0;
		}

		return $result;
	}

	/**
	 * Obtains the configuration key to use.
	 *
	 * @return string
	 */
	protected function getConfigurationKey() {
		$configurationKey = '_DEFAULT';

		$globalConfig = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		if (is_array($globalConfig)) {
			if (isset($globalConfig[$this->hostName])) {
				$configurationKey = $this->hostName;
			} elseif (isset($globalConfig[$this->alternativeHostName])) {
				$configurationKey = $this->alternativeHostName;
			}

			// Adjust if necessary
			if (isset($globalConfig['_DOMAINS'])) {
				if ($this->mode == self::MODE_DECODE && isset($globalConfig['_DOMAINS']['decode'])) {
					$configurationKey = $this->getConfigurationKeyByDomainDecode($configurationKey);
					// Encoding is handled after rootpage_id is determined.
				}
			}
		}

		return $this->resolveConfigurationKey($configurationKey);
	}

	/**
	 * Adjusts configration key for decoding.
	 *
	 * @param string $configurationKey
	 * @return string
	 */
	protected function getConfigurationKeyByDomainDecode($configurationKey) {
		$globalConfig = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		foreach ($globalConfig['_DOMAINS']['decode'] as $domainName => $configuration) {
			$checkThisConfiguration = false;
			if ($domainName{0} == '/') {
				// Regular expression, match only main host name
				if (@preg_match($domainName, $this->hostName)) {
					$checkThisConfiguration = true;
				}
			}
			elseif ($domainName === $this->hostName || $domainName === $this->alternativeHostName) {
				$checkThisConfiguration = true;
			}
			if ($checkThisConfiguration) {
				if (isset($configuration['useConfiguration']) && isset($globalConfig[$configuration['useConfiguration']])) {
					$configurationKey = $configuration['useConfiguration'];
					$this->domainConfiguration = $configuration;
				}
				if (is_array($configuration['GETvars'])) {
					foreach ($configuration['GETvars'] as $getVar => $getVarValue) {
						$this->getVarsToSet[$getVar] = $getVarValue;
					}
				}
				break;
			}
		}

		return $configurationKey;
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
		if ($this->extConfiguration['enableAutoConf'] && !$this->doesConfigurationExist()) {
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
	protected function setConfigurationForTheCurrentDomain() {
		$globalConfig = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		if (is_array($globalConfig)) {
			$configurationKey = $this->getConfigurationKey();
			$configuration = $globalConfig[$configurationKey];
			if (is_array($configuration)) {
				$this->configuration = $configuration;
			}

			$this->setRootPageId();

			if ($this->mode == self::MODE_ENCODE) {
				// Decode is handled when detecting configuration key
				$this->updateConfigurationForEncoding($configurationKey);
			}

			if (is_array($this->domainConfiguration)) {
				$this->configuration['domains'] = $this->domainConfiguration;
			}
			unset($this->domainConfiguration);
		}
	}

	/**
	 * Updates _DOMAINS configuration to include only relevant entries and remove
	 * rootpage_id option.
	 *
	 * @param string $configurationKey
	 * @return void
	 */
	protected function updateConfigurationForEncoding(&$configurationKey) {
		if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DOMAINS']['encode'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['_DOMAINS']['encode'] as $encodeConfiguration) {
				if (isset($encodeConfiguration['rootpage_id']) && (int)$encodeConfiguration['rootpage_id'] !== (int)$this->configuration['pagePath']['rootpage_id']) {
					// Not applicable to this root page
					continue;
				}
				if (isset($encodeConfiguration['ifDifferentToCurrent']) && $encodeConfiguration['ifDifferentToCurrent'] && GeneralUtility::_GET($encodeConfiguration['GETvar']) == $encodeConfiguration['value']) {
					// Same as current but prohibited by 'ifDifferentToCurrent'
					continue;
				}
				$getVarName = $encodeConfiguration['GETvar'];
				if (!isset($this->urlParameters[$getVarName]) || $this->urlParameters[$getVarName] != $encodeConfiguration['value']) {
					// Not that GET variable value
					continue;
				}
				if (isset($encodeConfiguration['useConfiguration']) && $encodeConfiguration['useConfiguration'] !== $configurationKey) {
					// Use different config
					$configurationKey = $this->resolveConfigurationKey($encodeConfiguration['useConfiguration']);
					$this->configuration = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'][$configurationKey];
				}
				$this->domainConfiguration = $encodeConfiguration;
				break;
			}
		}
	}

	/**
	 * Sets host name variables.
	 *
	 * @return void
	 */
	protected function setHostnames() {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'])) {
			if ($this->mode == self::MODE_DECODE) {
				$this->setHostnamesForDecoding();
			} else {
				$this->setHostnamesForEncoding();
			}
			if (substr($this->hostName, 0, 4) === 'www.') {
				$this->alternativeHostName = substr($this->hostName, 4);
			} elseif (substr_count($this->hostName, '.') === 1) {
				$this->alternativeHostName = 'www.' . $this->hostName;
			}
		}
	}

	/**
	 * Sets host name variables for decoding.
	 *
	 * @return void
	 */
	protected function setHostnamesForDecoding() {
		$this->alternativeHostName = $this->hostName = $this->utility->getCurrentHost();
	}

	/**
	 * Sets host name variables for encoding.
	 *
	 * @return void
	 */
	protected function setHostnamesForEncoding() {
		if ($GLOBALS['TSFE']->config['config']['typolinkEnableLinksAcrossDomains']) {
			// We have to find proper domain for the id.
			$pageRepository = $GLOBALS['TSFE']->sys_page;
			/** @var \TYPO3\CMS\Frontend\Page\PageRepository $pageRepository */
			$id = $this->urlParameters['id'];
			if (!MathUtility::canBeInterpretedAsInteger($id)) {
				$id = $pageRepository->getPageIdFromAlias($this->urlParameters['id']);
			}
			$MP = isset($this->urlParameters['MP']) ? $this->urlParameters['MP'] : '';
			$rootline = $pageRepository->getRootLine($id, $MP);
			$globalConfig = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
			foreach ($rootline as $page) {
				foreach ($globalConfig as $domainName => $configuration) {
					if ($domainName !== '_DOMAINS' && is_array($configuration) && isset($configuration['pagePath']) && is_array($configuration['pagePath']) && isset($configuration['pagePath']['rootpage_id'])) {
						if ((int)$configuration['pagePath']['rootpage_id'] === (int)$page['uid']) {
							$this->hostName = $domainName;
							break 2;
						}
					}
				}
			}
			if (empty($this->hostName) && !$MP) {
				$this->hostName = $this->tsfe->getDomainNameForPid($id);
			}
		}
		if (empty($this->hostName)) {
			$this->alternativeHostName = $this->hostName = $this->utility->getCurrentHost();
		}
	}

	/**
	 * Sets the root page id from the current host if that is not set already.
	 *
	 * @return void
	 * @throws \Exception
	 */
	protected function setRootPageId() {
		if (!isset($this->configuration['pagePath']['rootpage_id'])) {
			$this->setRootPageIdFromDomainRecord() || $this->setRootPageIdFromRootFlag() || $this->setRootPageIdFromTopLevelPages();
		}
		if ((int)$this->configuration['pagePath']['rootpage_id'] === 0) {
			throw new \Exception('RealURL was not able to find the root page id for the domain "' . $this->utility->getCurrentHost() . '"', 1453732574);
		}
	}

	/**
	 * Sets the root page id from domain records.
	 *
	 * @return bool
	 */
	protected function setRootPageIdFromDomainRecord() {
		$result = FALSE;

		$domainRecord = BackendUtility::getDomainStartPage($this->hostName);
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
			throw new \Exception('RealURL was not able to find the root page id for the domain "' . $this->utility->getCurrentHost() . '" as there was more than one root page with this domain.', 1420480928);
		} elseif (count($rows) !== 0) {
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

	/**
	 * Runs post-processing hooks for extensions.
	 *
	 * @return void
	 */
	protected function postProcessConfiguration() {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['ConfigurationReader_postProc'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl']['ConfigurationReader_postProc'] as $userFunc) {
				$parameters = array(
					'configuration' => &$this->configuration,
					'domainConfiguration' => &$this->domainConfiguration,
					'exception' => &$this->exception,
					'extConfiguration' => &$this->extConfiguration,
					'hostName' => &$this->hostName,
					'alternativeHostName' => &$this->alternativeHostName,
					'urlParameters' => &$this->urlParameters,
					'getVarsToSet' => &$this->getVarsToSet,
					'utility' => $this->utility,
					'pObj' => $this,
				);
				GeneralUtility::callUserFunction($userFunc, $parameters, $this);
			}
		}
	}
}
