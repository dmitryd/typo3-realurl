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

/**
 * This class implements management of RealURL url cache.
 *
 * @author Dmitry Dulepov <support@snowflake.ch>
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
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 */
	public function deleteAction() {
		$this->forward('index', 'UrlCache');
	}

	/**
	 * Deletes all entries for the given page.
	 *
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 */
	public function deleteAllAction() {
		$this->forward('index', 'UrlCache');
	}

	/**
	 * Deletes all entries from the cache.
	 *
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 */
	public function flushAction() {
		$this->forward('index', 'UrlCache');
	}

	public function indexAction() {
		$this->view->assignMultiple(array(
			'entries' => $this->getCacheEntries()
		));
	}

	protected function getCacheEntries() {
		$pageId = (int)GeneralUtility::_GP('id');
		$query = $this->repository->createQuery();
		$query->setOrderings(array(
			'speakingUrl' => QueryInterface::ORDER_ASCENDING,
			'originalUrl' => QueryInterface::ORDER_ASCENDING,
		));
		$query->matching($query->equals('pageId', $pageId));

		return $query->execute();
	}
}
