<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004 Kasper Skaarhoj (kasper@typo3.com)
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
/**
 * Speaking Url management extension
 *
 * $Id$
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 */
/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 *
 *
 *   58: class tx_realurl_modfunc1 extends t3lib_extobjbase
 *   65:     function modMenu()
 *   82:     function main()
 *  134:     function renderModule($tree)
 *
 * TOTAL FUNCTIONS: 3
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */

require_once(PATH_t3lib.'class.t3lib_pagetree.php');
require_once(PATH_t3lib.'class.t3lib_extobjbase.php');



/**
 * Speaking Url management extension
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @package TYPO3
 * @subpackage tx_realurl
 */
class tx_realurl_modfunc1 extends t3lib_extobjbase {

	/**
	 * Returns the menu array
	 *
	 * @return	array
	 */
	function modMenu()	{
		global $LANG;

		return array (
			'depth' => array(
				1 => $LANG->getLL('depth_1'),
				2 => $LANG->getLL('depth_2'),
				3 => $LANG->getLL('depth_3')
			)
		);
	}

	/**
	 * MAIN function for cache information
	 *
	 * @return	string		Output HTML for the module.
	 */
	function main()	{
		global $BACK_PATH,$LANG,$SOBE;

		if ($this->pObj->id)	{
			$theOutput = '';

				// Depth selector:
			$h_func = t3lib_BEfunc::getFuncMenu($this->pObj->id,'SET[depth]',$this->pObj->MOD_SETTINGS['depth'],$this->pObj->MOD_MENU['depth'],'index.php');
			$theOutput.= $h_func;

				// Add CSH:
		#	$theOutput.= t3lib_BEfunc::cshItem('_MOD_web_info','lang',$GLOBALS['BACK_PATH'],'|<br/>');

				// Showing the tree:
				// Initialize starting point of page tree:
			$treeStartingPoint = intval($this->pObj->id);
			$treeStartingRecord = t3lib_BEfunc::getRecord('pages', $treeStartingPoint);
			$depth = $this->pObj->MOD_SETTINGS['depth'];

				// Initialize tree object:
			$tree = t3lib_div::makeInstance('t3lib_pageTree');
			$tree->init('AND '.$GLOBALS['BE_USER']->getPagePermsClause(1));

				// Creating top icon; the current page
			$HTML = t3lib_iconWorks::getIconImage('pages', $treeStartingRecord, $GLOBALS['BACK_PATH'],'align="top"');
			$tree->tree[] = array(
				'row' => $treeStartingRecord,
				'HTML' => $HTML
			);

				// Create the tree from starting point:
			$tree->getTree($treeStartingPoint, $depth, '');

				// Add CSS needed:
			$css_content = '
				TABLE#tx-realurl-pathcacheTable TD { vertical-align: top; }
			';
			$marker = '/*###POSTCSSMARKER###*/';
			$this->pObj->content = str_replace($marker,$css_content.chr(10).$marker,$this->pObj->content);

				// Render information table:
			$theOutput.= $this->renderModule($tree);
		} else {
				// Render clear-all page-cache

		}

		return $theOutput;
	}

	/**
	 * Rendering the information
	 *
	 * @param	array		The Page tree data
	 * @return	string		HTML for the information table.
	 */
	function renderModule($tree)	{

			// Traverse tree:
		$output = '';
		foreach($tree->tree as $row)	{
			$tCells = array();

			$tCells[]='<td>'.$row['HTML'].''.t3lib_div::fixed_lgd_cs($row['row']['title'],30).'</td>';
			$tCells[]='<td>???</td>';

				// Compile Row:
			$output.= '
				<tr class="bgColor4">
					'.implode('
					',$tCells).'
				</tr>';
		}

			// Create header:
		$tCells = array();
		$tCells[]='<td>Title:</td>';
		$tCells[]='<td>!</td>';
		$output = '
			<tr class="bgColor5 tableheader">
				'.implode('
				',$tCells).'
			</tr>'.$output;

			// Compile final table and return:
		$output = '
		<table border="0" cellspacing="1" cellpadding="0" id="tx-realurl-pathcacheTable" class="lrPadding">'.$output.'
		</table>';

		return $output;
	}
}



if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cachemgm/modfunc1/class.tx_realurl_modfunc1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/cachemgm/modfunc1/class.tx_realurl_modfunc1.php']);
}
?>