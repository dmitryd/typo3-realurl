<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2007 Jan Bednarik <info@bednarik.org>
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


	// DEFAULT initialization of a module [BEGIN]
unset($MCONF);
require_once('conf.php');
require_once($BACK_PATH . 'init.php');
require_once($BACK_PATH . 'template.php');

$LANG->includeLLFile('EXT:realurl/mod1/locallang.xml');
require_once(PATH_t3lib . 'class.t3lib_scbase.php');
$BE_USER->modAccess($MCONF,1);	// This checks permissions and exits if the users has no permission for entry.
	// DEFAULT initialization of a module [END]

/**
 * Module 'RealURL' for the 'realurl' extension.
 *
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 * @package	TYPO3
 * @subpackage	tx_realurl
 */
class tx_realurl_module1 extends t3lib_SCbase {

	var $configs = array(
		'common' => array(
		),
		'sites' => array(
		),
	);

	/**
	 * Adds items to the ->MOD_MENU array. Used for the function menu selector.
	 *
	 * @return	void
	 */
	function menuConfig()	{
		global $LANG;
		$this->MOD_MENU = Array (
		/*
			'function' => Array (
				'1' => $GLOBALS['LANG']->getLL('function1'),
				'2' => $GLOBALS['LANG']->getLL('function2'),
				'3' => $GLOBALS['LANG']->getLL('function3'),
			)
		*/
		);
		parent::menuConfig();
	}

	/**
	 * Main function of the module. Write the content to $this->content
	 * If you chose "web" as main module, you will need to consider the $this->id parameter which will contain the uid-number of the page clicked in the page tree
	 *
	 * @return	[type]		...
	 */
	function main()	{
		$this->doc = t3lib_div::makeInstance('noDoc');
		$this->doc->backPath = $GLOBALS['BACK_PATH'];
		$this->doc->docType = 'xhtml_trans';
		$this->doc->form = '<form action="index.php" method="post" autocomplete="off">';
		$this->doc->JScode .= $this->doc->getDynTabMenuJScode();

		$this->content .= $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
		$this->content .= $this->doc->header($GLOBALS['LANG']->getLL('title'));
		$this->content .= '<p>' . $GLOBALS['LANG']->getLL('description') . '</p>';

		$this->getConfigs();

		$this->content .= $this->createTabs();

		$this->content .= $this->doc->endPage();
	}

	function getConfigs() {
		$conf = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['realurl'];
		$common_el = array_shift($conf);
		foreach ($conf as $common_el) {
			//array_diff
		}
	}

	function createTabs() {
		// Build top menu:
		$menuItems = array(
			array(
				'label' => $GLOBALS['LANG']->getLL('menuitem_init'),
				'linkTitle' => '',
				'content' => 'init',//$this->moduleContent_publish()
			),
			array(
				'label' => $GLOBALS['LANG']->getLL('menuitem_preVars'),
				'linkTitle' => '',
				'content' => 'preVars',
			),
			array(
				'label' => $GLOBALS['LANG']->getLL('menuitem_fixedPostVars'),
				'linkTitle' => '',
				'content' => 'fixedPostVars',
			),
			array(
				'label' => $GLOBALS['LANG']->getLL('menuitem_postVarSets'),
				'linkTitle' => '',
				'content' => 'postVarSets',
			),
			array(
				'label' => $GLOBALS['LANG']->getLL('menuitem_pagePath'),
				'linkTitle' => '',
				'content' => 'pagePath',
			),
			array(
				'label' => $GLOBALS['LANG']->getLL('menuitem_fileName'),
				'linkTitle' => '',
				'content' => 'fileName',
			),
		);

			// Add hidden fields and create tabs:
		$content = $this->doc->getDynTabMenu($menuItems, 'realurl');

		return $content;
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void
	 */
	function printContent()	{
		$this->content .= '</div>';
		echo $this->content;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/mod1/index.php']);
}




// Make instance:
$SOBE = t3lib_div::makeInstance('tx_realurl_module1');
$SOBE->init();

// Include files?
foreach($SOBE->include_once as $INC_FILE) {
	@include_once($INC_FILE);
}

$SOBE->main();
$SOBE->printContent();

?>