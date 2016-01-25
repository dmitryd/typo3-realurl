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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class implements management of RealURL url cache.
 *
 * @author Dmitry Dulepov <support@snowflake.ch>
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
		$this->databaseConnection->exec_DELETEquery('tx_realurl_pathcache', 'uid=' . (int)$uid);

		$this->addFlashMessage(LocalizationUtility::translate('module.path_cache.entry_deleted', 'realurl'));

		$this->forward('index', 'PathCache');
	}

	/**
	 * Shows a list of page path entries.
	 */
	public function indexAction() {
		$this->view->assignMultiple(array(
			'entries' => $this->getCacheEntries()
		));
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
}
