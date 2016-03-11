<?php
namespace DmitryDulepov\Realurl;
/***************************************************************
*  Copyright notice
*
*  (c) 2016 Dmitry Dulepov <dmitry.dulepov@gmail.com>
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
 * This class updates realurl from version 1.x to 2.x.
 *
 * @author Dmitry Dulepov <support@snowflake.ch>
 */
class ext_update {

	/** @var \TYPO3\CMS\Core\Database\DatabaseConnection */
	protected $databaseConnection;

	/**
	 * Creates the instance of the class.
	 */
	public function __construct() {
		$this->databaseConnection = $GLOBALS['TYPO3_DB'];
	}

	/**
	 * Runs the update.
	 */
	public function main() {
		if ($this->pathCacheNeedsUpdates()) {
			$this->databaseConnection->sql_query('ALTER TABLE tx_realurl_pathcache CHANGE cache_id uid int(11) NOT NULL');
			$this->databaseConnection->sql_query('ALTER TABLE tx_realurl_pathcache DROP PRIMARY KEY');
			$this->databaseConnection->sql_query('ALTER TABLE tx_realurl_pathcache MODIFY uid int(11) NOT NULL auto_increment primary key');
		}
	}

	/**
	 * Checks if the script should execute.
	 *
	 * @return bool
	 */
	public function access() {
		return $this->pathCacheNeedsUpdates();
	}

	/**
	 * Checks if path cache table is ok.
	 *
	 * @return bool
	 */
	protected function pathCacheNeedsUpdates() {
		$fields = $this->databaseConnection->admin_get_fields('tx_realurl_pathcache');

		return isset($fields['cache_id']) || !isset($fields['uid']) || stripos($fields['uid']['Extra'], 'auto_increment') === false;
	}

}
