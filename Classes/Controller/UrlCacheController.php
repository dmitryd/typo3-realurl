<?php
namespace DmitryDulepov\Realurl\Controller;
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

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class implements management of RealURL url cache.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class UrlCacheController extends BackendModuleController {

	/**
	 * @var \DmitryDulepov\Realurl\Domain\Repository\UrlCacheEntryRepository
	 * @inject
	 */
	protected $repository;

	/**
	 * Deletes a given entry for the given page.
	 *
	 * @param int $uid
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 */
	public function deleteAction($uid) {
		$this->databaseConnection->sql_query('START TRANSACTION');
		$this->databaseConnection->exec_DELETEquery('tx_realurl_urldata', 'uid=' . (int)$uid);
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias_cache_map', 'url_cache_id=' . (int)$uid);
		$this->databaseConnection->sql_query('COMMIT');

		$this->addFlashMessage(LocalizationUtility::translate('module.url_cache.entry_deleted', 'realurl'));

		$this->forward('index', 'UrlCache');
	}

	/**
	 * Deletes all entries for the given page.
	 *
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 */
	public function deleteAllAction() {
		$this->databaseConnection->sql_query('START TRANSACTION');
		$this->databaseConnection->sql_query('DELETE FROM tx_realurl_uniqalias_cache_map WHERE ' .
			'url_cache_id IN (SELECT uid FROM tx_realurl_urldata WHERE page_id=' . (int)GeneralUtility::_GP('id') . ')'
		);
		$this->databaseConnection->sql_query('DELETE FROM tx_realurl_urldata WHERE page_id=' . (int)GeneralUtility::_GP('id'));
		$this->databaseConnection->sql_query('COMMIT');

		$this->addFlashMessage(LocalizationUtility::translate('module.url_cache.all_entries_deleted', 'realurl'));

		$this->forward('index', 'UrlCache');
	}

	/**
	 * Deletes all entries from the cache.
	 *
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 */
	public function flushAction() {
		$this->databaseConnection->sql_query('START TRANSACTION');
		// Not using TRUNCATE because of DBAL
		$this->databaseConnection->sql_query('DELETE FROM tx_realurl_uniqalias_cache_map');
		$this->databaseConnection->sql_query('DELETE FROM tx_realurl_urldata');
		$this->databaseConnection->sql_query('COMMIT');

		$this->addFlashMessage(LocalizationUtility::translate('module.url_cache.flushed', 'realurl'));

		$this->forward('index', 'UrlCache');
	}

	/**
	 * Shows a list of URL cache entries.
	 */
	public function indexAction() {
		$entries = $this->getCacheEntries();
		$this->makeMessagesForDuplicates($entries);
		$entries->rewind();
		$this->view->assignMultiple(array(
			'entries' => $entries,
			'showFlushAllButton' => $this->shouldShowFlushAllButton(),
			'showFlushAllPageUrlsButton' => $this->shouldShowFlushAllPageUrlsButton(),
		));
	}

	/**
	 * Adds a message about duplicate URLs.
	 *
	 * @param string $speakingUrl
	 * @param QueryResultInterface $entries
	 */
	protected function addDuplicateMessage($speakingUrl, QueryResultInterface $entries) {
		// The ugly variable below has to be used because Extbase misses DISTINCT for queries
		static $addedCombinations = array();

		$pageIds = array();
		foreach ($entries as $entry) {
			/** @var \DmitryDulepov\Realurl\Domain\Model\UrlCacheEntry $entry */
			$pageId = (int)$entry->getPageId();
			if (!isset($pageIds[$pageId])) {
				if ($this->doesBackendUserHaveAccessToPage($pageId)) {
					$recordPath = rtrim(BackendUtility::getRecordPath($pageId, '', 300), '/');
					$pageIds[$pageId] = sprintf('%d (%s)', $pageId, $recordPath);
				}
			}
		}
		ksort($pageIds);

		if (count($pageIds) > 0) {
			$combination = $speakingUrl . implode(' ', $pageIds);
			if (!isset($addedCombinations[$combination])) {
				$message = LocalizationUtility::translate('module.url_cache.duplicate_url', 'realurl', array($speakingUrl, implode(', ', $pageIds)));

				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, '', FlashMessage::ERROR);
				/** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
				$this->controllerContext->getFlashMessageQueue()->enqueue($flashMessage);
			}
		}
	}

	/**
	 * Loads URL cache entries.
	 *
	 * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	protected function getCacheEntries() {
		$pageId = (int)GeneralUtility::_GP('id');
		$query = $this->repository->createQuery();
		$query->setOrderings(array(
			'speakingUrl' => QueryInterface::ORDER_ASCENDING,
			'originalUrl' => QueryInterface::ORDER_ASCENDING,
			'expire' => QueryInterface::ORDER_ASCENDING,
		));
		$query->matching($query->equals('pageId', $pageId));

		return $query->execute();
	}

	/**
	 * Gets restrictions TSConfig part.
	 *
	 * @param string $restrictionPropertyName
	 * @param bool $defaultValue
	 * @return bool
	 */
	protected function getTsConfigRestriction($restrictionPropertyName, $defaultValue = false) {
		$result = $defaultValue;

		$tsConfig = BackendUtility::getModTSconfig((int)GeneralUtility::_GP('id'), 'mod.tx_realurl');
		if (is_array($tsConfig['properties']) && is_array($tsConfig['properties']['restrictions.']) && isset($tsConfig['properties']['restrictions.'][$restrictionPropertyName])) {
			$result = (bool)$tsConfig['properties']['restrictions.'][$restrictionPropertyName];
		}

		return $result;
	}

	/**
	 * Checks if there are any duplicates for this url and adds warnings.
	 *
	 * @param QueryResultInterface $entries
	 */
	protected function makeMessagesForDuplicates(QueryResultInterface $entries) {
		foreach ($entries as $entry) {
			/** @var \DmitryDulepov\Realurl\Domain\Model\UrlCacheEntry $entry */
			$query = $this->repository->createQuery();
			// Conditions (logical and):
			// 1. Different page id
			// 2. Same url
			// 3. Same root page id
			/** @noinspection PhpMethodParametersCountMismatchInspection */
			$query->matching($query->logicalAnd(
				$query->logicalNot($query->equals('pageId', $entry->getPageId())),
				$query->equals('rootPageId', $entry->getRootPageId()),
				$query->equals('speakingUrl', $entry->getSpeakingUrl())
			));
			$query->setOrderings(array(
				'speakingUrl' => QueryInterface::ORDER_ASCENDING,
			));

			$result = $query->execute();
			if ($result->count() > 0) {
				$this->addDuplicateMessage($entry->getSpeakingUrl(), $result);
			}

			unset($result);
			unset($query);
		}
	}

	/**
	 * Checks if flush all entries should be visible.
	 *
	 * @return bool
	 */
	protected function shouldShowFlushAllButton() {
		return $GLOBALS['BE_USER']->isAdmin() || !$this->getTsConfigRestriction('disableFlushAllUrls');
	}

	protected function shouldShowFlushAllPageUrlsButton() {
		return $GLOBALS['BE_USER']->isAdmin() || !$this->getTsConfigRestriction('disableFlushAllPageUrls');
	}
}
