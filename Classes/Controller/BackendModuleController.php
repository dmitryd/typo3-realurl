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
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class provides a controller for the Backend module of RealURL.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
abstract class BackendModuleController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController {

	/** @var int */
	protected $currentPageId = 0;

	/** @var \TYPO3\CMS\Core\Database\DatabaseConnection */
	protected $databaseConnection;

	/**
	 * Initializes all actions.
	 *
	 * @return void
	 */
	protected function initializeAction() {
		$this->currentPageId = (int)\TYPO3\CMS\Core\Utility\GeneralUtility::_GET('id');
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];

		$pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
		/** @var \TYPO3\CMS\Core\Page\PageRenderer $pageRenderer */
		$pageRenderer->addJsLibrary('jQuery', 'sysext/core/Resources/Public/JavaScript/Contrib/jquery/jquery-2.1.4.min.js');
		$pageRenderer->addJsFile(ExtensionManagementUtility::extRelPath('realurl') . 'Resources/Public/realurl_be.js');

		parent::initializeAction();
	}
}
