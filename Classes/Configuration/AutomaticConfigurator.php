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

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AutomaticConfigurator {

	/** @var \TYPO3\CMS\Core\Database\DatabaseConnection */
	protected $databaseConnection;

	/** @var bool */
	protected $hasStaticInfoTables;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
		$this->hasStaticInfoTables = ExtensionManagementUtility::isLoaded('static_info_tables');
	}

	/**
	 * Configures RealURL.
	 *
	 * @return void
	 */
	public function configure() {
		$lockId = 'realurl_autoconfiguration';
		if (version_compare(TYPO3_branch, '7.2', '<')) {
			$lock = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Locking\\Locker', $lockId);
			/** @var \TYPO3\CMS\Core\Locking\Locker $lock */
			$lock->setEnableLogging(FALSE);
			$lock->acquireExclusiveLock();
		} else {
			$lockFactory = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Locking\\LockFactory');
			/** @var \TYPO3\CMS\Core\Locking\LockFactory $lockFactory */
			$lock = $lockFactory->createLocker($lockId);
			$lock->acquire();
		}

		if (!file_exists(PATH_site . TX_REALURL_AUTOCONF_FILE)) {
			$this->runConfigurator();
		}
		$lock->release();
	}

	/**
	 * Adds languages to configuration
	 *
	 * @param array $configuration
	 * @return void
	 */
	protected function addLanguages(array &$configuration) {
		if (version_compare(TYPO3_branch, '7.6', '>=')) {
			$languages = $this->databaseConnection->exec_SELECTgetRows('t1.uid AS uid,t1.language_isocode AS lg_iso_2', 'sys_language t1', 't1.hidden=0 AND t1.language_isocode<>\'\'');
		}
		elseif ($this->hasStaticInfoTables) {
			$languages = $this->databaseConnection->exec_SELECTgetRows('t1.uid AS uid,t2.lg_iso_2 AS lg_iso_2', 'sys_language t1, static_languages t2', 't2.uid=t1.static_lang_isocode AND t1.hidden=0 AND t2.lg_iso_2<>\'\'');
		}
		else {
			$languages = array();
		}
		if (count($languages) > 0) {
			$configuration['preVars'] = array(
				0 => array(
					'GETvar' => 'L',
					'valueMap' => array(
					),
					'noMatch' => 'bypass'
				),
			);
			foreach ($languages as $lang) {
				$configuration['preVars'][0]['valueMap'][strtolower($lang['lg_iso_2'])] = $lang['uid'];
			}
		}
	}

	/**
	 * Creates configuration for the installation without domain records.
	 *
	 * @param array $template
	 * @return array
	 */
	protected function createConfigurationWithoutDomains(array $template) {
		$configuration = array(
			'_DEFAULT' => $template
		);
		$row = $this->databaseConnection->exec_SELECTgetSingleRow('uid', 'pages',
			'deleted=0 AND hidden=0 AND is_siteroot=1'
		);
		if (is_array($row) > 0) {
			$configuration['_DEFAULT']['pagePath']['rootpage_id'] = $row['uid'];
		}

		return $configuration;
	}

	/**
	 * Creates configuration for the given list of domains.
	 *
	 * @param array $domains
	 * @param array $template
	 * @return array
	 */
	protected function createConfigurationForDomains(array $domains, array $template) {
		$configuration = array();
		foreach ($domains as $domain) {
			if ($domain['redirectTo'] != '') {
				// Redirects to another domain, see if we can make a shortcut
				$parts = parse_url($domain['redirectTo']);
				if (isset($domains[$parts['host']]) && ($domains['path'] == '/' || $domains['path'] == '')) {
					// Make a shortcut
					if ($configuration[$parts['host']] != $domain['domainName']) {
						// Here if there were no redirect from this domain to source domain
						$configuration[$domain['domainName']] = $parts['host'];
					}
					continue;
				}
			}
			// Make entry
			$configuration[$domain['domainName']] = $template;
			$configuration[$domain['domainName']]['pagePath']['rootpage_id'] = $domain['pid'];
		}

		return $configuration;
	}

	/**
	 * Obtains a list of domains.
	 *
	 * @return array
	 */
	private function getDomains() {
		return $this->databaseConnection->exec_SELECTgetRows('pid,domainName,redirectTo', 'sys_domain', 'hidden=0',
			'', '', '', 'domainName'
		);
	}

	/**
	 * Creates common configuration template.
	 *
	 * @return	array		Template
	 */
	protected function getTemplate() {
		$confTemplate = array(
			'init' => array(
				'appendMissingSlash' => 'ifNotFile,redirect',
				'emptyUrlReturnValue' => GeneralUtility::getIndpEnv('TYPO3_SITE_PATH')
			),
			'pagePath' => array(
			),
			'fileName' => array(
				'defaultToHTMLsuffixOnPrev' => 0,
				'acceptHTMLsuffix' => 1,
			)
		);

		// Add print feature if TemplaVoila is not loaded
		$confTemplate['fileName']['index']['print'] = array(
			'keyValues' => array(
				'type' => 98,
			)
		);

		$this->addLanguages($confTemplate);

		// Add from extensions
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/realurl/class.tx_realurl_autoconfgen.php']['extensionConfiguration'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/realurl/class.tx_realurl_autoconfgen.php']['extensionConfiguration'] as $extKey => $userFunc) {
				$previousExceptionHandler = set_error_handler(function(/** @noinspection PhpUnusedParameterInspection */ $errno, $errstr) use ($userFunc) {
					$message = 'Error while calling "' . $userFunc . '" for realurl automatic configuration. Error message: ' . $errstr;
					error_log($message);
				}, E_RECOVERABLE_ERROR);

				$params = array(
					'config' => $confTemplate,
					'extKey' => $extKey
				);
				$var = GeneralUtility::callUserFunction($userFunc, $params, $this);
				if ($var) {
					$confTemplate = $var;
				}

				set_error_handler($previousExceptionHandler, E_RECOVERABLE_ERROR);
			}
		}

		return $confTemplate;
	}

	/**
	 * Runs a postprocessing hook for extensions.
	 *
	 * @param array $configuration
	 * @return void
	 */
	protected function postProcessConfiguration(array &$configuration) {
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/realurl/class.tx_realurl_autoconfgen.php']['postProcessConfiguration'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/realurl/class.tx_realurl_autoconfgen.php']['postProcessConfiguration'] as $userFunc) {
				$previousExceptionHandler = set_error_handler(function(/** @noinspection PhpUnusedParameterInspection */ $errno, $errstr) use ($userFunc) {
					$message = 'Error while calling "' . $userFunc . '" for post processing realurl configuration. Error message: ' . $errstr;
					error_log($message);
				}, E_RECOVERABLE_ERROR);

				$parameters = array(
					'config' => &$configuration,
				);
				GeneralUtility::callUserFunction($userFunc, $parameters, $this);

				set_error_handler($previousExceptionHandler, E_RECOVERABLE_ERROR);
			}
		}
	}

	/**
	 * Does the actual configuration.
	 *
	 * @return void
	 */
	protected function runConfigurator() {
		$template = $this->getTemplate();

		$domains = $this->getDomains();
		if (count($domains) == 0) {
			$configuration = $this->createConfigurationWithoutDomains($template);
		} else {
			$configuration = $this->createConfigurationForDomains($domains, $template);
		}

		$this->postProcessConfiguration($configuration);

		$this->saveConfiguration($configuration);
	}

	/**
	 * Saves the configuration.
	 *
	 * @param array $configuration
	 * @return void
	 */
	protected function saveConfiguration(array $configuration) {
		$extensionConfiguration = @unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['realurl']);

		$fileName = PATH_site . TX_REALURL_AUTOCONF_FILE;
		if ($extensionConfiguration['autoConfFormat'] == 0) {
			file_put_contents($fileName, '<' . '?php' . chr(10) . '$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'realurl\']=' .
				'unserialize(\'' . str_replace('\'', '\\\'', serialize($configuration)) . '\');' . chr(10)
			);
		} else {
			file_put_contents($fileName, '<' . '?php' . chr(10) . '$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'realurl\']=' .
				var_export($configuration, TRUE) . ';' . chr(10)
			);
		}
		GeneralUtility::fixPermissions($fileName);
	}
}
