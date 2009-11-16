<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Dmitry Dulepov <dmitry@typo3.org>
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
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * $Id$
 */


/**
 * This class implement a RealURL clean up handler. This handler will inspect,
 * clean and optimize RealURL cache tables.
 *
 * @author	Dmitry Dulepov <dmitry@typo3.org>
 * @package	TYPO3
 * @subpackage	tx_realurl
 */

class tx_realurl_cleanuphandler extends tx_lowlevel_cleaner_core {

	/**
	 * Creates an instance of this class
	 *
	 * @return	void
	 */
	public function __construct() {
		parent::tx_lowlevel_cleaner_core();

		$this->cli_options[] = array('--verbose', 'Report what is done.');
		$this->cli_options[] = array('--disable-optimize', 'Disable optimization of tables.');

		$this->cli_help['name'] = 'tx_realurl_cleanuphandler -- Inspect, clean and optimize RealURL caches';
		$this->cli_help['description'] = trim('
This module checks RealURL caches, removes duplicate/expired entries and optimizes tables. This gives better RealURL performance.
');

		$this->cli_help['examples'] = '';
	}

	/**
	 * Main entry point to the clean up handler
	 *
	 * @return	array	Status
	 */
	public function main() {
		$resultArray = array(
			'message' => $this->cli_help['name'].chr(10).chr(10).$this->cli_help['description'],
			'headers' => array(
				'encode_cache' => array('Encode cache diagnostics', 'Number of removed entries in the encode cache', 1),
				'decode_cache' => array('Decode cache diagnostics','Number of removed entries in the encode cache', 2),
				'chash_cache' => array('List of elements that can be deleted','This is all elements which had no references to them and hence should be OK to delete right away.',2),
				'path_cache' => array('Decode cache diagnostics','Number of removed entries in the encode cache', 2),
			),
			'encode_cache' => array(),
			'decode_cache' => array(),
			'chash_cache' => array(),
			'path_cache' => array(),
		);

		touch(PATH_site . 'typo3temp/realurl_cleanup');

		@unlink(PATH_site . 'typo3temp/realurl_cleanup');

		return $resultArray;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/cleanup/class.tx_realurl_cleanuphandler.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/cleanup/class.tx_realurl_cleanuphandler.php']);
}


?>