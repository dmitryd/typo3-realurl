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
use DmitryDulepov\Realurl\Configuration\ConfigurationReader;
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

	/** @var array */
	static public $pageOverlayTitleFields = array('tx_realurl_pathsegment', 'nav_title', 'title', 'uid');

	/** @var PageRepository */
	protected $pageRepository = NULL;

	/** @var array */
	static public $pageTitleFields = array('tx_realurl_pathsegment', 'alias', 'nav_title', 'title', 'uid');

	/** @var int */
	protected $rootPageId;

	/** @var TypoScriptFrontendController */
	protected $tsfe;

	/** @var \DmitryDulepov\Realurl\Utility */
	protected $utility;

	/**
	 * Initializes the class.
	 */
	public function __construct() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
		$this->tsfe = $GLOBALS['TSFE'];
		$this->configuration = ConfigurationReader::getInstance();
		$this->rootPageId = (int)$this->configuration->get('pagePath/rootpage_id');
		$this->utility = Utility::getInstance();
		$this->cache = $this->utility->getCache();

		$this->pageRepository = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
		$this->pageRepository->init(FALSE);
	}

	/**
	 * Checks if the URL can be cached. This function may prevent RealURL cache
	 * pollution with Solr or Indexed search URLs.
	 *
	 * @param string $url
	 * @return bool
	 */
	protected function canCacheUrl($url) {
		$bannedUrlsRegExp = $this->configuration->get('cache/banUrlsRegExp');

		return (!$bannedUrlsRegExp || !preg_match($bannedUrlsRegExp, $url));
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
	protected function getConfigirationForPostVars(array $configuration, $pageId) {
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

		return $configurationBlock;
	}

	/**
	 * Looks up an ID value (integer) in lookup-table based on input alias value.
	 * (The lookup table for id<->alias is meant to contain UNIQUE alias strings for id integers)
	 * In the lookup table 'tx_realurl_uniqalias' the field "value_alias" should be unique (per combination of field_alias+field_id+tablename)! However the "value_id" field doesn't have to; that is a feature which allows more aliases to point to the same id. The alias selected for converting id to alias will be the first inserted at the moment. This might be more intelligent in the future, having an order column which can be controlled from the backend for instance!
	 *
	 * @param array $configuration
	 * @param string $aliasValue
	 * @param boolean $onlyNonExpired
	 * @return int ID integer. If none is found: false
	 */
	protected function getFromAliasCacheByAliasValue(array $configuration, $aliasValue, $onlyNonExpired) {
		/** @noinspection PhpUndefinedMethodInspection */
		$row = $this->databaseConnection->exec_SELECTgetSingleRow('value_id', 'tx_realurl_uniqalias',
				'value_alias=' . $this->databaseConnection->fullQuoteStr($aliasValue, 'tx_realurl_uniqalias') .
				' AND field_alias=' . $this->databaseConnection->fullQuoteStr($configuration['alias_field'], 'tx_realurl_uniqalias') .
				' AND field_id=' . $this->databaseConnection->fullQuoteStr($configuration['id_field'], 'tx_realurl_uniqalias') .
				' AND tablename=' . $this->databaseConnection->fullQuoteStr($configuration['table'], 'tx_realurl_uniqalias') .
				' AND ' . ($onlyNonExpired ? 'expire=0' : '(expire=0 OR expire>' . time() . ')'));
		return (is_array($row) ? (int)$row['value_id'] : false);
	}

	/**
	 * Obtains URL with all query parameters sorted.
	 *
	 * @param string $url
	 * @return string
	 */
	protected function getSortedUrl($url) {
		$urlParts = parse_url($url);
		$sortedUrl = $urlParts['path'];
		if ($urlParts['query']) {
			parse_str($url, $pathParts);
			$this->sortArrayDeep($pathParts);
			$sortedUrl .= '?' . ltrim(GeneralUtility::implodeArrayForUrl('', $pathParts), '&');
		}

		return $sortedUrl;
	}

	/**
	 * Resolves a shortcut to its target page if possible. If not, returns the shortcut itself.
	 *
	 * @param array $page
	 * @return array
	 */
	protected function resolveShortcut(array $page) {
		$processedPages = array();
		return $this->resolveShortcutProcess($page, $processedPages);
	}

	/**
	 * Resolvines shortcuts and watches for circular loops.
	 *
	 * @param array $page
	 * @param array $processedPages
	 * @return array
	 */
	private function resolveShortcutProcess(array $page, array &$processedPages) {
		if (!isset($processedPages[$page['uid']])) {
			$processedPages[$page['uid']] = '';
			switch ($page['shortcut_mode']) {
				case PageRepository::SHORTCUT_MODE_NONE:
					$page = $this->resolveShortcutModeNone($page, $processedPages);
					break;
				case PageRepository::SHORTCUT_MODE_FIRST_SUBPAGE:
					$page = $this->resolveShortcutFirstSubpage($page, $processedPages);
					break;
				case PageRepository::SHORTCUT_MODE_PARENT_PAGE:
					$page = $this->resolveShortcutParentPage($page, $processedPages);
					break;
			}
		}

		return $page;
	}

	/**
	 * Resolves shortcuts for the shortcur of type PageRepository::SHORTCUT_MODE_NONE.
	 *
	 * @param array $page
	 * @param array $processedPages
	 * @return array
	 */
	private function resolveShortcutModeNone(array $page, array &$processedPages) {
		if ($page['shortcut']) {
			$pageId = (int)$page['shortcut'];
			$page = $this->pageRepository->getPage($pageId, FALSE);
			if (is_array($page) && $page['doktype'] == PageRepository::DOKTYPE_SHORTCUT) {
				$page = $this->resolveShortcutProcess($page, $processedPages);
			}
		}

		return $page;
	}

	/**
	 * Resolves shortcuts for the shortcur of type PageRepository::SHORTCUT_MODE_FIRST_SUBPAGE.
	 *
	 * @param array $page
	 * @param array $processedPages
	 * @return array
	 */
	private function resolveShortcutFirstSubpage(array $page, array &$processedPages) {
		$rows = $this->pageRepository->getMenu($page['uid']);
		if (count($rows) > 0) {
			reset($rows);
			$page = current($rows);
			if ($page['doktype'] == PageRepository::DOKTYPE_SHORTCUT) {
				$page = $this->resolveShortcutProcess($page, $processedPages);
			}
		}

		return $page;
	}

	/**
	 * Resolves shortcuts for the shortcur of type PageRepository::SHORTCUT_MODE_PARENT_PAGE.
	 *
	 * @param array $page
	 * @param array $processedPages
	 * @return array
	 */
	private function resolveShortcutParentPage(array $page, array &$processedPages) {
		$page = $GLOBALS['TSFE']->sys_page->getPage($page['pid'], FALSE);
		if ($page && $page['doktype'] == PageRepository::DOKTYPE_SHORTCUT) {
			$page = $this->resolveShortcutProcess($page, $processedPages);
		}

		return $page;
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
		foreach ($pathParts as &$part) {
			if (is_array($part)) {
				$this->sortArrayDeep($part);
			}
		}
	}
}
