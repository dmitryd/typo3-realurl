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
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class updates realurl from version 1.x to 2.x.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
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
		$lock = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Locking\\LockFactory')->createLocker('tx_realurl_update');
		/** @var \TYPO3\CMS\Core\Locking\LockingStrategyInterface $lock */
		try {
			$lock->acquire();
		}
		catch (\TYPO3\CMS\Core\Locking\Exception $e) {
			// Nothing
		}

		$this->checkAndRenameTables();
		if ($this->pathCacheNeedsUpdates()) {
			$this->databaseConnection->sql_query('ALTER TABLE tx_realurl_pathdata CHANGE cache_id uid int(11) NOT NULL');
			$this->databaseConnection->sql_query('ALTER TABLE tx_realurl_pathdata DROP PRIMARY KEY');
			$this->databaseConnection->sql_query('ALTER TABLE tx_realurl_pathdata MODIFY uid int(11) NOT NULL auto_increment primary key');
		}

		if ($lock->isAcquired()) {
			$lock->release();
		}
	}

	/**
	 * Checks if the script should execute.
	 *
	 * @return bool
	 */
	public function access() {
		return $this->hasOldCacheTables() || $this->pathCacheNeedsUpdates();
	}

	/**
	 * Checks and renames *cache tables to *data tables.
	 */
	protected function checkAndRenameTables() {
		$tableMap = array(
			'tx_realurl_pathcache' => 'tx_realurl_pathdata',
			'tx_realurl_urlcache' => 'tx_realurl_urldata',
		);

		$tables = $this->databaseConnection->admin_get_tables();
		foreach ($tableMap as $oldTableName => $newTableName) {
			if (isset($tables[$oldTableName])) {
				if (!isset($tables[$newTableName])) {
					$this->databaseConnection->sql_query('ALTER TABLE ' . $oldTableName . ' RENAME TO ' . $newTableName);
				}
				else {
					if ((int)$tables[$newTableName]['Rows'] === 0) {
						$this->databaseConnection->sql_query('INSERT INTO ' . $newTableName . ' SELECT * FROM ' . $oldTableName);
					}
					$this->databaseConnection->sql_query('DROP TABLE' . $oldTableName);
				}
			}
		}
	}

	protected function hasOldCacheTables() {
		$tables = $this->databaseConnection->admin_get_tables();
		return isset($tables['tx_realurl_pathcache']) || isset($tables['tx_realurl_urlcache']);
	}

	/**
	 * Checks if path cache table is ok.
	 *
	 * @return bool
	 */
	protected function pathCacheNeedsUpdates() {
		$fields = $this->databaseConnection->admin_get_fields('tx_realurl_pathdata');

		return isset($fields['cache_id']) || !isset($fields['uid']) || stripos($fields['uid']['Extra'], 'auto_increment') === false;
	}

}
