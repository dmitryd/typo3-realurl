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

	/** @var string[] */
	protected $excludedArgments = array('uid', 'valueAlias', 'submit');

	/**
	 * @var \DmitryDulepov\Realurl\Domain\Repository\AliasRepository
	 * @inject
	 */
	protected $repository;

	/** @var string */
	static protected $validAliasCharacters = 'abcdefghijklmnopqrstuvwxyz0123456789-';

	/**
	 * Deletes the selected alias.
	 *
	 * @param int $uid
	 * @param string $selectedAlias
	 */
	public function deleteAction($uid, $selectedAlias) {
		$this->databaseConnection->sql_query('START TRANSACTION');
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias',
			'tablename=' . $this->databaseConnection->fullQuoteStr($selectedAlias, 'tx_realurl_uniqalias') .
			' AND uid=' . (int)$uid
		);
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias_cache_map',
			'alias_uid=' . (int)$uid
		);
		$this->databaseConnection->sql_query('COMMIT');
		$this->addFlashMessage(LocalizationUtility::translate('module.aliases.deleted', 'realurl'));
		$this->forward('index', null, null, $this->makeArgumentArray());
	}

	/**
	 * Deletes all aliases of the given kind.
	 *
	 * @param string $selectedAlias
	 */
	public function deleteAllAction($selectedAlias) {
		$this->databaseConnection->sql_query('START TRANSACTION');
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias_cache_map',
			'alias_uid IN (SELECT uid FROM tx_realurl_uniqalias WHERE ' .
				'tablename=' . $this->databaseConnection->fullQuoteStr($selectedAlias, 'tx_realurl_uniqalias_cache_map') .
				')'
		);
		$this->databaseConnection->exec_DELETEquery('tx_realurl_uniqalias',
			'tablename=' . $this->databaseConnection->fullQuoteStr($selectedAlias, 'tx_realurl_uniqalias')
		);
		$this->databaseConnection->sql_query('COMMIT');
		$this->addFlashMessage(LocalizationUtility::translate('module.aliases.all_deleted', 'realurl'));
		$this->forward('index');
	}

	/**
	 * Shows the edit form for the selected alias.
	 *
	 * @param int $uid
	 * @param string $selectedAlias
	 */
	public function editAction($uid, $selectedAlias) {
		if ($this->request->hasArgument('submit')) {
			if ($this->processEditSubmission()) {
				$_GET['tx_realurl_web_realurlrealurl'] = array(
					'controller' => $_GET['tx_realurl_web_realurlrealurl']['controller'],
				);
				$this->forward('index', null, null, $this->makeArgumentArray());
			}
		}

		$alias = $this->repository->findByUid((int)$uid);
		/** @var \DmitryDulepov\Realurl\Domain\Model\Alias $alias */
		if (!$alias) {
			$this->addFlashMessage(LocalizationUtility::translate('module.aliases.not_found', 'realurl'));
			$this->forward('index', null, null, $this->makeArgumentArray());
		}

		if ($this->request->hasArgument('valueAlias')) {
			$alias->setValueAlias($this->request->getArgument('valueAlias'));
		}

		$this->view->assignMultiple(array(
			'alias' => $alias,
			'selectedAlias' => $selectedAlias,
		));
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
			'searchAlias' => $this->request->hasArgument('searchAlias') ? $this->request->getArgument('searchAlias') : '',
			'selectedAlias' => $selectedAlias,
		));

		if ($selectedAlias && isset($availableAliasTypes[$selectedAlias])) {
			$this->processSelectedAlias($selectedAlias);
		}
	}

	/**
	 * Checks if this alias already exists for another record.
	 *
	 * @param string $aliasValue
	 * @return bool
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException
	 */
	protected function doesAliasExistsForAnotherRecord($aliasValue) {
		$result = false;

		$alias = $this->repository->findByUid((int)$this->request->getArgument('uid'));
		/** @var \DmitryDulepov\Realurl\Domain\Model\Alias $alias */
		if ($alias) {
			$query = $this->repository->createQuery();
			$query->matching($query->logicalAnd(array(
				$query->equals('valueAlias', $aliasValue),
				$query->equals('tablename', $alias->getTablename()),
				$query->equals('lang', $alias->getLang()),
				$query->logicalNot($query->equals('uid', (int)$alias->getUid()))
			)));

			$result = $query->count() > 0;
		}

		return $result;
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
	 * Checks if value alias is valid.
	 *
	 * @param $aliasValue
	 * @return int
	 */
	protected function isValidAliasValue($aliasValue) {
		return preg_match('/^[' . preg_quote(self::$validAliasCharacters, '/') . ']+$/', $aliasValue);
	}

	/**
	 * Processes edit form submission. Also adds flash messages if something is not right.
	 *
	 * @return bool
	 */
	protected function processEditSubmission() {
		$result = false;

		$aliasValue = $this->request->getArgument('valueAlias');

		if (!$this->isValidAliasValue($aliasValue)) {
			$this->addFlashMessage(LocalizationUtility::translate('module.aliases.edit.error.bad_alias_value', 'realurl', array(self::$validAliasCharacters)), '', AbstractMessage::ERROR);
		}
		elseif ($this->doesAliasExistsForAnotherRecord($aliasValue)) {
			$this->addFlashMessage(LocalizationUtility::translate('module.aliases.edit.error.already_exists', 'realurl'), '', AbstractMessage::ERROR);
		}
		else {
			$alias = $this->repository->findByUid((int)$this->request->getArgument('uid'));
			/** @var \DmitryDulepov\Realurl\Domain\Model\Alias $alias */
			if (!$alias) {
				$this->addFlashMessage(LocalizationUtility::translate('module.aliases.not_found', 'realurl'), '', AbstractMessage::ERROR);
			}
			else {
				if ($alias->getValueAlias() != $aliasValue) {
					$alias->setValueAlias($aliasValue);
					$this->repository->update($alias);
				}
				$this->addFlashMessage(LocalizationUtility::translate('module.aliases.edit.saved', 'realurl'), '', AbstractMessage::OK);
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * Shows editing interface for the selected aliases.
	 *
	 * @param string $selectedAlias
	 */
	protected function processSelectedAlias($selectedAlias) {
		$query = $this->repository->createQuery();
		$conditons[] = $query->equals('tablename', $selectedAlias);
		if ($this->request->hasArgument('searchAlias')) {
			$searchString = $this->request->getArgument('searchAlias');
			$searchString = $GLOBALS['TYPO3_DB']->escapeStrForLike($searchString, $selectedAlias);
			$conditons[] = $query->like('valueAlias', '%' . $searchString . '%');
		}
		$query->matching(count($conditons) > 1 ? $query->logicalAnd($conditons) : reset($conditons));
		$query->setOrderings(array(
			'valueId' => QueryInterface::ORDER_ASCENDING,
			'lang' => QueryInterface::ORDER_ASCENDING,
		));

		$this->view->assign('foundAliases', $query->execute());
	}

	/**
	 * Creates argument array for 'forward' method.
	 *
	 * @return array
	 */
	protected function makeArgumentArray() {
		$arguments = array();
		if ($this->request->hasArgument('selectedAlias')) {
			$arguments['selectedAlias'] = $this->request->getArgument('selectedAlias');
		}
		if ($this->request->hasArgument('@widget_0')) {
			$arguments['@widget_0'] = $this->request->getArgument('@widget_0');
		}

		return $arguments;
	}
}
