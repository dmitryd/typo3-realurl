<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2010 Dmitry Dulepov <dmitry.dulepov@typo3.org>
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
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * Class for updating RealURL data
 *
 * $Id$
 *
 * @author  Dmitry Dulepov <dmitry.dulepov@typo3.org>
 * @package TYPO3
 * @subpackage realurl
 */
class ext_update {

	/**
	 * Stub function for the extension manager
	 *
	 * @param	string	$what	What should be updated
	 * @return	boolean	true to allow access
	 */
	public function access($what = 'all') {
		$fields = $GLOBALS['TYPO3_DB']->admin_get_fields('pages');
		return isset($fields['tx_aoerealurlpath_overridepath']) && isset($fields['tx_aoerealurlpath_excludefrommiddle']);
	}

	/**
	 * Updates nested sets
	 *
	 * @return	string		HTML output
	 */
	public function main() {
		if (t3lib_div::_POST('nssubmit') != '') {
			$this->updateOverridePaths();
			$content = 'Update finished successfully.';
		}
		else {
			$content = $this->prompt();
		}
		return $content;
	}

	/**
	 * Shows a form to created nested sets data.
	 *
	 * @return	string
	 */
	protected function prompt() {
		return
			'<form action="' . t3lib_div::getIndpEnv('REQUEST_URI') . '" method="POST">' .
			'<p>This update will do the following:</p>' .
			'<ul>' .
			'<li>Import path overrides from aoe_realurlpath</li>' .
			'<li>Import exclusion flags from aoe_realurlpath</li>' .
			'</ul>' .
			'<p><b>Warning!</b> All current empty values will be discarded and replaced with values from aoe_realurlpath!</p>' .
			'<br />' .
			'<input type="submit" name="nssubmit" value="Update" /></form>';
	}

	/**
	 * Creates nested sets data for pages
	 *
	 * @return	string	Result
	 */
	protected function updateOverridePaths() {
		$GLOBALS['TYPO3_DB']->sql_query('UPDATE pages SET tx_realurl_exclude=1 ' .
			'WHERE tx_aoerealurlpath_excludefrommiddle<>0');
		$GLOBALS['TYPO3_DB']->sql_query('UPDATE pages SET tx_realurl_pathoverride=1,tx_realurl_pathsegment=tx_aoerealurlpath_overridepath ' .
			'WHERE tx_aoerealurlpath_overridepath<>\'\' AND tx_realurl_pathsegment=\'\'');
		$GLOBALS['TYPO3_DB']->sql_query('UPDATE pages_language_overlay SET tx_realurl_pathsegment=tx_aoerealurlpath_overridepath ' .
			'WHERE tx_aoerealurlpath_overridepath<>\'\' AND tx_realurl_pathsegment=\'\'');
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.ext_update.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realurl/class.ext_update.php']);
}

?>
