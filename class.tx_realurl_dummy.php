<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2004 Kasper Skaarhoj (kasper@typo3.com)
*  All rights reserved
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
/**
 * Example class for simple management of page IDs in Speaking URLs
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
 *   68: class tx_realurl_dummy
 *   82:     function main(&$params,$ref)
 *  109:     function idToPath(&$paramKeyValues, &$pathParts)
 *  121:     function pathToId(&$pathParts)
 *
 * TOTAL FUNCTIONS: 3
 * (This index is automatically created/updated by the extension "extdeveval")
 *
 */










/**
 * Example class for simple management of page IDs in Speaking URLs
 * This class does *exactly* the same as the default behaviour which is to just output the page id/alias as a single segment of the speaking URL
 * By looking at this class you should be able to write your own implementations using this framework to start up.
 * See the manual of "realurl" extension to see how you can activate this id-resolving method.
 *
 * @author	Kasper Skaarhoj <kasper@typo3.com>
 * @package TYPO3
 * @subpackage tx_realurl
 */
class tx_realurl_dummy {

		// Internal, dynamic:
	var $pObjRef;				// Reference to the parent object of "tx_realurl"
	var $conf;					// Local configuration for the "pagePath"

	/**
	 * Main function, called for both encoding and deconding of URLs.
	 * Based on the "mode" key in the $params array it branches out to either decode or encode functions.
	 *
	 * @param	array		Parameters passed from parent object, "tx_realurl". Some values are passed by reference! (paramKeyValues, pathParts and pObj)
	 * @param	object		Copy of parent object. Not used.
	 * @return	mixed		Depends on branching.
	 */
	function main(&$params,$ref)	{

			// Branching out based on type:
		switch((string)$params['mode'])	{
			case 'encode':
				$this->pObjRef = &$params['pObj'];
				$this->conf = $params['conf'];
				return $this->idToPath($params['paramKeyValues'],$params['pathParts']);
			break;
			case 'decode':
				$this->pObjRef = &$params['pObj'];
				$this->conf = $params['conf'];
				return array($this->pathToId($params['pathParts']));
			break;
			default:
			break;
		}
	}

	/**
	 * Creating the TYPO3 Page path into $pathParts from the "id" value in $paramKeyValues
	 *
	 * @param	array		Current URLs GETvar => value pairs in array, being translated into pathParts: Here we take out "id" GET var.
	 * @param	array		Numerical array of path-parts, continously being filled. Here, the "page path" is being added by which-ever method is preferred. Passed by reference.
	 * @return	void		Unsetting "id" from $paramKeyValues / Setting page path in $pathParts
	 * @see tx_realurl::encodeSpURL_pathFromId()
	 */
	function idToPath(&$paramKeyValues, &$pathParts)	{
		$pathParts[] = rawurlencode($paramKeyValues['id']);
		unset($paramKeyValues['id']);
	}

	/**
	 * Extracts the page ID from URL.
	 *
	 * @param	array		Parts of path. NOTICE: Passed by reference.
	 * @return	integer		Page ID
	 * @see tx_realurl::decodeSpURL_idFromPath()
	 */
	function pathToId(&$pathParts)	{
		$value = array_shift($pathParts);
		return $value;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_dummy.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.tx_realurl_dummy.php']);
}

?>