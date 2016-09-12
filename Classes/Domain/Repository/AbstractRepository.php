<?php
namespace DmitryDulepov\Realurl\Domain\Repository;
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

use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * This class implements a base repository for all RealURl repositories.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
abstract class AbstractRepository extends Repository {

	/**
	 * Creates query and makes sure that no Extbase magic is performed with
	 * pid, languages, etc.
	 *
	 * Magic is cool but not on this case.
	 *
	 * @return \TYPO3\CMS\Extbase\Persistence\QueryInterface
	 */
	public function createQuery() {
		$query = parent::createQuery();
		$query->getQuerySettings()->setRespectStoragePage(false)->setIgnoreEnableFields(true)->setRespectSysLanguage(false);

		return $query;
	}

}
