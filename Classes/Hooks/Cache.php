<?php
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
namespace DmitryDulepov\Realurl\Hooks;

use DmitryDulepov\Realurl\Cache\CacheFactory;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * This class implements a hook to clea RealURL cache if page cache is cleared.
 *
 * @package DmitryDulepov\Realurl\Hooks
 */
class Cache {

	/**
	 * Clears the URL cache according to parameters.
	 *
	 * @param array $parameters
	 * @return void
	 */
	public function clearUrlCache(array $parameters) {
		$cacheCommand = $parameters['cacheCmd'];
		if ($cacheCommand == 'pages' || $cacheCommand == 'all' || MathUtility::canBeInterpretedAsFloat($cacheCommand)) {
			$cacheInstance = CacheFactory::getCache();
			if ($cacheCommand == 'pages' || $cacheCommand == 'all') {
				$cacheInstance->clearUrlCache();
			}
			else {
				$cacheInstance->clearUrlCacheForPage($cacheCommand);
			}
		}
	}

	/**
	 * Clears URL cache for records.
	 *
	 * Currently only clears URL cache for pages but should also clear alias
	 * cache for records later.
	 *
	 * @param array $parameters
	 * @return void
	 */
	public function clearUrlCacheForRecords(array $parameters) {
		if ($parameters['table'] == 'pages' && MathUtility::canBeInterpretedAsInteger($parameters['uid'])) {
			$cacheInstance = CacheFactory::getCache();
			$cacheInstance->clearUrlCacheForPage($parameters['uid']);
		}
	}
}
