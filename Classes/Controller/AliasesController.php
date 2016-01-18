<?php
namespace DmitryDulepov\Realurl\Controller;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2016 Dmitry Dulepov (dmitry.dulepov@gmail.com)
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
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class provides a controller for aliases Backend function of RealURL.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class AliasesController extends BackendModuleController {

	/**
	 * @var \DmitryDulepov\Realurl\Domain\Repository\AliasRepository
	 * @inject
	 */
	protected $repository;

	/**
	 * Deletes the selected alias.
	 *
	 * @param int $uid
	 * @param string $selectedAlias
	 */
	public function deleteAction($uid, $selectedAlias) {
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias',
			'tablename=' . $this->databaseConnection->fullQuoteStr($selectedAlias, 'tx_realurl_uniqalias') .
			' AND uid=' . (int)$uid
		);
		$argments = array(
			'selectedAlias' => $selectedAlias,
		);
		if ($this->request->hasArgument('@widget_0')) {
			$arguments['@widget_0'] = $this->request->getArgument('@widget_0');
		}
		$this->forward('index', null, null, $argments);
	}

	/**
	 * Deletes all aliases of the given kind.
	 *
	 * @param string $selectedAlias
	 */
	public function deleteAllAction($selectedAlias) {
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias',
			'tablename=' . $this->databaseConnection->fullQuoteStr($selectedAlias, 'tx_realurl_uniqalias')
		);
		$this->forward('index');
	}

	/**
	 * Shows the edit form for the selected alias.
	 *
	 * @param int $uid
	 * @param string $selectedAlias
	 */
	public function editAction($uid, $selectedAlias) {
		// TODO Present a form to edit the alias and perform the action when submitted
	}

	/**
	 * Performs alias management functions.
	 *
	 * @param string $selectedAlias
	 */
	public function indexAction($selectedAlias = '') {
		$availableAliasTypes = $this->getAvailableAliasTypes();
		$this->view->assignMultiple(array(
			'availableAliasTypes' => $availableAliasTypes,
			'selectedAlias' => $selectedAlias,
		));

		if ($selectedAlias && isset($availableAliasTypes[$selectedAlias])) {
			$this->processSelectedAlias($selectedAlias);
		}
	}

	/**
	 * Obtains a list of aliases.
	 *
	 * @return array
	 */
	protected function getAvailableAliasTypes() {
		$result = array();

		$rows = $this->databaseConnection->exec_SELECTgetRows('DISTINCT tablename AS tablename', 'tx_realurl_uniqalias', '');
		array_walk($rows, function($row) use (&$result) {
			$tableNameKey = $row['tablename'];
			$tableName = '<' . $tableNameKey . '>';
			if (isset($GLOBALS['TCA'][$tableNameKey]['ctrl']['title'])) {
				if (substr($GLOBALS['TCA'][$tableNameKey]['ctrl']['title'], 0, 4) === 'LLL:') {
					$tableName = LocalizationUtility::translate($GLOBALS['TCA'][$tableNameKey]['ctrl']['title'], '');
				}
				else {
					$tableName = $GLOBALS['TCA'][$tableNameKey]['ctrl']['title'];
				}
			}
			$result[$tableNameKey] = $tableName;
		});

		asort($result);

		return $result;
	}

	/**
	 * Shows editing interface for the selected aliases.
	 *
	 * @param string $selectedAlias
	 */
	protected function processSelectedAlias($selectedAlias) {
		$query = $this->repository->createQuery();
		$query->matching($query->equals('tablename', $selectedAlias));
		$query->setOrderings(array(
			'valueId' => QueryInterface::ORDER_ASCENDING,
			'lang' => QueryInterface::ORDER_ASCENDING,
		));

		$this->view->assign('foundAliases', $query->execute());
	}
}
