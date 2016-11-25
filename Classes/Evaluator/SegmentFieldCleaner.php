<?php
namespace DmitryDulepov\Realurl\Evaluator;
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
 * This class contains form field evaluator that will remove leading and
 * trailing slashes from the tx_realurl_pathsegment field on save.
 *
 * @author Dmitry Dulepov <dmitry.dulepov@gmail.com>
 */
class SegmentFieldCleaner {

	/**
	 * Evaluates field value.
	 *
	 * @param string $value
	 * @return string
	 */
	public function evaluateFieldValue($value) {
        $value = str_replace('_', '-', $value);
		return trim($value, '/');
	}

}
