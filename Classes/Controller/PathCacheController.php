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
class PathCacheController extends BackendModuleController {

	/**
	 * @var \DmitryDulepov\Realurl\Domain\Repository\PathCacheEntryRepository
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
		$this->databaseConnection->exec_DELETEquery('tx_realurl_pathdata', 'uid=' . (int)$uid);

		$this->addFlashMessage(LocalizationUtility::translate('module.path_cache.entry_deleted', 'realurl'));

		$this->forward('index', 'PathCache');
	}

	/**
	 * Shows a list of page path entries.
	 */
	public function indexAction() {
		$entries = $this->getCacheEntries();
		$this->makeMessagesForDuplicates($entries);
		$entries->rewind();
		$this->view->assignMultiple(array(
			'entries' => $entries
		));
	}

	/**
	 * Adds a message about duplicate URLs.
	 *
	 * @param string $pagePath
	 * @param QueryResultInterface $entries
	 */
	protected function addDuplicateMessage($pagePath, QueryResultInterface $entries) {
		// The ugly variable below has to be used because Extbase misses DISTINCT for queries
		static $addedCombinations = array();

		$pageIds = array();
		foreach ($entries as $entry) {
			/** @var \DmitryDulepov\Realurl\Domain\Model\PathCacheEntry $entry */
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
			$combination = $pagePath . '_' . implode('_', $pageIds);
			if (!isset($addedCombinations[$combination])) {
				$addedCombinations[$combination] = true;

				$message = LocalizationUtility::translate('module.path_cache.duplicate_path', 'realurl', array($pagePath, implode(', ', $pageIds)));

				$flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, '', FlashMessage::ERROR);
				/** @var \TYPO3\CMS\Core\Messaging\FlashMessage $flashMessage */
				$this->controllerContext->getFlashMessageQueue()->enqueue($flashMessage);
			}
		}
	}

	/**
	 * Fetches cache entries.
	 *
	 * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
	 */
	protected function getCacheEntries() {
		$pageId = (int)GeneralUtility::_GP('id');
		$query = $this->repository->createQuery();
		$query->setOrderings(array(
			'languageId' => QueryInterface::ORDER_ASCENDING,
			'expire' => QueryInterface::ORDER_ASCENDING,
			'pagePath' => QueryInterface::ORDER_ASCENDING,
		));
		$query->matching($query->equals('pageId', $pageId));

		return $query->execute();
	}

	/**
	 * Checks if there are any duplicates for this url and adds warnings.
	 *
	 * @param QueryResultInterface $entries
	 */
	protected function makeMessagesForDuplicates(QueryResultInterface $entries) {
		foreach ($entries as $entry) {
			/** @var \DmitryDulepov\Realurl\Domain\Model\PathCacheEntry $entry */
			$query = $this->repository->createQuery();
			// Conditions (logical and):
			// 1. Different page id
			// 2. Same path
			// 3. Same root page id
			// 4. Same language id
			/** @noinspection PhpMethodParametersCountMismatchInspection */
			$query->matching($query->logicalAnd(
				$query->logicalNot($query->equals('pageId', $entry->getPageId())),
				$query->equals('rootPageId', $entry->getRootPageId()),
				$query->equals('pagePath', $entry->getPagePath()),
				$query->equals('languageId', $entry->getLanguageId())
			));
			$query->setOrderings(array(
				'pagePath' => QueryInterface::ORDER_ASCENDING,
				'languageId' => QueryInterface::ORDER_ASCENDING,
			));

			$result = $query->execute();
			if ($result->count() > 0) {
				$this->addDuplicateMessage($entry->getPagePath(), $result);
			}

			unset($result);
			unset($query);
		}
	}

}
