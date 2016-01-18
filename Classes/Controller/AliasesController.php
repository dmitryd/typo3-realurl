<?php
namespace DmitryDulepov\Realurl\Controller;
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
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * This class provides a controller for aliases Backend function of RealURL.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class AliasesController extends BackendModuleController {

	/**
	 * Performs alias management functions.
	 */
	public function indexAction() {
		$availableAliasTypes = $this->getAvailableAliasTypes();
		if (count($availableAliasTypes) == 0) {
			$this->addFlashMessage(LocalizationUtility::translate('LLL:EXT:realurl/Resources/Private/Language/locallang.xlf:module.aliases.not_available', ''), '', AbstractMessage::INFO);
		}

		$selectedAlias = GeneralUtility::_GP('selectedAlias');
		if ($selectedAlias) {
			// Fix pagination
			GeneralUtility::_GETset($selectedAlias, 'selectedAlias');
		}

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
		$repository = $this->objectManager->get('DmitryDulepov\\Realurl\\Domain\\Repository\\AliasRepository');
		/** @var \DmitryDulepov\Realurl\Domain\Repository\AliasRepository $repository */
		$query = $repository->createQuery();
		$query->matching($query->equals('tablename', $selectedAlias));
		$query->setOrderings(array(
			'valueId' => QueryInterface::ORDER_ASCENDING,
			'lang' => QueryInterface::ORDER_ASCENDING,
		));

		$this->view->assign('foundAliases', $query->execute());
	}
}
