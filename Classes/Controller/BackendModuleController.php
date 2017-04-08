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
use DmitryDulepov\Realurl\Utility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * This class provides a controller for the Backend module of RealURL.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
abstract class BackendModuleController extends ActionController {

	/** @var int */
	protected $currentPageId = 0;

	/** @var \TYPO3\CMS\Core\Database\DatabaseConnection */
	protected $databaseConnection;

	/** @var string[] */
	protected $excludedArgments = array();

	/**
	 * Forwards the request to the last active action.
	 *
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
	 */
	protected function forwardToLastModule() {
		$moduleData = BackendUtility::getModuleData(
			array('controller' => ''),
			array(),
			'tx_realurl_web_realurlrealurl'
		);
		//Don't need to check if it is an array because getModuleData always returns an array. Only have to check if it's empty.
		if (!empty($moduleData)) {
			$currentController = $this->getControllerName();
			if ($moduleData['controller'] !== '' && $moduleData['controller'] !== $currentController) {
				$this->redirect(null, $moduleData['controller']);
			}
		}
	}

	/**
	 * Makes action name from the current action method name.
	 *
	 * @return string
	 */
	protected function getActionName() {
		return substr($this->actionMethodName, 0, -6);
	}

	/**
	 * Makes controller name from the controller class name.
	 *
	 * @return string
	 */
	protected function getControllerName() {
		return (string)preg_replace('/^.*\\\([^\\\]+)Controller$/', '\1', get_class($this));
	}

	/**
	 * Adds code to the standard request processor for saving the last action.
	 *
	 * @param \TYPO3\CMS\Extbase\Mvc\RequestInterface $request
	 * @param \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response
	 * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
	 */
	public function processRequest(\TYPO3\CMS\Extbase\Mvc\RequestInterface $request, \TYPO3\CMS\Extbase\Mvc\ResponseInterface $response) {
		parent::processRequest($request, $response);

		// We are here ony if the action did not throw exceptions (==successful and not forwarded). Save the action.
		$this->storeLastModuleInformation();
	}

	/**
	 * Checks if the BE user has access to the given page.
	 *
	 * @param int $pageId
	 * @return bool
	 */
	protected function doesBackendUserHaveAccessToPage($pageId) {
		$record = BackendUtility::getRecord('pages', $pageId);
		return (0 !== ($GLOBALS['BE_USER']->calcPerms($record) & Permission::PAGE_SHOW));
	}

	/**
	 * Initializes all actions.
	 *
	 * @return void
	 */
	protected function initializeAction() {
		Utility::checkAndPerformRequiredUpdates();

		$this->currentPageId = (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GET('id');
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];

		// Fix pagers
		$arguments = GeneralUtility::_GPmerged('tx_realurl_web_realurlrealurl');
		if ($arguments && is_array($arguments)) {
			foreach ($arguments as $argumentKey => $argumentValue) {
				if ($argumentValue) {
					if (!in_array($argumentKey, $this->excludedArgments)) {
						GeneralUtility::_GETset($argumentValue, 'tx_realurl_web_realurlrealurl|' . $argumentKey);
					}
					else {
						GeneralUtility::_GETset('', 'tx_realurl_web_realurlrealurl|' . $argumentKey);
					}
				}
			}
		}
		else {
			$this->forwardToLastModule();
		}

		parent::initializeAction();
	}

	/**
	 * Stores information about the last action of the module.
	 */
	protected function storeLastModuleInformation() {
		// Probably should store also arguments (except pager?)
		BackendUtility::getModuleData(
			array('controller' => ''),
			array('controller' => $this->getControllerName()),
			'tx_realurl_web_realurlrealurl'
		);
	}
}
