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
	 * Sets the configuration from the current domain.
	 *
	 * @return void
	 */
	protected function setConfigurationForTheCurentDomain() {
		$globalConfig = &$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		if (is_array($globalConfig)) {
			$configuration = NULL;
			$hostName = $this->utility->getCurrentHost();
			if (isset($globalConfig[$hostName])) {
				$configuration = $globalConfig[$hostName];
			} elseif (substr($hostName, 0, 4) === 'www.') {
				$alternativeHostName = substr($hostName, 4);
				if (isset($globalConfig[$alternativeHostName])) {
					$configuration = $globalConfig[$alternativeHostName];
				}
			} elseif (isset($globalConfig['_DEFAULT'])) {
				$configuration = $globalConfig['_DEFAULT'];
			}

			$maxLoops = 30;
			while ($maxLoops-- && is_string($configuration)) {
				$configuration = $globalConfig[$configuration];
			}

			if (is_array($configuration)) {
				$this->configuration = $configuration;
			}
		}
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
}
